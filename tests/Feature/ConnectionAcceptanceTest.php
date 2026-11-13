<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createConsignorForAcceptance(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

function createApprovedTransporterForAcceptance(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->transporter()->approved()->create([
        'user_id' => $user->id,
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('accept creates connection atomically and reveals contact only to participants', function () {
    $consignor = createConsignorForAcceptance();
    $transporter = createApprovedTransporterForAcceptance();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept")->assertRedirect();

    $conn = MarketplaceConnection::first();
    expect($conn)->not->toBeNull();
    expect($conn->contactDetailsFor($transporter))->not->toBeNull();
    expect($conn->contactDetailsFor(User::factory()->create()))->toBeNull();
});

it('is idempotent on double accept', function () {
    $consignor = createConsignorForAcceptance();
    $transporter = createApprovedTransporterForAcceptance();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");
    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");

    expect(MarketplaceConnection::count())->toBe(1);
});

it('selects only one transporter for a freight with competing interests', function () {
    $consignor = createConsignorForAcceptance();
    $firstTransporter = createApprovedTransporterForAcceptance();
    $secondTransporter = createApprovedTransporterForAcceptance();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);

    $first = FreightInterest::factory()->for($post)->for($firstTransporter)->create([
        'status' => FreightInterestStatus::Submitted->value,
    ]);
    $second = FreightInterest::factory()->for($post)->for($secondTransporter)->create([
        'status' => FreightInterestStatus::Shortlisted->value,
    ]);

    $this->actingAs($consignor)->post("/interests/{$first->id}/accept")->assertRedirect();
    $this->actingAs($consignor)->post("/interests/{$second->id}/accept")->assertSessionHasErrors('status');
    $this->actingAs($consignor)->post("/interests/{$first->id}/accept")->assertRedirect();

    expect(MarketplaceConnection::where('freight_post_id', $post->id)->count())->toBe(1);
    expect(MarketplaceConnection::first()->freight_interest_id)->toBe($first->id);
    expect($first->fresh()->status)->toBe(FreightInterestStatus::Selected->value);
    expect($second->fresh()->status)->toBe(FreightInterestStatus::Shortlisted->value);
    expect($post->fresh()->status)->toBe(FreightPostStatus::Closed->value);
    expect($post->moderationLogs()->where('reason', 'Accepted by consignor')->count())->toBe(1);
});

it('enforces the single-accepted-transporter rule at the database level', function () {
    $consignor = createConsignorForAcceptance();
    $firstTransporter = createApprovedTransporterForAcceptance();
    $secondTransporter = createApprovedTransporterForAcceptance();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $first = FreightInterest::factory()->for($post)->for($firstTransporter)->create();
    $second = FreightInterest::factory()->for($post)->for($secondTransporter)->create();

    $this->actingAs($consignor)->post("/interests/{$first->id}/accept")->assertRedirect();

    expect(fn () => MarketplaceConnection::create([
        'public_id' => (string) Str::ulid(),
        'freight_post_id' => $post->id,
        'freight_interest_id' => $second->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $secondTransporter->id,
    ]))->toThrow(QueryException::class);
});
