<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\TransporterProfile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createAcceptUser(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    return $user->fresh();
}

function createTransporterForAccept(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);
    TransporterProfile::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
    $user->setRelation('transporterProfile', $user->transporterProfile()->first());

    return $user->fresh();
}

it('creates only one status history for selected on accept', function () {
    $consignor = createAcceptUser();
    $transporter = createTransporterForAccept();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");

    $histories = $interest->fresh()->statusHistories()->count();
    expect($histories)->toBe(1); // only the selected transition (no duplicate)
});

it('accept is idempotent when connection exists', function () {
    $consignor = createAcceptUser();
    $transporter = createTransporterForAccept();
    $post = FreightPost::factory()->for($consignor)->create(['status' => FreightPostStatus::Active->value]);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => FreightInterestStatus::Submitted->value]);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");
    $firstConnection = MarketplaceConnection::first();
    expect($firstConnection)->not->toBeNull();

    $this->actingAs($consignor)->post("/interests/{$interest->id}/accept");
    expect(MarketplaceConnection::count())->toBe(1);
    expect($firstConnection->public_id)->toBe(MarketplaceConnection::first()->public_id);
});
