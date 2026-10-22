<?php

use App\Enums\FreightInterestStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function interestsOwnerUser(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'account_type' => 'personal',
        'display_name' => 'Shipper Tester',
        'phone' => '+25191'.random_int(10000000, 99999999),
        'primary_location_id' => $location->id,
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

it('passes the pagination the page renders', function () {
    $owner = interestsOwnerUser();
    $post = FreightPost::factory()->active()->create(['user_id' => $owner->id]);

    FreightInterest::factory()->count(15)->create(['freight_post_id' => $post->id]);

    $this->actingAs($owner)
        ->get("/my-freight/{$post->ulid}/interests")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('freight/interests')
            ->where('total', 15)
            ->has('links'));
});

it('counts every interest on the post, not just the current page', function () {
    $owner = interestsOwnerUser();
    $post = FreightPost::factory()->active()->create(['user_id' => $owner->id]);

    FreightInterest::factory()->count(13)->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Submitted->value,
    ]);
    FreightInterest::factory()->count(2)->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Shortlisted->value,
    ]);

    $this->actingAs($owner)
        ->get("/my-freight/{$post->ulid}/interests")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.all', 15)
            ->where('counts.submitted', 13)
            ->where('counts.shortlisted', 2)
            ->where('counts.selected', 0));
});

it('bounces an invalid accept with the error the page must show', function () {
    $owner = interestsOwnerUser();
    $post = FreightPost::factory()->active()->create(['user_id' => $owner->id]);
    $interest = FreightInterest::factory()->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Rejected->value,
    ]);

    $this->actingAs($owner)
        ->from("/my-freight/{$post->ulid}/interests")
        ->post("/interests/{$interest->id}/accept")
        ->assertSessionHasErrors('status');
});

it('flashes success when an interest is rejected', function () {
    $owner = interestsOwnerUser();
    $post = FreightPost::factory()->active()->create(['user_id' => $owner->id]);
    $interest = FreightInterest::factory()->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Submitted->value,
    ]);

    $this->actingAs($owner)
        ->from("/my-freight/{$post->ulid}/interests")
        ->post("/interests/{$interest->id}/reject")
        ->assertSessionHas('success', 'Interest rejected.');
});
