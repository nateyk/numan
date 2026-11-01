<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The carrier half of the public profile: creating it, editing it, and what
 * the public sees.
 *
 * A carrier is not a separate record any more. The same profile that posts
 * freight grows a transport section, and every carrier field has exactly one
 * home. These tests follow that record from the first identity save, through
 * the review queue, to the public directory and its hidden contact details.
 */
uses(RefreshDatabase::class);

function carrierUser(array $profileOverrides = []): User
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::factory()->for($user)->transporter()->create(array_merge([
        'display_name' => 'Adama Haulage',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => Location::factory()->create()->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'status' => 'draft',
    ], $profileOverrides));

    return $user->refresh();
}

/**
 * The identity half of a carrier profile save.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function carrierForm(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Adama Haulage',
        'account_type' => 'personal',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => Location::factory()->create([
            'name' => 'Adama',
            'latitude' => 8.54,
            'longitude' => 39.27,
        ])->id,
        'phone_visible' => true,
        'email_visible' => true,
        'posts_freight' => false,
        'provides_transport' => true,
    ], $overrides);
}

// ── creating and submitting ────────────────────────────────────────────────

it('creates a transport profile and submits it for review', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm())
        ->assertRedirect();

    $profile = $user->profile()->firstOrFail();

    // Saving the identity opens a draft listing; the carrier section is what
    // puts it in the review queue.
    expect(Profile::where('user_id', $user->id)->count())->toBe(1)
        ->and($profile->provides_transport)->toBeTrue()
        ->and($profile->status)->toBe('draft')
        ->and($profile->slug)->not->toBeEmpty();

    $this->actingAs($user)
        ->put('/public-profile/transport', [
            'truck_type_ids' => [TruckType::factory()->create()->id],
        ])
        ->assertRedirect();

    expect($profile->refresh()->status)->toBe('pending');
});

it('gives a new profile a slug without being asked', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/public-profile', carrierForm())->assertRedirect();

    expect($user->profile()->firstOrFail()->slug)->toStartWith('adama-haulage-');
});

it('updates the same profile instead of creating a second one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/public-profile', carrierForm())->assertRedirect();

    $slug = $user->profile()->firstOrFail()->slug;

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['display_name' => 'Adama Transport Ltd']))
        ->assertRedirect();

    expect(Profile::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->profile()->firstOrFail()->display_name)->toBe('Adama Transport Ltd')
        ->and($user->profile()->firstOrFail()->slug)->toBe($slug);
});

it('requires a display name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['display_name' => '']))
        ->assertSessionHasErrors('display_name');
});

it('rejects a phone number that is not a phone number', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['phone' => 'ring me']))
        ->assertSessionHasErrors('phone');
});

it('rejects a base location that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['primary_location_id' => 999999]))
        ->assertSessionHasErrors('primary_location_id');
});

// ── editing ────────────────────────────────────────────────────────────────

it('edits a pending carrier profile', function () {
    $user = carrierUser();
    $this->actingAs($user)->put('/public-profile/transport', ['truck_type_ids' => []])->assertRedirect();

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['display_name' => 'Adama Transport Ltd']))
        ->assertRedirect();

    $profile = $user->profile()->firstOrFail();

    expect($profile->display_name)->toBe('Adama Transport Ltd')
        ->and($profile->status)->toBe('pending');
});

it('lets an approved carrier edit their identity without losing approval', function () {
    $user = carrierUser(['status' => 'approved']);

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['display_name' => 'Adama Transport Ltd']))
        ->assertRedirect();

    $profile = $user->profile()->firstOrFail();

    expect($profile->display_name)->toBe('Adama Transport Ltd')
        ->and($profile->status)->toBe('approved');
});

it('cannot edit somebody else profile', function () {
    $owner = carrierUser(['display_name' => 'Owner Haulage']);
    $intruder = carrierUser(['display_name' => 'Intruder Haulage']);

    $this->actingAs($intruder)
        ->patch('/public-profile', carrierForm(['display_name' => 'Hijacked']))
        ->assertRedirect();

    expect($owner->profile()->firstOrFail()->display_name)->toBe('Owner Haulage')
        ->and($intruder->profile()->firstOrFail()->display_name)->toBe('Hijacked');
});

it('requires authentication', function () {
    $this->patch('/public-profile', carrierForm())->assertRedirect(route('login'));
    $this->get('/public-profile')->assertRedirect(route('login'));
});

// ── the visibility flags ──────────────────────────────────────────────────

it('records the visibility choice on the profile', function () {
    $user = carrierUser(['phone_visible' => true, 'email_visible' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm([
            'phone_visible' => false,
            'email_visible' => false,
        ]))
        ->assertRedirect();

    $profile = $user->profile()->firstOrFail();

    expect((bool) $profile->phone_visible)->toBeFalse()
        ->and((bool) $profile->email_visible)->toBeFalse();
});

it('records an unticked box as a refusal rather than leaving it alone', function () {
    $user = carrierUser(['phone_visible' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', carrierForm(['phone_visible' => false]))
        ->assertRedirect();

    expect((bool) $user->profile()->firstOrFail()->phone_visible)->toBeFalse();
});

// ── readiness ──────────────────────────────────────────────────────────────

it('reports a completed carrier profile as ready to carry', function () {
    $user = carrierUser();

    expect(app(MarketplaceReadiness::class)->for($user)['ready']['provides_transport'])->toBeTrue();
});

it('names the field that is missing', function () {
    // phone is nullable in the form. A carrier can save without one and must then
    // be told exactly that, rather than being left unable to express interest
    // with no idea why.
    $user = carrierUser(['phone' => null]);

    expect(app(MarketplaceReadiness::class)->for($user)['missing']['provides_transport'])
        ->toContain('phone');
});

it('treats a blank phone as missing', function () {
    $user = carrierUser(['phone' => '   ']);

    expect(app(MarketplaceReadiness::class)->for($user)['missing']['provides_transport'])
        ->toContain('phone');
});

it('needs a base location to be ready to carry', function () {
    $user = carrierUser(['primary_location_id' => null]);

    expect(app(MarketplaceReadiness::class)->for($user)['ready']['provides_transport'])->toBeFalse()
        ->and(app(MarketplaceReadiness::class)->for($user)['missing']['provides_transport'])
        ->toContain('primary_location_id');
});

// ── retired directory and public profile ──────────────────────────────────

it('redirects the old directory while keeping pending profiles private', function () {
    $user = carrierUser(['status' => 'pending', 'display_name' => 'Pending Haulage']);
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    $this->actingAs($viewer)->get('/transporters')->assertRedirect('/freight');

    $this->actingAs($viewer)
        ->get('/p/'.$user->profile()->firstOrFail()->slug)
        ->assertNotFound();
});

it('keeps approved carriers available through their public profile URLs', function () {
    $profile = carrierUser(['status' => 'approved'])->profile()->firstOrFail();
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    $this->actingAs($viewer)
        ->get("/transporters/{$profile->slug}")
        ->assertRedirect("/p/{$profile->slug}");
});

it('shows a carrier public page by slug once approved', function () {
    $profile = carrierUser(['status' => 'approved'])->profile()->firstOrFail();
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    $this->actingAs($viewer)->get("/p/{$profile->slug}")->assertOk()->assertSee('Adama Haulage');
});

it('never shows the phone number on the public page, even when the carrier agreed', function () {
    $profile = carrierUser([
        'status' => 'approved',
        'phone' => '+251911000001',
        'phone_visible' => true,
    ])->profile()->firstOrFail();
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    // The viewer needs a different number of their own: shared props carry it,
    // and the target number would otherwise match the viewer's data.
    $this->actingAs($viewer)
        ->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertDontSee('+251911000001');
});

it('withholds hidden phone and email from an approved public profile', function () {
    $profile = carrierUser([
        'status' => 'approved',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'phone_visible' => false,
        'email_visible' => false,
    ])->profile()->firstOrFail();
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    $response = $this->actingAs($viewer)->get("/p/{$profile->slug}");

    $response->assertOk();
    expect($response->getContent())->not->toContain('+251911000001')
        ->and($response->getContent())->not->toContain('carrier@example.test');
});

it('keeps the carrier name public when contact details are hidden', function () {
    $profile = carrierUser(['status' => 'approved', 'phone_visible' => false])->profile()->firstOrFail();
    $viewer = carrierUser(['display_name' => 'Viewer Haulage', 'phone' => '+251999999999']);

    $this->actingAs($viewer)->get("/p/{$profile->slug}")->assertOk()->assertSee('Adama Haulage');
});

// ── the record carries its history ─────────────────────────────────────────

it('keeps trucks and routes against the profile', function () {
    $user = carrierUser();

    $origin = Location::factory()->create(['name' => 'Adama']);
    $destination = Location::factory()->create(['name' => 'Awash']);
    $truck = TruckType::factory()->create();

    $this->actingAs($user)->put('/public-profile/transport', [
        'truck_type_ids' => [$truck->id],
        'routes' => [[
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
        ]],
    ])->assertRedirect();

    $profile = $user->profile()->firstOrFail();

    expect($profile->truckTypes)->toHaveCount(1)
        ->and($profile->routes)->toHaveCount(1);
});

it('replaces rather than accumulates trucks on a second edit', function () {
    $user = carrierUser();

    $first = TruckType::factory()->create();
    $second = TruckType::factory()->create();

    $this->actingAs($user)
        ->put('/public-profile/transport', ['truck_type_ids' => [$first->id, $second->id]])
        ->assertRedirect();

    expect($user->profile()->firstOrFail()->truckTypes)->toHaveCount(2);

    $this->actingAs($user)
        ->put('/public-profile/transport', ['truck_type_ids' => [$second->id]])
        ->assertRedirect();

    expect($user->profile()->firstOrFail()->truckTypes)->toHaveCount(1);
});

it('generates a distinct slug when two carriers share a name', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)
        ->patch('/public-profile', carrierForm(['display_name' => 'Same Name Haulage']))
        ->assertRedirect();

    $this->actingAs($second)
        ->patch('/public-profile', carrierForm(['display_name' => 'Same Name Haulage']))
        ->assertRedirect();

    $slugs = Profile::pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});
