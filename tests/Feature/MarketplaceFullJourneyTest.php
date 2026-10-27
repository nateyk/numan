<?php

use App\Enums\LegalForm;
use App\Models\CargoType;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TruckType;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\ProfileConsolidation;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * The whole thing, end to end, as a person actually uses it.
 *
 * Every other test here starts from a fixture somebody hand-built. This one
 * starts at registration and never reaches into the database to skip a step,
 * because that is the only way to find the failures that live in the gaps
 * between screens — and those are exactly the failures that do not show up in a
 * suite where every test constructs the state it needs.
 *
 * The journey: register, onboard, become a carrier, post a load, find it,
 * express interest, reveal each other's contact details.
 *
 * This file found the defect that made every reveal on production return null.
 * A consignor and a carrier built by hand both had profile rows; a real pair
 * created through the app did not, and nothing else noticed.
 */
uses(RefreshDatabase::class);

/**
 * Register and start browsing exactly as the app does.
 *
 * There is no setup gate any more: registration creates the workspace, and the
 * person can read the freight board before they have chosen a profile.
 *
 * @return array{0: User, 1: Workspace}
 */
function registerThroughTheApp(array $attributes = []): array
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
    ], $attributes));

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->refresh(), $workspace];
}

/**
 * Save the consolidated public profile the way the page does.
 *
 * @param  array<string, mixed>  $details
 */
function saveProfileThroughTheApp(User $user, array $details = []): Profile
{
    $details = array_merge([
        'display_name' => 'Adama Haulage',
        'account_type' => 'business',
        'phone' => '+251911000001',
        'primary_location_id' => Location::factory()->create([
            'name' => 'Adama',
            'latitude' => 8.54,
            'longitude' => 39.27,
        ])->id,
        'legal_name' => 'Adama Haulage PLC',
        'legal_form' => LegalForm::PrivateLimitedCompany->value,
        'posts_freight' => true,
        'provides_transport' => false,
    ], $details);

    test()
        ->actingAs($user->fresh())
        ->patch('/public-profile', $details)
        ->assertSessionHasNoErrors();

    return $user->refresh()->profile()->firstOrFail();
}

/**
 * Publish a load the way a person publishes one.
 *
 * Five requests: create the draft, route, cargo, schedule, submit. Each has its
 * own required fields, and the wizard refuses to submit an incomplete load — so
 * a factory-built post skips the exact sequence that produces most of what can
 * go wrong. FreightCargoStepTest covers the cargo step alone; this covers the
 * whole thing end to end.
 *
 * Mirrors the shape used by FreightCargoStepTest, which is the one test in the
 * suite that already walked this flow.
 */
function publishALoadThroughTheWizard(User $consignor): FreightPost
{
    $origin = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);
    $destination = Location::factory()->create(['name' => 'Awash', 'latitude' => 8.98, 'longitude' => 40.17]);

    // No body. The draft is created immediately and the first real screen is the
    // route step.
    test()->actingAs($consignor->fresh())
        ->post('/my-freight')
        ->assertRedirect();

    $post = FreightPost::where('user_id', $consignor->id)->latest('id')->firstOrFail();

    expect($post->status)->toBe('draft');

    test()->actingAs($consignor->fresh())
        ->put("/my-freight/{$post->ulid}/route", [
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
        ])
        ->assertSessionHasNoErrors();

    test()->actingAs($consignor->fresh())
        ->put("/my-freight/{$post->ulid}/cargo", [
            'cargo_type_id' => CargoType::factory()->create()->id,
            'item_name' => 'Cement',
            'actual_weight' => 12000,
            'weight_unit' => 'kg',
            'tariff_amount' => 9000,
            'tariff_currency' => 'ETB',
            'required_truck_type_id' => TruckType::factory()->create()->id,
            'truck_count' => 1,
        ])
        ->assertSessionHasNoErrors();

    test()->actingAs($consignor->fresh())
        ->put("/my-freight/{$post->ulid}/schedule", [
            'pickup_date' => now()->addWeek()->toDateString(),
            'schedule_type' => 'one_time',
        ])
        ->assertSessionHasNoErrors();

    test()->actingAs($consignor->fresh())
        ->post("/my-freight/{$post->ulid}/submit")
        ->assertSessionHasNoErrors();

    return $post->refresh();
}

/**
 * A profile-ready consignor, for tests whose subject is the counterparty.
 */
function onboardedConsignor(): User
{
    [$user] = registerThroughTheApp();

    saveProfileThroughTheApp($user, [
        'display_name' => 'Consignor Co',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    return $user->refresh();
}

/**
 * A load already published, for tests about what happens after the posting.
 */
function someonePostedALoad(): FreightPost
{
    $consignor = onboardedConsignor();

    return FreightPost::factory()->for($consignor)->create([
        'status' => 'active',
        'cargo_description' => 'Twelve tonnes of cement',
    ]);
}

it('walks a consignor from registration to a published load', function () {
    [$consignor] = registerThroughTheApp();

    $profile = saveProfileThroughTheApp($consignor, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    expect((bool) $profile->posts_freight)->toBeTrue()
        ->and((bool) $profile->provides_transport)->toBeFalse()
        ->and($profile->display_name)->toBe('Adama Haulage');

    // Readiness has to actually say ready, or the gate below will refuse a
    // consignor who did everything the app asked them to do.
    $state = app(MarketplaceReadiness::class)->for($consignor);

    expect($state['ready']['posts_freight'])->toBeTrue();

    $post = publishALoadThroughTheWizard($consignor);

    // Submitted, not live. A newly published load goes to moderation, which is
    // why production holds 5 drafts, 1 submitted and 22 already approved. The
    // rest of these journeys work against a live post because expressing interest
    // is gated on `active` — a consignor cannot offer to carry their own load
    // before it has been approved, which is the behaviour we want.
    expect($post->user_id)->toBe($consignor->id)
        ->and($post->status)->toBe('submitted')
        ->and($post->item_name)->toBe('Cement')
        ->and((float) $post->tariff_amount)->toBe(9000.0)
        ->and($post->origin_location_id)->not->toBeNull()
        ->and($post->destination_location_id)->not->toBeNull();
});

it('walks a carrier from registration to expressing interest', function () {
    [$carrier] = registerThroughTheApp();

    $profile = saveProfileThroughTheApp($carrier, [
        'posts_freight' => false,
        'provides_transport' => true,
    ]);

    $this->actingAs($carrier)->put('/public-profile/transport', [
        'truck_type_ids' => [TruckType::factory()->create()->id],
    ])->assertSessionHasNoErrors();

    expect($profile->refresh()->provides_transport)->toBeTrue()
        ->and($profile->refresh()->status)->toBe('pending');

    $readiness = app(MarketplaceReadiness::class)->for($carrier);

    expect($readiness['ready']['provides_transport'])->toBeTrue();

    // Find somebody's load and say they can take it.
    $post = someonePostedALoad();

    $this->actingAs($carrier)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available with a 10 tonne truck'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('freight_interests', [
        'freight_post_id' => $post->id,
        'user_id' => $carrier->id,
    ]);
});

it('lets a consignor and a carrier exchange contact details', function () {
    // The reason any of this exists.
    [$consignor] = registerThroughTheApp();

    saveProfileThroughTheApp($consignor, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    // A carrier saved the same way a person would be.
    [$carrier] = registerThroughTheApp();

    saveProfileThroughTheApp($carrier, [
        'posts_freight' => false,
        'provides_transport' => true,
        'display_name' => 'Awash Carriers',
        'phone' => '+251911000002',
        'legal_name' => 'Awash Carriers PLC',
        'primary_location_id' => $base->id,
    ]);

    $this->actingAs($carrier)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasNoErrors();

    $interest = FreightInterest::where('freight_post_id', $post->id)
        ->where('user_id', $carrier->id)
        ->firstOrFail();

    // The carrier reveals the consignor's number.
    $response = $this->actingAs($carrier)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe('+251911000001');

    // And the consignor reveals the carrier's.
    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe('+251911000002');
});

it('serves a user with no profile row rather than erroring', function () {
    // Every user registered before public_profiles was introduced has no row on
    // it — 18 of 21 accounts on production. ContactRevealService used to open
    // with `if (! $profile) return null`, so all eleven live interests on
    // production revealed nothing at all. The marketplace's core mechanic had
    // never worked; every other test hid it by building profiles by hand.
    $carrier = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($carrier);
    $carrier->update(['current_workspace_id' => $workspace->id, 'name' => 'Legacy Consignor']);

    expect($carrier->profile()->exists())->toBeFalse();

    $consignor = onboardedConsignor();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $carrier->id,
        'status' => 'submitted',
        'message' => 'Available',
    ]);

    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    // The endpoint works. It has simply nothing to give: this account has no
    // profile row and no carrier record, and there is no phone column on users,
    // so no number exists anywhere to release. Returning null is honest; the old
    // early `if (! $profile) return null` returned the same null for a different
    // reason and made every one of these look like a successful reveal.
    $response->assertOk();
    expect($response->json('contact.name'))->toBe('Legacy Consignor')
        ->and($response->json('contact.phone'))->toBeNull();
});

it('reveals a legacy carrier after the migration copies the carrier record onto the profile', function () {
    // All four carrier profiles on production were like this: display_name,
    // phone and legal_name existed on transporter_profiles and nowhere else.
    // Contact resolution reads the profile only, so the backfill has to move
    // those details before the drop. This recreates the old schema, writes the
    // row the way production held it, and runs the same backfill the migration
    // runs.
    $consolidation = app(ProfileConsolidation::class);
    $consolidation->createLegacySchema();

    $carrier = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($carrier);
    $carrier->update(['current_workspace_id' => $workspace->id]);

    $base = Location::factory()->create(['name' => 'Adama']);

    DB::table('transporter_profiles')->insert([
        'user_id' => $carrier->id,
        'slug' => 'legacy-'.uniqid(),
        'display_name' => 'Elias Transport',
        'phone' => '+251913125042',
        'legal_name' => 'Elias Transport PLC',
        'primary_location_id' => $base->id,
        'status' => 'approved',
        // The column defaults to 0 — hidden. All four production carriers have
        // this set to 1, and a test that omits it is asserting the opposite of
        // what production actually holds.
        'phone_visible' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $consolidation->backfill();

    $consignor = onboardedConsignor();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $carrier->id,
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBe('+251913125042')
        ->and($response->json('contact.name'))->toBe('Elias Transport');
});

it('lets a carrier who also posts freight do both', function () {
    // The either/or model could not express this person at all.
    [$user] = registerThroughTheApp();

    saveProfileThroughTheApp($user, [
        'posts_freight' => true,
        'provides_transport' => true,
    ]);

    $post = someonePostedALoad();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasNoErrors();

    expect((bool) $user->profile()->value('posts_freight'))->toBeTrue()
        ->and((bool) $user->profile()->value('provides_transport'))->toBeTrue()
        ->and(FreightInterest::where('user_id', $user->id)->exists())->toBeTrue();
});

it('changes its mind about what it does and gets on with it', function () {
    // Registers to post freight, later needs to carry. There was no way to say so.
    [$user] = registerThroughTheApp();

    saveProfileThroughTheApp($user, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    saveProfileThroughTheApp($user, [
        'posts_freight' => true,
        'provides_transport' => true,
    ]);

    $readiness = app(MarketplaceReadiness::class)->for($user);

    expect($readiness['ready']['posts_freight'])->toBeTrue()
        ->and($readiness['ready']['provides_transport'])->toBeTrue();
});

it('shows a carrier the dashboard checklist naming what is blocking them', function () {
    // The question the dashboard is supposed to answer, asked of a real user.
    [$user] = registerThroughTheApp();

    saveProfileThroughTheApp($user, [
        'posts_freight' => false,
        'provides_transport' => true,
    ]);

    // Readiness is profile-only now, so remove the base from the profile rather
    // than relying on a missing carrier row to stand in for it.
    Profile::where('user_id', $user->id)->update(['primary_location_id' => null]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    $carrying = $steps->firstWhere('id', 'provides_transport_ready');

    expect($carrying)->not->toBeNull()
        ->and($carrying['href'])->toBe('/public-profile')
        ->and($carrying['description'])->toContain('primary_location_id');
});

it('honours a carrier who hid their number before the profile existed', function () {
    // The refusal has to survive the migration that copies the carrier record
    // onto the profile, or the fix only covers users created after it.
    $consolidation = app(ProfileConsolidation::class);
    $consolidation->createLegacySchema();

    $carrier = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($carrier);
    $carrier->update(['current_workspace_id' => $workspace->id]);

    $base = Location::factory()->create(['name' => 'Adama']);

    DB::table('transporter_profiles')->insert([
        'user_id' => $carrier->id,
        'slug' => 'legacy-hidden-'.uniqid(),
        'display_name' => 'Elias Transport',
        'phone' => '+251913125042',
        'primary_location_id' => $base->id,
        'status' => 'approved',
        'phone_visible' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $consolidation->backfill();

    $consignor = onboardedConsignor();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $carrier->id,
        'status' => 'submitted',
    ]);

    $response = $this->actingAs($consignor)
        ->postJson(route('freight.interests.reveal', ['interest' => $interest->id]));

    $response->assertOk();
    expect($response->json('contact.phone'))->toBeNull();
});
