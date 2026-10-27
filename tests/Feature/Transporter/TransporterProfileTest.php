<?php

use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The carrier profile: creating it, editing it, and what the public sees.
 *
 * There was no test file for any of this. TransporterDocumentAccessTest covers
 * downloading documents and CapabilityChangeTest covers switching the capability
 * on, but nothing covered the profile itself — the form a carrier fills in, the
 * status it lands in, whether it can be edited afterwards, or whether the phone
 * number a carrier publishes is the one anybody can actually see.
 *
 * That last one is why this file exists. The visibility flags were being written
 * to transporter_profiles while ContactRevealService read them from
 * public_profiles, and no test failed for three commits because the two were
 * never exercised together.
 */
uses(RefreshDatabase::class);

function carrierUser(array $profileOverrides = []): User
{
    $user = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(
        ['user_id' => $user->id],
        array_merge([
            'display_name' => 'Adama Haulage',
            'phone' => '+251911000001',
            'primary_location_id' => Location::factory()->create()->id,
            'posts_freight' => false,
            'provides_transport' => true,
            'onboarding_step' => 'completed',
            'onboarding_completed_at' => now(),
        ], $profileOverrides),
    );

    return $user->refresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function carrierForm(array $overrides = []): array
{
    return array_merge([
        'display_name' => 'Adama Haulage',
        'legal_name' => 'Adama Haulage PLC',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => Location::factory()->create([
            'name' => 'Adama',
            'latitude' => 8.54,
            'longitude' => 39.27,
        ])->id,
        'phone_visible' => '1',
        'email_visible' => '1',
    ], $overrides);
}

// ── creating ───────────────────────────────────────────────────────────────

it('creates a carrier profile in pending', function () {
    $user = carrierUser();

    $this->actingAs($user)
        ->post('/my-transporter-profile', carrierForm())
        ->assertRedirect(route('transporter.profile.index'));

    $carrier = $user->transporterProfile()->firstOrFail();

    // Pending, not approved. This is the status that used to be a wall in front
    // of expressing interest and is now only a badge.
    expect($carrier->status)->toBe('pending')
        ->and($carrier->display_name)->toBe('Adama Haulage')
        ->and($carrier->slug)->not->toBeEmpty();
});

it('gives a carrier profile a slug without being asked', function () {
    // The column is NOT NULL and unique and the logic lived in a controller, so
    // any other creation path hit an integrity violation.
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    expect($user->transporterProfile()->firstOrFail()->slug)->toStartWith('adama-haulage-');
});

it('refuses a second carrier profile', function () {
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    $this->actingAs($user)
        ->post('/my-transporter-profile', carrierForm(['display_name' => 'Second Attempt']))
        ->assertSessionHasErrors();

    // The first one is untouched rather than replaced.
    expect($user->transporterProfile()->count())->toBe(1)
        ->and($user->transporterProfile()->first()->display_name)->toBe('Adama Haulage');
});

it('requires a display name and a base location', function () {
    $user = carrierUser();

    $this->actingAs($user)
        ->post('/my-transporter-profile', ['display_name' => '', 'primary_location_id' => ''])
        ->assertSessionHasErrors(['display_name', 'primary_location_id']);
});

it('rejects a phone number that is not a phone number', function () {
    $user = carrierUser();

    $this->actingAs($user)
        ->post('/my-transporter-profile', carrierForm(['phone' => 'ring me']))
        ->assertSessionHasErrors('phone');
});

it('rejects a base location that does not exist', function () {
    $user = carrierUser();

    $this->actingAs($user)
        ->post('/my-transporter-profile', carrierForm(['primary_location_id' => 999999]))
        ->assertSessionHasErrors('primary_location_id');
});

// ── editing ────────────────────────────────────────────────────────────────

it('edits a pending carrier profile', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    $this->actingAs($user)
        ->put('/my-transporter-profile', carrierForm(['display_name' => 'Adama Transport Ltd']))
        ->assertRedirect();

    expect($user->transporterProfile()->first()->display_name)->toBe('Adama Transport Ltd');
});

it('refuses to edit a profile that is no longer a draft', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    $user->transporterProfile()->first()->update(['status' => 'approved']);

    $this->actingAs($user)
        ->put('/my-transporter-profile', carrierForm(['display_name' => 'Changed After Approval']))
        ->assertSessionHasErrors('display_name');

    expect($user->transporterProfile()->first()->display_name)->toBe('Adama Haulage');
});

it('will not update when there is nothing to update', function () {
    $user = carrierUser();

    $this->actingAs($user)
        ->put('/my-transporter-profile', carrierForm())
        ->assertNotFound();
});

it('cannot be edited by somebody else', function () {
    $owner = carrierUser();
    $this->actingAs($owner)->post('/my-transporter-profile', carrierForm());

    $intruder = carrierUser();

    $this->actingAs($intruder)
        ->put('/my-transporter-profile', carrierForm(['display_name' => 'Hijacked']))
        ->assertNotFound();

    expect($owner->transporterProfile()->first()->display_name)->toBe('Adama Haulage');
});

it('requires authentication', function () {
    $this->post('/my-transporter-profile', carrierForm())->assertRedirect(route('login'));
    $this->get('/my-transporter-profile')->assertRedirect(route('login'));
});

// ── the visibility flags, which is where the two records drifted apart ─────

it('records the visibility choice on the profile, not only on the carrier', function () {
    // ContactRevealService reads these from public_profiles. Writing them only to
    // transporter_profiles meant a carrier who ticked the box still had their
    // number withheld, with nothing in the database recording that they agreed.
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm([
        'phone_visible' => '0',
        'email_visible' => '0',
    ]));

    $identity = Profile::where('user_id', $user->id)->first();

    expect((bool) $identity->phone_visible)->toBeFalse()
        ->and((bool) $identity->email_visible)->toBeFalse();
});

it('records an unticked box as a refusal rather than leaving it alone', function () {
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm(['phone_visible' => '1']));

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeTrue();

    $this->actingAs($user)->put('/my-transporter-profile', carrierForm(['phone_visible' => '0']));

    expect((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeFalse();
});

it('keeps the carrier copy in step with the profile', function () {
    // Both records carry the flags until the duplicated columns are dropped.
    // Reading one and writing the other is how the public page and the reveal
    // disagree about whether a number is allowed out.
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm(['phone_visible' => '0']));

    expect((bool) $user->transporterProfile()->first()->phone_visible)->toBeFalse()
        ->and((bool) Profile::where('user_id', $user->id)->value('phone_visible'))->toBeFalse();
});

// ── completeness ───────────────────────────────────────────────────────────

it('reports a completed carrier profile as complete', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    $carrier = $user->transporterProfile()->firstOrFail();

    expect($carrier->isComplete())->toBeTrue()
        ->and($carrier->missingFields())->toBe([])
        ->and(app(MarketplaceReadiness::class)->for($user)['ready']['provides_transport'])->toBeTrue();
});

it('names the field that is missing', function () {
    // phone is nullable in the form. A carrier can save without one and must then
    // be told exactly that, rather than being left unable to express interest
    // with no idea why.
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm(['phone' => null]));

    expect($user->transporterProfile()->firstOrFail()->missingFields())->toContain('phone');
});

it('treats a blank phone as missing', function () {
    $user = carrierUser();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm(['phone' => '   ']));

    expect($user->transporterProfile()->firstOrFail()->missingFields())->toContain('phone');
});

// ── the public directory ───────────────────────────────────────────────────

it('does not list a carrier whose profile is still pending', function () {
    // Deliberate, and worth pinning. Every new carrier is pending until an admin
    // approves, so a directory that showed pending carriers would put unvetted
    // businesses in front of counterparties the moment they filled in a form.
    //
    // The public directory reads the consolidated profile now. The legacy
    // carrier form still exists until the old surfaces are retired, so this
    // test keeps exercising it while asserting the new public surface.
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());

    $user->profile()->update(['status' => 'pending']);

    $this->actingAs(carrierUser())->get('/transporters')->assertOk()->assertDontSee('Adama Haulage');

    $this->actingAs(carrierUser())
        ->get('/p/'.$user->profile()->firstOrFail()->slug)
        ->assertNotFound();
});

it('lists a carrier once approved', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());
    $user->profile()->update(['status' => 'approved']);

    $this->actingAs(carrierUser())->get('/transporters')->assertOk()->assertSee('Adama Haulage');
});

it('shows a carrier public page by slug once approved', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());
    $profile = $user->profile()->firstOrFail();
    $profile->update(['status' => 'approved']);

    $this->actingAs(carrierUser())->get("/p/{$profile->slug}")->assertOk()->assertSee('Adama Haulage');
});

it('withholds a hidden phone number from the public directory', function () {
    // The number is not merely absent from the payload — the value must not
    // appear anywhere in the serialised page, because it could equally have been
    // carried under a different key.
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm([
        'phone_visible' => '0',
        'email_visible' => '0',
    ]));
    $user->profile()->update([
        'status' => 'approved',
        'phone_visible' => false,
        'email_visible' => false,
    ]);

    // Browsed by somebody else, with a different number of their own. A user's
    // own phone is in the shared props of every page they load, and this helper
    // defaults every user to the same number — so browsing as another carrier
    // would match on the viewer's own data and pass whether or not the directory
    // leaked anything.
    $response = $this->actingAs(carrierUser(['phone' => '+251999999999']))
        ->get('/transporters');

    $response->assertOk()->assertSee('Adama Haulage');

    expect($response->getContent())->not->toContain('+251911000001')
        ->and($response->getContent())->not->toContain('carrier@example.test');
});

it('never shows the phone number on the public page, even when the carrier agreed', function () {
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm());
    $profile = $user->profile()->firstOrFail();
    $profile->update(['status' => 'approved', 'phone_visible' => true]);

    // The viewer needs a different number of their own: shared props carry it,
    // and the target number would otherwise match the viewer's data.
    $this->actingAs(carrierUser(['phone' => '+251999999999']))
        ->get("/p/{$profile->slug}")
        ->assertOk()
        ->assertDontSee('+251911000001');
});

it('still shows the name when the number is hidden', function () {
    // A carrier who declines publication has still chosen to trade. Hiding the
    // number is not withdrawing from the directory.
    $user = carrierUser();
    $this->actingAs($user)->post('/my-transporter-profile', carrierForm([
        'phone_visible' => '0',
    ]));
    $user->profile()->update(['status' => 'approved', 'phone_visible' => false]);

    $this->actingAs(carrierUser())->get('/transporters')->assertOk()->assertSee('Adama Haulage');
});

// ── the records carry their history ────────────────────────────────────────

it('keeps trucks and routes against the profile', function () {
    $user = carrierUser();

    $origin = Location::factory()->create(['name' => 'Adama']);
    $destination = Location::factory()->create(['name' => 'Awash']);
    $truck = TruckType::factory()->create();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm([
        'truck_type_ids' => [$truck->id],
        'routes' => [[
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
        ]],
    ]));

    $carrier = $user->transporterProfile()->firstOrFail();

    expect($carrier->truckTypes)->toHaveCount(1)
        ->and($carrier->routes)->toHaveCount(1);
});

it('replaces rather than accumulates trucks on a second edit', function () {
    $user = carrierUser();

    $first = TruckType::factory()->create();
    $second = TruckType::factory()->create();

    $this->actingAs($user)->post('/my-transporter-profile', carrierForm([
        'truck_type_ids' => [$first->id, $second->id],
    ]));

    expect($user->transporterProfile()->first()->truckTypes)->toHaveCount(2);

    $this->actingAs($user)->put('/my-transporter-profile', carrierForm([
        'truck_type_ids' => [$second->id],
    ]));

    expect($user->transporterProfile()->first()->truckTypes)->toHaveCount(1);
});

it('generates a distinct slug when two carriers share a name', function () {
    $first = carrierUser();
    $this->actingAs($first)->post('/my-transporter-profile', carrierForm());

    $second = carrierUser();
    $this->actingAs($second)->post('/my-transporter-profile', carrierForm());

    $slugs = TransporterProfile::pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});
