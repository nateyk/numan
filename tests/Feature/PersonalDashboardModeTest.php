<?php

use App\Models\User;
use App\Models\Workspace;

test('personal mode keeps the dashboard available for an existing workspace account', function () {
    config()->set('features.workspace.ui', false);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addUser($user, 'owner');
    $user->switchWorkspace($workspace);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('workspace_ui_enabled', false)
            ->where('workspace.id', $workspace->id)
        );
});

test('workspace interface can be re-enabled without changing workspace ownership', function () {
    config()->set('features.workspace.ui', true);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->addUser($user, 'owner');
    $user->switchWorkspace($workspace);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('workspace_ui_enabled', true)
            ->where('workspace.id', $workspace->id)
        );
});
