<?php

use App\Enums\FreightInterestStatus;
use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * /my-freight/{ulid} is the owner's management page, but it rendered the same
 * component as the public /freight/{ulid} with none of the viewer-relationship
 * props — and FreightPostResource only sends 'owner' when user is loaded, which
 * this controller never did. The owner therefore saw their own post as a
 * visitor would: no owner card, no interest count, and an Express Interest
 * button aimed at themselves.
 */
function ownerShowUser(string $role = 'owner'): User
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => $role === 'owner',
        'provides_transport' => $role === 'carrier',
        'account_type' => 'personal',
        'display_name' => ucfirst($role).' Tester',
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

it('renders the manage URL as the owners own post, with the interest count', function () {
    $owner = ownerShowUser();
    $post = FreightPost::factory()->create([
        'user_id' => $owner->id,
        'status' => FreightPostStatus::Submitted->value,
    ]);

    FreightInterest::factory()->count(2)->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Submitted->value,
    ]);
    FreightInterest::factory()->create([
        'freight_post_id' => $post->id,
        'status' => FreightInterestStatus::Rejected->value,
    ]);

    $response = $this->actingAs($owner)->get("/my-freight/{$post->ulid}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('freight/show')
        ->where('post.owner.id', $owner->id)
        ->where('hasInterest', false)
        ->where('canReveal', false)
        ->where('interestId', null)
        ->where('interestsCount', 3)
        ->where('isHiddenListing', false)
    );
});

it('sends anyone else who opens the manage URL to the public page', function () {
    $owner = ownerShowUser();
    $carrier = ownerShowUser('carrier');
    // Active, so the view policy admits a non-owner and the redirect is what
    // answers them; a submitted post would 403 them before reaching it.
    $post = FreightPost::factory()->create([
        'user_id' => $owner->id,
        'status' => FreightPostStatus::Active->value,
    ]);

    $this->actingAs($carrier)
        ->get("/my-freight/{$post->ulid}")
        ->assertRedirect(route('freight.public.show', $post->ulid));
});

it('lets the owner open a hidden post from the manage URL', function () {
    $owner = ownerShowUser();
    $post = FreightPost::factory()->create([
        'user_id' => $owner->id,
        'status' => FreightPostStatus::Submitted->value,
        'hidden_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get("/my-freight/{$post->ulid}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('freight/show')
        ->where('isHiddenListing', true)
        ->where('canRestoreListing', true)
    );
});
