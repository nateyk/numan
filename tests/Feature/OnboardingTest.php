<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceService;

/**
 * The workspace-onboarding wizard is gone; the account still gets a workspace.
 *
 * The wizard used to be the only thing that created a workspace and the only
 * thing that set `onboarded_at`. Both now happen at registration, so there is
 * nothing to gate behind a forced flow.
 */
it('does not gate the dashboard behind an onboarding flag', function () {
    $user = User::factory()->unonboarded()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

it('redirects the retired onboarding URL to the public profile', function () {
    $user = User::factory()->unonboarded()->create();

    $this->actingAs($user)->get('/onboarding')->assertRedirect('/public-profile');
});

it('creates a personal workspace at registration', function () {
    $user = User::factory()->create();
    app(WorkspaceService::class)->createPersonalWorkspace($user);

    expect($user->ownedWorkspaces()->count())->toBe(1)
        ->and($user->current_workspace_id)->not->toBeNull()
        ->and($user->fresh()->currentWorkspace->personal_workspace)->toBeTrue();
});

it('does not create an owned workspace when user joins via invitation', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $user = User::factory()->unonboarded()->create();

    $invitation = WorkspaceInvitation::create([
        'workspace_id' => $workspace->id,
        'email' => $user->email,
        'role' => 'member',
        'token' => 'onboarding-skip-token',
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($user)->post("/invitations/{$invitation->token}/accept");

    $user->refresh();

    $ownedWorkspaceCount = $user->ownedWorkspaces()->count();

    $this->actingAs($user)->get('/dashboard')->assertSuccessful();

    expect($user->fresh()->ownedWorkspaces()->count())->toBe($ownedWorkspaceCount);
});

it('lets a user with an incomplete profile reach account settings', function () {
    $user = User::factory()->unonboarded()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/settings/profile')->assertSuccessful();
});
