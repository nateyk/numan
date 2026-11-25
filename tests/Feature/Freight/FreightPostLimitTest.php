<?php

use App\Models\FreightPost;
use App\Models\Location;
use App\Models\MarketplaceSetting;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A poster whose workspace is already at the Free live-post limit.
 *
 * Mirrors the cargo-step helper: onboarded profile with posting capability,
 * a phone number (required by store), and an owned workspace set as current.
 */
function limitTestUser(int $livePosts): User
{
    // Pin the quota at one live post through the settings row (the same row
    // the admin UI writes): the service reads the row first and only falls
    // back to config when it is missing, so a config override alone would
    // not take effect here.
    MarketplaceSetting::updateOrCreate(['key' => 'free_active_posts'], ['value' => 1]);

    $user = User::factory()->create();
    $location = Location::factory()->create();

    Profile::factory()->create([
        'user_id' => $user->id,
        'posts_freight' => true,
        'provides_transport' => false,
        'account_type' => 'personal',
        'display_name' => 'Test User',
        'phone' => '+251900000000',
        'primary_location_id' => $location->id,
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    FreightPost::factory()->count($livePosts)->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => 'active',
    ]);

    return $user->fresh();
}

it('redirects with an explanation instead of a raw 403 when the free post limit is reached', function () {
    $user = limitTestUser(1);

    $this->actingAs($user)
        ->post('/my-freight')
        ->assertRedirect('/my-freight')
        ->assertSessionHasErrors('freight');

    // No draft may be created as a side effect of the denied request.
    expect(FreightPost::where('user_id', $user->id)->count())->toBe(1);
});

it('still creates a draft when under the free post limit', function () {
    $user = limitTestUser(0);

    $this->actingAs($user)
        ->post('/my-freight')
        ->assertRedirect();

    expect(FreightPost::where('user_id', $user->id)->where('status', 'draft')->count())->toBe(1);
});

it('tells the freight list whether posting is still allowed', function () {
    $user = limitTestUser(1);

    $this->actingAs($user)
        ->get('/my-freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canPost', false)
            ->where('livePostLimit', 1)
        );
});

it('cannot submit an earlier draft after another live freight post consumes the only slot', function () {
    $user = limitTestUser(1);
    $post = FreightPost::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $user->current_workspace_id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->post("/my-freight/{$post->ulid}/submit")
        ->assertSessionHasErrors('freight');

    expect($post->fresh()->status)->toBe('draft');
    expect($post->moderationLogs()->count())->toBe(0);
});

it('allows one draft submission but rejects the next when the free live cap is one', function () {
    $user = limitTestUser(0);
    $drafts = FreightPost::factory()->count(2)->create([
        'user_id' => $user->id,
        'workspace_id' => $user->current_workspace_id,
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->post("/my-freight/{$drafts[0]->ulid}/submit")
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post("/my-freight/{$drafts[1]->ulid}/submit")
        ->assertSessionHasErrors('freight');

    expect($drafts[0]->fresh()->status)->toBe('submitted');
    expect($drafts[1]->fresh()->status)->toBe('draft');
});
