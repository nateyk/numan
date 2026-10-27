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
use Illuminate\Foundation\Testing\RefreshDatabase;

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
