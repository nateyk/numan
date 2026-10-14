<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Changing what you do in the marketplace, after the fact.
 *
 * OnboardingController refuses every action once a user is onboarded — correct
 * for a wizard, wrong for the capability decision. It left the choice permanent,
 * so somebody who registered to post a load and later needed to carry one had no
 * way to say so. That is what these pin.
 */
uses(RefreshDatabase::class);

function onboardedUser(array $profileOverrides = []): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(
        ['user_id' => $user->id],
        array_merge([
            'display_name' => 'Someone',
            'phone' => '+251911000001',
            'posts_freight' => false,
            'provides_transport' => false,
            'onboarding_step' => 'completed',
            'onboarding_completed_at' => now(),
        ], $profileOverrides),
    );

    return $user->refresh();
}

// ── the decision is revisitable ─────────────────────────────────────────────

it('lets an onboarded consignor start carrying freight', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect((bool) $profile->posts_freight)->toBeTrue()
        ->and((bool) $profile->provides_transport)->toBeTrue();
});

it('lets an onboarded user hold both capabilities at once', function () {
    // The either/or model could not express this, which is what the original
    // "either/or" account_type column encoded.
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('provides_transport'))->toBeTrue();
});

it('lets a user turn one capability off without losing the other', function () {
    $user = onboardedUser(['posts_freight' => true, 'provides_transport' => true]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '0', 'provides_transport' => '1'])
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect((bool) $profile->posts_freight)->toBeFalse()
        ->and((bool) $profile->provides_transport)->toBeTrue();
});

it('refuses to leave a user with neither capability', function () {
    // Both booleans are submitted so one can be turned off while the other stays
    // on. Neither is a meaningful state: a user with no capability cannot act,
    // and a profile that reads as ready while being inert is worse than one that
    // admits it.
    $user = onboardedUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '0', 'provides_transport' => '0'])
        ->assertSessionHasErrors('posts_freight');

    expect((bool) Profile::where('user_id', $user->id)->value('posts_freight'))->toBeTrue();
});

it('creates an empty carrier profile when someone starts carrying', function () {
    // Without one the readiness check reports "transporter_profile" missing,
    // which is accurate but tells them nothing actionable. An empty profile turns
    // it into three ordinary fields they can fill in.
    $user = onboardedUser(['posts_freight' => true]);

    expect($user->transporterProfile()->exists())->toBeFalse();

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    $this->assertDatabaseHas('transporter_profiles', ['user_id' => $user->id]);
});

it('does not overwrite an existing carrier profile', function () {
    $user = onboardedUser(['posts_freight' => true]);

    $existing = TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'existing-'.uniqid(),
        'display_name' => 'Existing Carrier',
        'phone' => '+251911000009',
        'business_description' => 'Ten years on the Addis–Adama run',
        'primary_location_id' => Location::factory()->create()->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    $after = $user->transporterProfile()->first();

    expect($after->id)->toBe($existing->id)
        ->and($after->business_description)->toBe('Ten years on the Addis–Adama run');
});

it('keeps the carrier profile when the capability is turned off', function () {
    // Turning off a checkbox must not destroy trucks, routes and uploaded
    // documents. The user may switch back tomorrow.
    $user = onboardedUser(['posts_freight' => true, 'provides_transport' => true]);

    $existing = TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'keep-me-'.uniqid(),
        'display_name' => 'Existing Carrier',
        'phone' => '+251911000010',
        'primary_location_id' => Location::factory()->create()->id,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '0'])
        ->assertRedirect();

    $this->assertDatabaseHas('transporter_profiles', ['id' => $existing->id]);
});

it('does not disturb the onboarding wizard state', function () {
    // The guard on OnboardingController exists so a finished wizard cannot be
    // re-run. Writing capabilities must not reopen that door.
    $user = onboardedUser(['posts_freight' => true, 'onboarding_step' => 'completed']);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect($profile->onboarding_step)->toBe('completed')
        ->and($profile->onboarding_completed_at)->not->toBeNull();
});

it('requires authentication', function () {
    $this->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '0'])
        ->assertRedirect(route('login'));
});

// ── an onboarded user is sent somewhere they can actually act ──────────────

it('sends an onboarded user away from the wizard to the revisitable page', function () {
    // Every action on the wizard refuses once onboarded, so landing them there
    // gave them a page with no controls on it.
    $user = onboardedUser();

    $this->actingAs($user)
        ->get('/onboarding')
        ->assertRedirect(route('settings.marketplace-details.edit'));
});

it('still shows the wizard to somebody who has not onboarded', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    // Creating a workspace sets this, and OnboardingController checks it first.
    $user->forceFill([
        'current_workspace_id' => $workspace->id,
        'onboarded_at' => null,
    ])->save();

    $this->actingAs($user)
        ->get('/onboarding')
        ->assertOk();
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

    TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'partial-'.uniqid(),
        'display_name' => 'Ready Identity',
        'phone' => '+251911000011',
        'status' => 'pending',
    ]);

    $state = $readiness->for($user);

    // Identity is complete, so posting is unblocked. Carrying additionally needs
    // the transporter fields, and reporting one figure for both is how somebody
    // is shown as ready and then refused at the gate.
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

it('says transporter_profile when a carrier has no profile at all', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = onboardedUser(['provides_transport' => true]);

    expect($readiness->for($user)['missing']['provides_transport'])
        ->toContain('transporter_profile');
});

it('reports a carrier with no profile at all as not ready', function () {
    $readiness = app(MarketplaceReadiness::class);
    $user = onboardedUser(['provides_transport' => true]);

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
    // display_name and phone are NOT NULL, so a profile can only be incomplete by
    // being blank — and a field holding spaces passes a null check while showing
    // an empty line to whoever is reading it.
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

    // "display_name, display_name, transporter_profile" reads like a bug report.
    expect(array_count_values($missing)['display_name'])->toBe(1)
        ->and(array_count_values($missing)['phone'])->toBe(1);
});

it('serves the same verdicts to the page as the service computes', function () {
    // The page used to recompute readiness from missingFields, which is how a
    // screen and a gate end up disagreeing. Identity is blank so posting is
    // blocked on identity alone, while carrying is additionally blocked on the
    // carrier profile that does not exist.
    $user = onboardedUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => '',
        'phone' => '',
    ]);

    $props = $this->actingAs($user)
        ->get('/settings/marketplace-details')
        ->viewData('page')['props'];

    expect($props['ready']['posts_freight'])->toBeFalse()
        ->and($props['ready']['provides_transport'])->toBeFalse()
        ->and($props['missingFields']['posts_freight'])->toContain('display_name', 'phone')
        ->and($props['missingFields']['provides_transport'])
        ->toContain('transporter_profile');
});
