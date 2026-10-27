<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The review step is where a submit bounce used to be invisible: the
 * controller returned errors for five different reasons and the page rendered
 * none of them. These tests pin the contracts the page reads.
 */
function reviewFeedbackUser(): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'account_type' => 'personal',
        'display_name' => 'Review Tester',
        'phone' => '+251900000001',
        'primary_location_id' => $location->id,
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    return $user->fresh();
}

/**
 * A draft with every scored field cleared, so nothing blocks and nothing is met.
 */
function emptyReviewDraft(User $user): FreightPost
{
    return FreightPost::factory()->create([
        'user_id' => $user->id,
        'status' => FreightPostStatus::Draft->value,
        'origin_location_id' => null,
        'destination_location_id' => null,
        'origin_exact_lat' => null,
        'origin_exact_lng' => null,
        'destination_exact_lat' => null,
        'destination_exact_lng' => null,
        'origin_point_confirmed_at' => null,
        'destination_point_confirmed_at' => null,
        'cargo_type_id' => null,
        'cargo_description' => null,
        'item_name' => null,
        'actual_weight' => null,
        'tariff_amount' => null,
        'required_truck_type_id' => null,
        'truck_count' => 0,
        'pickup_date' => null,
        'pickup_time' => null,
    ]);
}

function completeReviewDraft(User $user): FreightPost
{
    return FreightPost::factory()->create([
        'user_id' => $user->id,
        'status' => FreightPostStatus::Draft->value,
    ]);
}

it('tells the review page which requirements still block submission', function () {
    $user = reviewFeedbackUser();
    $post = emptyReviewDraft($user);

    $response = $this->actingAs($user)->get("/my-freight/{$post->ulid}/review");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('freight/review')
        ->has('requirements', 5)
        ->where('requirements.0.key', 'route')
        ->where('requirements.0.met', false)
        ->where('requirements.1.key', 'cargo')
        ->where('requirements.1.met', false)
        ->where('requirements.2.key', 'truck')
        ->where('requirements.2.met', false)
        ->where('requirements.3.key', 'schedule')
        ->where('requirements.3.met', false)
        ->where('requirements.4.key', 'completeness')
        ->where('requirements.4.met', false)
    );
});

it('marks every review requirement met once the load is complete', function () {
    $user = reviewFeedbackUser();
    $post = completeReviewDraft($user);

    $response = $this->actingAs($user)->get("/my-freight/{$post->ulid}/review");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('freight/review')
        ->has('requirements', 5)
        ->where('requirements.0.met', true)
        ->where('requirements.1.met', true)
        ->where('requirements.2.met', true)
        ->where('requirements.3.met', true)
        ->where('requirements.4.met', true)
    );
});

it('accepts a load whose only cargo detail is the item name', function () {
    $user = reviewFeedbackUser();
    $post = completeReviewDraft($user);
    $post->update([
        'cargo_type_id' => null,
        'cargo_description' => null,
        'item_name' => '20 tons teff in 40kg bags',
    ]);

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/submit")
        ->assertSessionHasNoErrors();

    expect(FreightPost::find($post->id)->status)->toBe(FreightPostStatus::Submitted->value);
});

it('bounces an incomplete submit back with the field errors the review page must show', function () {
    $user = reviewFeedbackUser();
    $post = emptyReviewDraft($user);

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/submit")
        ->assertSessionHasErrors(['origin_location_id']);
});

it('flashes the submission confirmation onto My Freight', function () {
    $user = reviewFeedbackUser();
    $post = completeReviewDraft($user);

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/submit")
        ->assertRedirect(route('freight.index'));

    expect(FreightPost::find($post->id)->status)->toBe(FreightPostStatus::Submitted->value);

    $this->actingAs($user)
        ->get('/my-freight')
        ->assertInertia(fn ($page) => $page
            ->component('freight/index')
            ->where('flash.success', 'Freight submitted for review.')
        );
});
