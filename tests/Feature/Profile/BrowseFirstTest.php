<?php

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Browse first, set up later.
 *
 * A new account can look at freight and carriers before it has a profile, and
 * nothing forces a wizard between signing in and reading the board. The profile
 * is created when the person chooses to post, and every authenticated surface
 * can still rely on a workspace existing.
 */
uses(RefreshDatabase::class);

it('lets a signed-in user with no profile browse freight and redirects the retired directory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/freight')->assertOk();
    $this->actingAs($user)->get('/transporters')->assertRedirect('/freight');
});

it('redirects onboarding to the public profile page', function () {
    $this->actingAs(User::factory()->create())->get('/onboarding')->assertRedirect('/public-profile');
});

it('creates a personal workspace for a user who has none before posting', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    Profile::factory()->for($user)->create([
        'display_name' => 'Consignor',
        'phone' => '+251911000001',
        'posts_freight' => true,
    ]);

    $user->workspaces()->detach();
    $user->forceFill(['current_workspace_id' => null])->save();

    $this->actingAs($user->fresh())->post('/my-freight')->assertRedirect();

    expect($user->fresh()->currentWorkspace)->not->toBeNull()
        ->and($user->fresh()->workspaces()->count())->toBe(1);
});
