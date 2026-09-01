<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplaceConnection;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConsignorForConnection(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

function createApprovedTransporterForConnection(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
        'primary_location_id' => $location->id,
    ]);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);
    \App\Models\TransporterProfile::factory()->create(['user_id' => $user->id, 'status' => 'approved']);
    $user->setRelation('transporterProfile', $user->transporterProfile()->first());

    return $user->fresh();
}

it('contactDetailsFor returns null for outsider', function () {
    $consignor = createConsignorForConnection();
    $transporter = createApprovedTransporterForConnection();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);
    \App\Models\MarketplaceConnection::factory()->create([
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $outsider = User::factory()->create();
    $conn = \App\Models\MarketplaceConnection::first();
    expect($conn->contactDetailsFor($outsider))->toBeNull();
});

it('contactDetailsFor returns contact for transporter participant', function () {
    $consignor = createConsignorForConnection();
    $transporter = createApprovedTransporterForConnection();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);
    \App\Models\MarketplaceConnection::factory()->create([
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $conn = \App\Models\MarketplaceConnection::first();
    $details = $conn->contactDetailsFor($transporter);
    expect($details)->not->toBeNull()
        ->and($details['name'])->not->toBeEmpty();
});

it('contactDetailsFor returns contact for consignor participant', function () {
    $consignor = createConsignorForConnection();
    $transporter = createApprovedTransporterForConnection();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);
    \App\Models\MarketplaceConnection::factory()->create([
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $conn = \App\Models\MarketplaceConnection::first();
    $details = $conn->contactDetailsFor($consignor);
    expect($details)->not->toBeNull()
        ->and($details['name'])->not->toBeEmpty();
});

it('factory generates unique public_id', function () {
    \App\Models\MarketplaceConnection::factory()->create();
    \App\Models\MarketplaceConnection::factory()->create();
    expect(\App\Models\MarketplaceConnection::count())->toBe(2);
});