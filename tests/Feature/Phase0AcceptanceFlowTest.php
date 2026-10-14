<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Phase 0 acceptance flow check.
 *
 * Traces the real flow with a real consignor and a real transporter:
 *   interest -> shortlist -> accept -> MarketplaceConnection -> contact details
 *
 * This is a diagnostic, not a change: it reports what works today.
 */
uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: User, 2: FreightPost}
 */
function seedAcceptanceFlow(): array
{
    $consignor = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);
    $consignorWorkspace = app(WorkspaceService::class)->createPersonalWorkspace($consignor);
    $consignor->update(['current_workspace_id' => $consignorWorkspace->id]);
    $consignor->refresh();

    Profile::firstOrCreate(['user_id' => $consignor->id], [
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Consignor Person',
        'phone' => '+251911000001',
    ]);

    $transporter = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);
    $transporterWorkspace = app(WorkspaceService::class)->createPersonalWorkspace($transporter);
    $transporter->update(['current_workspace_id' => $transporterWorkspace->id]);
    $transporter->refresh();

    Profile::firstOrCreate(['user_id' => $transporter->id], [
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'display_name' => 'Transporter Person',
        'phone' => '+251922000002',
    ]);

    // primary_location_id is now part of what "complete" means. This fixture was
    // approved but had no base, which passed only because approval used to stand
    // in for completeness — a transporter with no location is an unfinished
    // profile, and the test was asserting the finished case.
    $base = Location::firstOrCreate(
        ['name' => 'Adama'],
        ['type' => 'city', 'region_name' => 'Oromia', 'latitude' => 8.54, 'longitude' => 39.27]
    );

    TransporterProfile::firstOrCreate(['user_id' => $transporter->id], [
        'slug' => 'transporter-'.Str::lower(Str::random(8)),
        'display_name' => 'Transporter Person',
        'status' => 'approved',
        'phone' => '+251922000002',
        'primary_location_id' => $base->id,
        'email' => 'transporter@example.com',
        'whatsapp' => '+251955000003',
        'phone_visible' => true,
        'email_visible' => true,
        'whatsapp_visible' => true,
    ]);

    $post = FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $consignor->id,
        'workspace_id' => $consignorWorkspace->id,
        'status' => FreightPostStatus::Active->value,
        'cargo_description' => 'Phase 0 acceptance flow cargo',
    ]);

    return [$consignor, $transporter, $post];
}

it('phase 0: transporter can express interest on an active post', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $response = $this->actingAs($transporter)->post(
        route('freight.interests.store', ['ulid' => $post->ulid]),
        ['contact_name' => 'Transporter Person']
    );

    $response->assertRedirect();

    expect(FreightInterest::where('freight_post_id', $post->id)->count())->toBe(1);

    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    expect($interest->status)->toBe('submitted');
});

it('phase 0: consignor can shortlist the interest', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);

    $interest = FreightInterest::where('freight_post_id', $post->id)->first();

    $response = $this->actingAs($consignor)->post(
        route('freight.interests.shortlist', ['interest' => $interest->id])
    );

    $response->assertRedirect();

    expect($interest->fresh()->status)->toBe('shortlisted');
});

it('phase 0: consignor can accept and a connection is created', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();

    $response = $this->actingAs($consignor)->post(
        route('freight.interests.accept', ['interest' => $interest->id])
    );

    $response->assertRedirect();

    expect(MarketplaceConnection::count())->toBe(1);

    $connection = MarketplaceConnection::first();
    expect($connection->freight_post_id)->toBe($post->id);
    expect($connection->freight_interest_id)->toBe($interest->id);
    expect($connection->consignor_user_id)->toBe($consignor->id);
    expect($connection->transporter_user_id)->toBe($transporter->id);

    expect($interest->fresh()->status)->toBe('selected');
    expect($post->fresh()->status)->toBe(FreightPostStatus::Closed->value);
});

it('phase 0: the transporter can see the consignor contact details on the connection', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $connection = MarketplaceConnection::first();

    $details = $connection->contactDetailsFor($transporter);

    expect($details)->not->toBeNull();
    expect($details['phone'])->toBe('+251911000001');
    expect($details['email'])->toBe($consignor->email);
});

it('phase 0: the consignor can see the transporter contact details on the connection', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $connection = MarketplaceConnection::first();

    $details = $connection->contactDetailsFor($consignor);

    expect($details)->not->toBeNull();
    expect($details['phone'])->toBe('+251922000002');
});

it('phase 0: an unrelated user gets no contact details', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $connection = MarketplaceConnection::first();
    $stranger = User::factory()->create();

    expect($connection->contactDetailsFor($stranger))->toBeNull();
});

it('phase 0: the connection page renders for the transporter', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $connection = MarketplaceConnection::first();

    $this->actingAs($transporter)
        ->get(route('connections.show', ['public_id' => $connection->public_id]))
        ->assertOk();
});

it('phase 0: the reveal endpoint returns contact details for the transporter', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    $connection = MarketplaceConnection::first();

    $response = $this->actingAs($transporter)->post(
        route('connections.reveal', ['public_id' => $connection->public_id])
    );

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('phase 0: accepting twice is idempotent and does not create a second connection', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();

    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    // Second attempt is refused because the interest is already selected.
    $this->actingAs($consignor)->post(route('freight.interests.accept', ['interest' => $interest->id]));

    expect(MarketplaceConnection::count())->toBe(1);
});

it('phase 0: a stranger cannot accept an interest', function () {
    [$consignor, $transporter, $post] = seedAcceptanceFlow();

    $this->actingAs($transporter)->post(route('freight.interests.store', ['ulid' => $post->ulid]), ['contact_name' => 'Transporter Person']);
    $interest = FreightInterest::where('freight_post_id', $post->id)->first();

    $stranger = User::factory()->create(['email_verified_at' => now(), 'onboarded_at' => now()]);

    $this->actingAs($stranger)->post(
        route('freight.interests.accept', ['interest' => $interest->id])
    )->assertForbidden();

    expect(MarketplaceConnection::count())->toBe(0);
});
