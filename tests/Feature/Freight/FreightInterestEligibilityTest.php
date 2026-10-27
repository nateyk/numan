<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Who may express interest, and what they are told when they may not.
 *
 * The bug this covers: expressing interest required transporter_profiles.status
 * === 'approved', and only AdminTransporterController can set that. So a new user
 * registered, filled in a transporter profile, and was told it was "pending
 * approval" — and waited, because no queue existed to approve it. Expressing
 * interest is the single action the marketplace depends on, and it was behind a
 * manual admin click that nothing performs.
 *
 * Approval is now a quality signal — a verified badge, priority in listings —
 * rather than a wall between somebody and saying they are interested. The
 * protection against contact details being harvested is the metered reveal
 * ledger, which already exists and already logs every use.
 *
 * Completeness is what gates instead, and this file pins what that means.
 */
uses(RefreshDatabase::class);

/**
 * A transporter with a profile at the given status.
 *
 * @param  array<string, mixed>  $transporterOverrides
 */
function transporterAt(string $status = 'pending', array $transporterOverrides = [], array $profileOverrides = []): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $location = Location::factory()->create([
        'name' => 'Adama',
        'latitude' => 8.54,
        'longitude' => 39.27,
    ]);

    Profile::factory()->create(array_merge([
        'user_id' => $user->id,
        'account_type' => 'business',
        'display_name' => 'Test Haulage',
        'phone' => '+251911000001',
        'primary_location_id' => $location->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ], $profileOverrides));

    $transporter = TransporterProfile::create(array_merge([
        'user_id' => $user->id,
        'slug' => 'test-haulage-'.uniqid(),
        'display_name' => 'Test Haulage',
        'legal_name' => 'Test Haulage PLC',
        'phone' => '+251911000001',
        'business_description' => 'General freight across Oromia',
        'primary_location_id' => $location->id,
        'status' => $status,
    ], $transporterOverrides));

    return [$user, $transporter];
}

function activePost(): FreightPost
{
    $consignor = User::factory()->create();
    Profile::factory()->create([
        'user_id' => $consignor->id,
        'display_name' => 'Consignor',
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    return FreightPost::factory()->for($consignor)->create(['status' => 'active']);
}

// ── the deadlock ───────────────────────────────────────────────────────────

it('lets a pending transporter express interest', function () {
    // The whole point. Pending is the state every new transporter is in, and it
    // is the state nothing ever leaves.
    [$user] = transporterAt('pending');
    $post = activePost();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasNoErrors();

    expect(FreightInterest::where('user_id', $user->id)->exists())->toBeTrue();
});

it('lets an approved transporter express interest', function () {
    // Unchanged behaviour: approval never became a blocker.
    [$user] = transporterAt('approved');
    $post = activePost();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasNoErrors();
});

it('tells an incomplete transporter to complete the profile, not to create one', function () {
    [$user] = transporterAt('pending', [], ['primary_location_id' => null]);
    $post = activePost();

    $response = $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available']);

    $response->assertSessionHasErrors('interest');

    $message = session('errors')->first('interest');

    // "Please create a transporter profile first" is wrong once the profile
    // exists — it sends somebody to a page they have already filled in. The
    // message has to name what is actually missing instead.
    expect($message)->toContain('public profile')
        ->and($message)->toContain('primary_location_id')
        ->and(strtolower($message))->toContain('complete')
        ->not->toContain('create');
});

it('sends a user with no profile at all to the phone step first', function () {
    // There is no separate carrier record to create any more. A user with no
    // profile is missing identity, and the inline phone step is the first thing
    // they can act on.
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = activePost();

    $response = $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available']);

    $response->assertSessionHasErrors('interest')
        ->assertSessionHas('requires_phone', true);
    expect(session('errors')->first('interest'))->toContain('phone number');
});

it('never mentions approval when refusing an interest', function () {
    [$user] = transporterAt('pending', [], ['phone' => null]);
    $post = activePost();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasErrors('interest');

    // "Pending approval" told people to wait for something that never happens.
    // If they are being turned away, it is because the profile is unfinished.
    expect(session('errors')->first('interest'))->not->toContain('approval')
        ->not->toContain('wait');
});

// ── completeness is defined, not assumed ───────────────────────────────────

it('reports a complete transporter profile as complete', function () {
    [, $transporter] = transporterAt('pending');

    expect($transporter->isComplete())->toBeTrue()
        ->and($transporter->missingFields())->toBe([]);
});

it('reports an incomplete profile and says which fields', function () {
    // display_name is NOT NULL in the schema, so a blank string is the only way
    // to leave it empty — which is exactly why the completeness check trims rather
    // than testing for null alone. Whitespace passes a null check and renders as
    // an empty line to whoever is reading the profile.
    [, $transporter] = transporterAt('pending', [
        'display_name' => '   ',
        'phone' => '',
    ]);

    expect($transporter->isComplete())->toBeFalse()
        ->and($transporter->missingFields())->toContain('display_name', 'phone');
});

// ── capabilities are independent ───────────────────────────────────────────

it('lets someone who also posts freight express interest', function () {
    // The case the old model could not express: both capabilities at once.
    [$user] = transporterAt('pending');
    $user->profile->update(['posts_freight' => true]);
    $post = activePost();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasNoErrors();
});

it('still refuses someone who does not provide transport', function () {
    [$user] = transporterAt('pending');
    $user->profile->update(['provides_transport' => false]);
    $post = activePost();

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/interests", ['message' => 'Available'])
        ->assertSessionHasErrors('interest');

    expect(FreightInterest::where('user_id', $user->id)->exists())->toBeFalse();
});
