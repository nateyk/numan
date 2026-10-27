<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceConnection;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\MarketplaceConnectionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeConnectionUser(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'primary_location_id' => $loc->id,
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    return $user->fresh();
}

function makeTransporterUser(): User
{
    $user = User::factory()->create();
    $loc = Location::factory()->create();
    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => false,
        'provides_transport' => true,
        'primary_location_id' => $loc->id,
        'status' => 'approved',
    ]);
    $ws = Workspace::factory()->create(['owner_id' => $user->id]);
    $ws->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $ws->id]);

    return $user->fresh();
}

it('allows any authenticated user to viewAny connections', function () {
    $user = makeConnectionUser();
    expect((new MarketplaceConnectionPolicy)->viewAny($user))->toBeTrue();
});

it('allows view only for participants or superadmin', function () {
    $consignor = makeConnectionUser();
    $transporter = makeTransporterUser();
    $outsider = makeConnectionUser();
    $superadmin = User::factory()->superadmin()->create();
    $ws = Workspace::factory()->create(['owner_id' => $superadmin->id]);
    $ws->users()->attach($superadmin->id, ['role' => 'owner']);
    $superadmin->update(['current_workspace_id' => $ws->id]);

    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);
    $connection = MarketplaceConnection::factory()->create([
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $policy = new MarketplaceConnectionPolicy;
    expect($policy->view($consignor, $connection))->toBeTrue()
        ->and($policy->view($transporter, $connection))->toBeTrue()
        ->and($policy->view($outsider, $connection))->toBeFalse()
        ->and($policy->view($superadmin->fresh(), $connection))->toBeTrue();
});

it('allows update only for participants', function () {
    $consignor = makeConnectionUser();
    $transporter = makeTransporterUser();
    $outsider = makeConnectionUser();
    $post = FreightPost::factory()->for($consignor)->create(['status' => 'active']);
    $interest = FreightInterest::factory()->for($post)->for($transporter)->create(['status' => 'submitted']);
    $connection = MarketplaceConnection::factory()->create([
        'freight_post_id' => $post->id,
        'freight_interest_id' => $interest->id,
        'consignor_user_id' => $consignor->id,
        'transporter_user_id' => $transporter->id,
    ]);

    $policy = new MarketplaceConnectionPolicy;
    expect($policy->update($consignor, $connection))->toBeTrue()
        ->and($policy->update($transporter, $connection))->toBeTrue()
        ->and($policy->update($outsider, $connection))->toBeFalse();
});
