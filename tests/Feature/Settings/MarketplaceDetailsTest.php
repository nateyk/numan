<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * /settings/marketplace-details — the person as a counterparty sees them.
 *
 * This page exists because those fields had nowhere else to go. display_name,
 * phone and primary_location_id were only reachable through the transporter
 * form, so a consignor — who has every one of those fields and none of the
 * transporter-specific ones — could not set a single one of them.
 */
uses(RefreshDatabase::class);

function detailsUser(array $profileOverrides = []): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(
        ['user_id' => $user->id],
        array_merge(['display_name' => 'Someone', 'onboarding_step' => 'completed', 'onboarding_completed_at' => now()], $profileOverrides),
    );

    return $user->refresh();
}

it('loads for a user who has never touched the marketplace', function () {
    // No profile row at all, and no capabilities. The page creates the row on
    // demand rather than bouncing them to onboarding first.
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $this->actingAs($user)
        ->get('/settings/marketplace-details')
        ->assertOk();

    expect(Profile::where('user_id', $user->id)->exists())->toBeTrue();
});

it('saves the details onto the profile', function () {
    $user = detailsUser();
    $location = Location::factory()->create(['name' => 'Adama']);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Adama Haulage',
            'phone' => '+251911000001',
            'primary_location_id' => $location->id,
            'phone_visible' => '1',
            'email_visible' => '1',
        ])
        ->assertRedirect(route('settings.marketplace-details.edit'));

    $profile = Profile::where('user_id', $user->id)->first();

    expect($profile->display_name)->toBe('Adama Haulage')
        ->and($profile->phone)->toBe('+251911000001')
        ->and($profile->primary_location_id)->toBe($location->id)
        ->and((bool) $profile->phone_visible)->toBeTrue();
});

it('saves a WhatsApp number separately from the phone', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'WhatsApp Consignor',
            'phone' => '+251911000006',
            'whatsapp' => '+251922000007',
            'phone_visible' => '1',
            'whatsapp_visible' => '1',
        ])
        ->assertRedirect(route('settings.marketplace-details.edit'));

    expect(Profile::where('user_id', $user->id)->value('whatsapp'))
        ->toBe('+251922000007');

    $this->actingAs($user)
        ->get('/settings/marketplace-details')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profile.whatsapp', '+251922000007')
        );
});

it('rejects a WhatsApp number that is not a phone number', function () {
    $user = detailsUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Someone',
            'phone' => '+251911000007',
            'whatsapp' => 'message me maybe',
        ])
        ->assertSessionHasErrors('whatsapp');
});

it('treats an absent checkbox as a refusal, not as consent', function () {
    // A checkbox that is not ticked is not submitted at all. Reading absence as
    // "keep the default of true" would mean clearing the box and pressing save
    // republished a number the user had deliberately hidden.
    $user = detailsUser(['phone_visible' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Someone',
            'phone' => '+251911000001',
            // phone_visible deliberately omitted
        ])
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeFalse();
});

it('keeps a phone hidden when the user saves an unrelated field', function () {
    $user = detailsUser(['phone_visible' => false]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Renamed',
            'phone' => '+251911000002',
            'phone_visible' => '1',
        ])
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeTrue();
});

// ── the required-fields rule follows the capability, not the page ───────────

it('demands a name and phone from somebody who posts freight', function () {
    $user = detailsUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', ['display_name' => '', 'phone' => ''])
        ->assertSessionHasErrors(['display_name', 'phone']);
});

it('demands a name and phone from somebody who carries freight', function () {
    $user = detailsUser(['provides_transport' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', ['display_name' => '', 'phone' => ''])
        ->assertSessionHasErrors(['display_name', 'phone']);
});

it('asks for neither from somebody who does neither', function () {
    // Not reaching this form to trade, so making them invent a phone number they
    // will never publish is friction for nobody's benefit.
    $user = detailsUser(['posts_freight' => false, 'provides_transport' => false]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', ['display_name' => '', 'phone' => ''])
        ->assertSessionHasNoErrors();
});

it('rejects a phone number that is not a phone number', function () {
    $user = detailsUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Someone',
            'phone' => 'call me maybe',
        ])
        ->assertSessionHasErrors('phone');
});

it('rejects a location that does not exist', function () {
    $user = detailsUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => 'Someone',
            'phone' => '+251911000003',
            'primary_location_id' => 999999,
        ])
        ->assertSessionHasErrors('primary_location_id');
});

// ── readiness is reported per capability ───────────────────────────────────

it('reports posting as blocked by the identity fields only', function () {
    $user = detailsUser(['posts_freight' => true, 'display_name' => '', 'phone' => '']);

    $response = $this->actingAs($user)->get('/settings/marketplace-details');

    $response->assertOk();
    expect($response->viewData('page')['props']['missingFields']['posts_freight'])
        ->toContain('display_name', 'phone');
});

it('reports carrying as needing the base location too', function () {
    // A base location is part of what carrying needs and posting does not. The
    // legacy carrier row is no longer consulted, so leaving it unset changes
    // nothing.
    $user = detailsUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => 'Ready Identity',
        'phone' => '+251911000004',
    ]);

    TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'not-ready-'.uniqid(),
        'display_name' => 'Ready Identity',
        'phone' => '+251911000004',
        'status' => 'pending',
        // primary_location_id left unset
    ]);

    $response = $this->actingAs($user)->get('/settings/marketplace-details');

    $response->assertOk();

    $missing = $response->viewData('page')['props']['missingFields'];

    expect($missing['posts_freight'])->toBe([])
        ->and($missing['provides_transport'])->toContain('primary_location_id');
});

it('reports both capabilities as ready once everything is filled in', function () {
    $base = Location::factory()->create();

    $user = detailsUser([
        'posts_freight' => true,
        'provides_transport' => true,
        'display_name' => 'Fully Ready',
        'phone' => '+251911000005',
        'primary_location_id' => $base->id,
    ]);

    TransporterProfile::create([
        'user_id' => $user->id,
        'slug' => 'ready-'.uniqid(),
        'display_name' => 'Fully Ready',
        'phone' => '+251911000005',
        'status' => 'pending',
        'primary_location_id' => $base->id,
    ]);

    $response = $this->actingAs($user)->get('/settings/marketplace-details');

    $missing = $response->viewData('page')['props']['missingFields'];

    expect($missing['posts_freight'])->toBe([])
        ->and($missing['provides_transport'])->toBe([]);
});

it('does not let a crafted request skip the required fields', function () {
    // The capability check reads the database, not the submitted payload, so a
    // POST cannot talk its way into the "does neither" branch.
    $user = detailsUser(['posts_freight' => true]);

    $this->actingAs($user)
        ->patch('/settings/marketplace-details', [
            'display_name' => '',
            'phone' => '',
            'posts_freight' => '0',
            'provides_transport' => '0',
        ])
        ->assertSessionHasErrors(['display_name', 'phone']);
});
