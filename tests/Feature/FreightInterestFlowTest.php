<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createConsignor(): User
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

function createApprovedTransporter(): User
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

it('transporter expresses interest and consignor shortlists', function () {
    $consignor = createConsignor();
    $transporter = createApprovedTransporter();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($transporter)->post("/my-freight/{$post->ulid}/interests", ['message' => 'I am interested'])->assertRedirect();

    $interest = \App\Models\FreightInterest::first();
    expect($interest)->not->toBeNull()
        ->and($interest->status)->toBe(FreightInterestStatus::Submitted->value);

    $this->actingAs($consignor)->post("/interests/{$interest->id}/shortlist")->assertRedirect();
    expect($interest->fresh()->status)->toBe(FreightInterestStatus::Shortlisted->value);
});

it('prevents self-interest', function () {
    $consignor = createConsignor();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($consignor)->post("/my-freight/{$post->ulid}/interests", ['message' => 'self'])->assertForbidden();
});

it('allows transporter to withdraw before accept', function () {
    $consignor = createConsignor();
    $transporter = createApprovedTransporter();
    $post = FreightPost::factory()->create(['user_id' => $consignor->id, 'status' => FreightPostStatus::Active->value]);

    $this->actingAs($transporter)->post("/my-freight/{$post->ulid}/interests")->assertRedirect();
    $interest = \App\Models\FreightInterest::first();

    $this->actingAs($transporter)->post("/interests/{$interest->id}/withdraw")->assertRedirect();
    expect($interest->fresh()->status)->toBe(FreightInterestStatus::Withdrawn->value);
});
