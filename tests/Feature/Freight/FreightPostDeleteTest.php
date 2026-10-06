<?php

use App\Models\FreightPost;
use App\Models\Location;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A user owning a draft freight post.
 *
 * @return array{0: User, 1: FreightPost}
 */
function deleteTestSetup(): array
{
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
        'onboarding_step' => 'completed',
        'onboarding_completed_at' => now(),
    ]);

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->users()->attach($user->id, ['role' => 'owner']);
    $user->update(['current_workspace_id' => $workspace->id]);

    $post = FreightPost::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
    ]);

    return [$user->fresh(), $post];
}

it('lets the owner delete their freight post', function () {
    [$user, $post] = deleteTestSetup();

    $this->actingAs($user)
        ->delete("/my-freight/{$post->ulid}")
        ->assertRedirect(route('freight.index'));

    expect(FreightPost::where('ulid', $post->ulid)->count())->toBe(0);
});

it('forbids deleting another user freight post', function () {
    [$user, $post] = deleteTestSetup();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->delete("/my-freight/{$post->ulid}")
        ->assertForbidden();

    expect(FreightPost::where('ulid', $post->ulid)->count())->toBe(1);
});

it('requires authentication to delete a freight post', function () {
    [$user, $post] = deleteTestSetup();

    $this->delete("/my-freight/{$post->ulid}")->assertRedirect('/login');

    expect(FreightPost::where('ulid', $post->ulid)->count())->toBe(1);
});
