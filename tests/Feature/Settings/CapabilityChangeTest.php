<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Changing what you do in the marketplace, after the fact.
 *
 * The capability decision used to be permanent: the wizard refused every action
 * once a user was onboarded, so somebody who registered to post a load and later
 * needed to carry one had no way to say so. It is now a field on the single
 * public profile, editable at any time.
 */
uses(RefreshDatabase::class);

function onboardedUser(array $profileOverrides = []): User
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::factory()->for($user)->create(array_merge([
        'account_type' => 'personal',
        'display_name' => 'Someone',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
        'primary_location_id' => null,
    ], $profileOverrides));

    return $user->refresh();
}

/**
 * The minimum a full profile save must carry.
 *
 * @return array<string, mixed>
 */
function capabilityPayload(User $user, array $overrides = []): array
{
    $profile = $user->profile()->firstOrFail();

    return array_merge([
        'display_name' => $profile->display_name,
        'account_type' => $profile->account_type ?? 'personal',
        'posts_freight' => (bool) $profile->posts_freight,
        'provides_transport' => (bool) $profile->provides_transport,
    ], $overrides);
}

// ── the decision is revisitable ─────────────────────────────────────────────

it('lets an onboarded consignor start carrying freight', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect((bool) $profile->posts_freight)->toBeTrue()
        ->and((bool) $profile->provides_transport)->toBeTrue();
});

it('lets an onboarded user hold both capabilities at once', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('provides_transport'))->toBeTrue();
});

it('lets a user turn one capability off without losing the other', function () {
    $user = onboardedUser(['posts_freight' => true, 'provides_transport' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => false,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect((bool) $profile->posts_freight)->toBeFalse()
        ->and((bool) $profile->provides_transport)->toBeTrue();
});

it('refuses to leave a user with neither capability', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => false,
            'provides_transport' => false,
        ]))
        ->assertSessionHasErrors('capabilities');

    expect((bool) Profile::where('user_id', $user->id)->value('posts_freight'))->toBeTrue();
});

it('does not create a second profile when the capability is switched on', function () {
    // There used to be a transporter record created behind the capability. That
    // is gone: the same profile grows a carrier half, and readiness names the
    // base location as the next thing to fill in.
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    expect(Profile::where('user_id', $user->id)->count())->toBe(1);

    expect(app(MarketplaceReadiness::class)->for($user)['missing']['provides_transport'])
        ->toContain('primary_location_id');
});

it('starts the carrier listing in draft when transport is switched on', function () {
    $user = onboardedUser(['posts_freight' => true, 'status' => null]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    expect(Profile::where('user_id', $user->id)->value('status'))->toBe('draft');
});

it('accepts carrier details once transport is switched on', function () {
    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);

    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => false,
        'primary_location_id' => $base->id,
    ]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    $this->actingAs($user)
        ->put('/public-profile/transport', [
            'truck_type_ids' => [],
            'routes' => [],
        ])
        ->assertSessionHasNoErrors();

    expect(app(MarketplaceReadiness::class)->for($user)['ready']['provides_transport'])
        ->toBeTrue();
});

it('does not overwrite existing carrier details', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $profile = $user->profile()->firstOrFail();
    $profile->forceFill([
        'business_description' => 'Ten years on the Addis–Adama run',
        'status' => 'pending',
    ])->save();

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    $after = $user->profile()->first();

    expect($after->id)->toBe($profile->id)
        ->and($after->business_description)->toBe('Ten years on the Addis–Adama run');
});

it('keeps carrier data and approval when the capability is turned off and on', function () {
    // Turning off a checkbox must not destroy trucks, routes, uploaded
    // documents, or an approval. The user may switch back tomorrow.
    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'status' => 'approved',
        'verification_status' => 'verified',
    ]);

    $profile = $user->profile()->firstOrFail();
    $truckType = TruckType::factory()->create();
    $profile->truckTypes()->attach($truckType->id);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => false,
        ]))
        ->assertRedirect();

    $profile->refresh();

    expect($profile->status)->toBe('approved')
        ->and($profile->verification_status)->toBe('verified')
        ->and($profile->truckTypes()->count())->toBe(1);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    expect($profile->refresh()->status)->toBe('approved');
});

it('leaves the retired onboarding state alone', function () {
    // The wizard columns are gone. Changing capabilities must not try to write
    // them back or depend on them existing.
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', capabilityPayload($user, [
            'posts_freight' => true,
            'provides_transport' => true,
        ]))
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect($profile)->not->toBeNull()
        ->and($profile->provides_transport)->toBeTrue();
});

it('requires authentication', function () {
    $this->patch('/public-profile', [
        'display_name' => 'Someone',
        'account_type' => 'personal',
        'posts_freight' => true,
        'provides_transport' => false,
    ])->assertRedirect(route('login'));
});

// ── the wizard is retired ──────────────────────────────────────────────────

it('sends every user from onboarding to the public profile page', function () {
    $user = onboardedUser();

    $this->actingAs($user)
        ->get('/onboarding')
        ->assertRedirect('/public-profile');
});

it('redirects a user who has not onboarded to the public profile page too', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->forceFill([
        'current_workspace_id' => $workspace->id,
        'onboarded_at' => null,
    ])->save();

    $this->actingAs($user)
        ->get('/onboarding')
        ->assertRedirect('/public-profile');
});

// ── one source of truth for readiness ──────────────────────────────────────

it('reports readiness for each capability independently', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => 'Ready Identity',
        'phone' => '+251911000011',
    ]);

    $state = $readiness->for($user);

    // Identity is complete, so posting is unblocked. Carrying additionally needs
    // a base, and reporting one figure for both is how somebody is shown as ready
    // and then refused at the gate.
    expect($state['ready']['posts_freight'])->toBeTrue()
        ->and($state['ready']['provides_transport'])->toBeFalse()
        ->and($state['missing']['provides_transport'])->toContain('primary_location_id');
});

it('reports nothing missing for a capability the user does not hold', function () {
    $readiness = app(MarketplaceReadiness::class);
    // A consignor must not be told to add a base location for a capability they
    // have not picked up.
    $user = onboardedUser(['posts_freight' => true, 'provides_transport' => false, 'phone' => '']);

    $state = $readiness->for($user);

    expect($state['missing']['provides_transport'])->toBe([])
        ->and($state['missing']['posts_freight'])->toContain('phone');
});

it('names the base location when a carrier has not set one', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = onboardedUser(['provides_transport' => true]);

    expect($readiness->for($user)['missing']['provides_transport'])
        ->toContain('primary_location_id');
});

it('reports a carrier with no profile at all as not ready', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = User::factory()->create();

    expect($readiness->for($user)['ready']['provides_transport'])->toBeFalse();
});

it('handles a user with no profile row without throwing', function () {
    $readiness = app(MarketplaceReadiness::class);
    // Every new user hits a page that renders readiness before they have ever
    // opened onboarding. An exception here would be the first thing they see.
    $user = User::factory()->create();

    $state = $readiness->for($user);

    expect($state['acts_in_marketplace'])->toBeFalse()
        ->and($state['capabilities']['posts_freight'])->toBeFalse();
});

it('treats a whitespace phone as missing', function () {
    $readiness = app(MarketplaceReadiness::class);
    // A field holding spaces passes a null check while showing an empty line to
    // whoever is reading it.
    $user = onboardedUser(['posts_freight' => true, 'display_name' => '   ', 'phone' => '  ']);

    expect($readiness->for($user)['missing']['posts_freight'])
        ->toContain('display_name', 'phone');
});

it('lists a shared field once when both capabilities need it', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => '',
        'phone' => '',
    ]);

    $missing = $readiness->for($user)['missing']['provides_transport'];

    // "display_name, display_name" reads like a bug report.
    expect(array_count_values($missing)['display_name'])->toBe(1)
        ->and(array_count_values($missing)['phone'])->toBe(1);
});

it('serves the page the same profile the service reads', function () {
    // The page used to recompute readiness from its own copy of the fields, which
    // is how a screen and a gate end up disagreeing. It now renders the saved
    // profile, and the service judges that same record.
    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => '',
        'phone' => '',
    ]);

    $props = $this->actingAs($user)
        ->get('/public-profile')
        ->viewData('page')['props'];

    expect($props['profile']['display_name'])->toBe('')
        ->and(app(MarketplaceReadiness::class)->for($user)['missing']['posts_freight'])
        ->toContain('display_name', 'phone');
});
