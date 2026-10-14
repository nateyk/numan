<?php

use App\Enums\LegalForm;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\MarketplaceReadiness;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
 * Register and onboard exactly as the app does — no factory shortcuts past the
 * screens a person actually goes through.
 *
 * @return array{0: User, 1: Workspace}
 */
function registerThroughTheApp(array $attributes = []): array
{
    // Deliberately NOT onboarded. OnboardingController refuses every wizard
    // action once onboarded_at is set, so pre-setting it means posting to
    // /onboarding is silently a no-op and the journey never actually runs.
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now(),
        'onboarded_at' => null,
    ], $attributes));

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    return [$user->refresh(), $workspace];
}

/**
 * All three wizard steps, in the order the app requires.
 *
 * The details step refuses to run until capabilities and account type are both
 * recorded, and each of those silently redirects to the dashboard when they are
 * not. Walking one step and skipping to the next produces a blank profile with no
 * error anywhere — which is exactly how a broken onboarding looks from outside.
 *
 * @param  array<string, mixed>  $details
 */
function onboardThroughTheApp(User $user, array $details = []): void
{
    $details = array_merge([
        'display_name' => 'Adama Haulage',
        'phone' => '+251911000001',
        'primary_location_id' => Location::factory()->create([
            'name' => 'Adama',
            'latitude' => 8.54,
            'longitude' => 39.27,
        ])->id,
        'legal_name' => 'Adama Haulage PLC',
        'legal_form' => LegalForm::PrivateLimitedCompany->value,
    ], $details);

    $postsFreight = $details['posts_freight'] ?? true;
    $providesTransport = $details['provides_transport'] ?? false;

    test()
        ->actingAs($user)
        ->post('/onboarding/capabilities', [
            'posts_freight' => $postsFreight ? '1' : '0',
            'provides_transport' => $providesTransport ? '1' : '0',
        ])
        ->assertRedirect();

    test()
        ->actingAs($user)
        ->post('/onboarding/account-type', [
            'account_type' => 'business',
            'legal_name' => $details['legal_name'],
            'legal_form' => $details['legal_form'],
        ])
        ->assertRedirect();

    test()
        ->actingAs($user)
        ->post('/onboarding/details', $details)
        ->assertRedirect();
}

/**
 * A load somebody has posted.
 *
 * Built with the factory rather than by walking the four-request draft wizard,
 * and deliberately so: posting a load is four screens with their own required
 * fields, and it has dedicated tests (FreightCargoStepTest and friends). What
 * this file is for is everything either side of it — registration, onboarding,
 * capability, interest and reveal — where a hand-built fixture hides the fact
 * that the app and the data do not actually fit together.
 */
function someonePostedALoad(): FreightPost
{
    // A plain onboarded consignor. Running the wizard again here would leave the
    // session carrying redirects and flash state into the assertions that follow.
    [$consignor] = registerThroughTheApp(['onboarded_at' => now()]);

    Profile::firstOrCreate(['user_id' => $consignor->id], [
        'display_name' => 'Consignor Co',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return FreightPost::factory()->for($consignor->refresh())->create([
        'status' => 'active',
        'cargo_description' => 'Twelve tonnes of cement',
    ]);
}

/**
 * An onboarded consignor who can post and be found.
 *
 * Used where the test is about the *counterparty's* missing data rather than
 * about onboarding, and an un-onboarded user would simply be redirected out of
 * the marketplace by middleware before the assertion is ever reached.
 */
function onboardedConsignor(): User
{
    [$user] = registerThroughTheApp(['onboarded_at' => now()]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Consignor Co',
        'phone' => '+251911000001',
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return $user->refresh();
}

it('walks a consignor from registration to a published load', function () {
    [$consignor] = registerThroughTheApp();

    // Onboarding. The capability is chosen first, then the details.
    onboardThroughTheApp($consignor, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $profile = $consignor->profile()->first();

    expect((bool) $profile->posts_freight)->toBeTrue()
        ->and((bool) $profile->provides_transport)->toBeFalse()
        ->and($profile->display_name)->toBe('Adama Haulage');

    // Readiness has to actually say ready, or the gate below will refuse a
    // consignor who did everything the app asked them to do.
    $state = app(MarketplaceReadiness::class)->for($consignor);

    expect($state['ready']['posts_freight'])->toBeTrue();

    $post = FreightPost::factory()->for($consignor)->create([
        'status' => 'active',
        'cargo_description' => 'Twelve tonnes of cement',
    ]);

    expect($post->user_id)->toBe($consignor->id)
        ->and($post->cargo_description)->toBe('Twelve tonnes of cement');
});

it('walks a carrier from registration to expressing interest', function () {
    [$carrier] = registerThroughTheApp();

    onboardThroughTheApp($carrier, [
        'posts_freight' => false,
        'provides_transport' => true,
    ]);

    // The carrier form. A real one fills this in.
    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);

    $this->actingAs($carrier)->post('/my-transporter-profile', [
        'display_name' => 'Adama Haulage',
        'legal_name' => 'Adama Haulage PLC',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => $base->id,
        'phone_visible' => '1',
        'email_visible' => '1',
    ]);

    $transporter = $carrier->transporterProfile()->first();

    expect($transporter)->not->toBeNull()
        ->and($transporter->isComplete())->toBeTrue();

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

    onboardThroughTheApp($consignor, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);

    // A carrier onboarded the same way a person would be.
    [$carrier] = registerThroughTheApp();

    onboardThroughTheApp($carrier, [
        'posts_freight' => false,
        'provides_transport' => true,
        'display_name' => 'Awash Carriers',
        'phone' => '+251911000002',
        'legal_name' => 'Awash Carriers PLC',
    ]);
    $this->actingAs($carrier)->post('/my-transporter-profile', [
        'display_name' => 'Awash Carriers',
        'phone' => '+251911000002',
        'primary_location_id' => $base->id,
        'phone_visible' => '1',
        'email_visible' => '1',
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

it('reveals a legacy carrier whose details live only on the carrier record', function () {
    // All four carrier profiles on production are like this: display_name, phone
    // and legal_name exist on transporter_profiles and nowhere else. Dropping
    // those columns without copying them first would have deleted four real
    // carriers' phone numbers, and reading only the profile would return nothing.
    $carrier = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($carrier);
    $carrier->update(['current_workspace_id' => $workspace->id]);

    $base = Location::factory()->create(['name' => 'Adama']);

    TransporterProfile::create([
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
    ]);

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

    onboardThroughTheApp($user, [
        'posts_freight' => true,
        'provides_transport' => true,
    ]);

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);
    $post = someonePostedALoad();

    $this->actingAs($user)->post('/my-transporter-profile', [
        'display_name' => 'Adama Haulage',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => $base->id,
        'phone_visible' => '1',
    ]);

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

    onboardThroughTheApp($user, [
        'posts_freight' => true,
        'provides_transport' => false,
    ]);

    $this->actingAs($user)
        ->patch('/settings/capabilities', ['posts_freight' => '1', 'provides_transport' => '1'])
        ->assertRedirect();

    $base = Location::factory()->create(['name' => 'Adama', 'latitude' => 8.54, 'longitude' => 39.27]);

    $this->actingAs($user)->post('/my-transporter-profile', [
        'display_name' => 'Adama Haulage',
        'legal_name' => 'Adama Haulage PLC',
        'phone' => '+251911000001',
        'email' => 'carrier@example.test',
        'primary_location_id' => $base->id,
        'phone_visible' => '1',
        'email_visible' => '1',
    ])->assertSessionHasNoErrors();

    $readiness = app(MarketplaceReadiness::class)->for($user);

    expect($readiness['ready']['posts_freight'])->toBeTrue()
        ->and($readiness['ready']['provides_transport'])->toBeTrue();
});

it('shows a carrier the dashboard checklist naming what is blocking them', function () {
    // The question the dashboard is supposed to answer, asked of a real user.
    [$user] = registerThroughTheApp();

    onboardThroughTheApp($user, [
        'posts_freight' => false,
        'provides_transport' => true,
    ]);

    $steps = collect($this->actingAs($user)->getJson('/onboarding-checklist')->json('steps'));

    $carrying = $steps->firstWhere('id', 'provides_transport_ready');

    // Onboarding records the person's base on the profile, which is not the
    // carrier record. So the first thing missing is the record itself.
    expect($carrying)->not->toBeNull()
        ->and($carrying['href'])->toBe('/settings/marketplace-details')
        ->and($carrying['description'])->toContain('transporter_profile');
});

it('honours a carrier who hides their number, without a profile row', function () {
    // The refusal flag must work for the legacy records too, or the fix only
    // covers users created after it.
    $carrier = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($carrier);
    $carrier->update(['current_workspace_id' => $workspace->id]);

    $base = Location::factory()->create(['name' => 'Adama']);

    TransporterProfile::create([
        'user_id' => $carrier->id,
        'slug' => 'legacy-hidden-'.uniqid(),
        'display_name' => 'Elias Transport',
        'phone' => '+251913125042',
        'primary_location_id' => $base->id,
        'status' => 'approved',
        'phone_visible' => false,
    ]);

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
