<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The person as a counterparty sees them.
 *
 * These fields used to live at /settings/marketplace-details because they had
 * nowhere else to go. They now live on the single public profile, and the old
 * URL only forwards there.
 */
uses(RefreshDatabase::class);

function detailsUser(array $profileOverrides = []): User
{
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::factory()->for($user)->consignor()->create(array_merge([
        'account_type' => 'personal',
        'display_name' => 'Someone',
        'phone' => '+251911000001',
        'primary_location_id' => null,
    ], $profileOverrides));

    return $user->refresh();
}

/**
 * The minimum a full profile save must carry.
 *
 * @return array<string, mixed>
 */
function detailsPayload(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Someone',
        'account_type' => 'personal',
        'posts_freight' => true,
        'provides_transport' => false,
    ], $overrides);
}

it('redirects the old marketplace details URL to the public profile page', function () {
    $user = detailsUser();

    $this->actingAs($user)->get('/settings/marketplace-details')->assertRedirect('/public-profile');
    $this->actingAs($user)->patch('/settings/marketplace-details')->assertRedirect('/public-profile');
});

it('saves the details onto the profile', function () {
    $user = detailsUser();
    $location = Location::factory()->create(['name' => 'Adama']);

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'display_name' => 'Adama Haulage',
            'phone' => '+251911000001',
            'primary_location_id' => $location->id,
            'phone_visible' => true,
            'email_visible' => true,
        ]))
        ->assertRedirect();

    $profile = Profile::where('user_id', $user->id)->first();

    expect($profile->display_name)->toBe('Adama Haulage')
        ->and($profile->phone)->toBe('+251911000001')
        ->and($profile->primary_location_id)->toBe($location->id)
        ->and((bool) $profile->phone_visible)->toBeTrue();
});

it('saves a WhatsApp number separately from the phone', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'display_name' => 'WhatsApp Consignor',
            'phone' => '+251911000006',
            'whatsapp' => '+251922000007',
            'phone_visible' => true,
            'whatsapp_visible' => true,
        ]))
        ->assertRedirect();

    expect(Profile::where('user_id', $user->id)->value('whatsapp'))
        ->toBe('+251922000007');

    $this->actingAs($user)
        ->get('/public-profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profile.whatsapp', '+251922000007')
        );
});

it('rejects a WhatsApp number that is not a phone number', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'phone' => '+251911000007',
            'whatsapp' => 'message me maybe',
        ]))
        ->assertSessionHasErrors('whatsapp');
});

it('hides a number when its visibility flag is off', function () {
    // The page submits every visibility flag, so an explicit false is a refusal
    // and must be persisted as one.
    $user = detailsUser(['phone_visible' => true]);

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'phone' => '+251911000001',
            'phone_visible' => false,
        ]))
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeFalse();
});

it('publishes a number when its visibility flag is on', function () {
    $user = detailsUser(['phone_visible' => false]);

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'display_name' => 'Renamed',
            'phone' => '+251911000002',
            'phone_visible' => true,
        ]))
        ->assertRedirect();

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeTrue();
});

// ── validation belongs to the profile, not to one page ─────────────────────

it('demands a display name', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload(['display_name' => '']))
        ->assertSessionHasErrors('display_name');
});

it('allows a missing phone to be saved because readiness, not the save, gates action', function () {
    // A phone is required to post or carry, but not to save a draft profile. The
    // gate that refuses the action names the missing field; the save path must
    // not make the page impossible to complete in sections.
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload(['phone' => '']))
        ->assertSessionHasNoErrors();

    expect(Profile::where('user_id', $user->id)->value('phone'))->toBeNull();
});

it('rejects a phone number that is not a phone number', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload(['phone' => 'call me maybe']))
        ->assertSessionHasErrors('phone');
});

it('rejects a location that does not exist', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'phone' => '+251911000003',
            'primary_location_id' => 999999,
        ]))
        ->assertSessionHasErrors('primary_location_id');
});

it('refuses a profile with no capability', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', detailsPayload([
            'posts_freight' => false,
            'provides_transport' => false,
        ]))
        ->assertSessionHasErrors('capabilities');
});

it('requires both capability booleans', function () {
    $user = detailsUser();

    $this->actingAs($user)
        ->patch('/public-profile', [
            'display_name' => 'Someone',
            'account_type' => 'personal',
        ])
        ->assertSessionHasErrors(['posts_freight', 'provides_transport']);
});
