<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\Profile;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: Workspace}
 */
function disabledInterestsUser(): array
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'onboarded_at' => now(),
    ]);

    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    Profile::firstOrCreate(['user_id' => $user->id], [
        'display_name' => 'Flag User',
        'phone' => '+251911000123',
    ]);

    return [$user->fresh(), $workspace];
}

it('404s the interest pages and actions when the flag is off', function () {
    config(['marketplace.interests_enabled' => false]);

    [$owner] = disabledInterestsUser();
    $post = FreightPost::factory()->for($owner)->create([
        'status' => FreightPostStatus::Active->value,
    ]);

    $interest = FreightInterest::create([
        'freight_post_id' => $post->id,
        'user_id' => $owner->id,
        'status' => 'submitted',
        'message' => 'still in the database',
    ]);

    $this->actingAs($owner)
        ->get(route('freight.my-interests'))
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('freight.interests.store', $post->ulid))
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('freight.interests.reveal', $interest->id))
        ->assertNotFound();
});

it('keeps the listing reveal working when interests are off', function () {
    config(['marketplace.interests_enabled' => false]);

    [$owner] = disabledInterestsUser();
    $post = FreightPost::factory()->for($owner)->create([
        'status' => FreightPostStatus::Active->value,
    ]);

    [$viewer] = disabledInterestsUser();

    $this->actingAs($viewer)
        ->postJson(route('freight.reveal', $post->ulid))
        ->assertOk()
        ->assertJsonPath('status', 'allowed');

    expect(FreightInterest::count())->toBe(0);
});

it('shares the flag with Inertia', function () {
    config(['marketplace.interests_enabled' => false]);

    $this->get('/freight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('marketplace.interests_enabled', false)
        );
});

it('keeps the interest routes working when the flag is on', function () {
    config(['marketplace.interests_enabled' => true]);

    [$owner] = disabledInterestsUser();

    $this->actingAs($owner)
        ->get(route('freight.my-interests'))
        ->assertOk();
});
