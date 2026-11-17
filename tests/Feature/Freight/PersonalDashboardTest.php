<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\User;
use App\Services\WorkspaceService;

beforeEach(function () {
    config()->set('features.workspace.ui', false);
});

it('shows accurate owner-only freight counts and recent posts', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $otherUser = User::factory()->create();
    app(WorkspaceService::class)->createPersonalWorkspace($otherUser);

    FreightPost::factory()->for($user)->draft()->create(['item_name' => 'Personal draft']);
    FreightPost::factory()->for($user)->active()->create(['item_name' => 'Published load']);
    FreightPost::factory()->for($user)->active()->create([
        'item_name' => 'Hidden load',
        'hidden_at' => now(),
    ]);
    $arrived = FreightPost::factory()->for($user)->create([
        'item_name' => 'Delivered load',
        'status' => FreightPostStatus::Closed->value,
        'arrived_at' => now(),
    ]);

    $otherPost = FreightPost::factory()->for($otherUser)->active()->create([
        'item_name' => 'Another consignor load',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('freightStats.total', 4)
        ->where('freightStats.active', 2)
        ->where('freightStats.drafts', 1)
        ->where('freightStats.arrived', 1)
        ->has('recentFreight', 4)
        ->where('recentFreight.0.ulid', $arrived->ulid)
        ->where('recentFreight.0.status', 'arrived')
        ->missing('recentFreight.0.user_id')
    );
    $response->assertDontSee($otherPost->ulid);
});

it('provides an accurate empty dashboard without placeholder totals', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('freightStats.total', 0)
            ->where('freightStats.active', 0)
            ->where('freightStats.drafts', 0)
            ->where('freightStats.arrived', 0)
            ->has('recentFreight', 0)
        );
});

it('redirects legacy workspace usage to the freight dashboard in single-profile mode', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $this->actingAs($user)->get('/usage')->assertRedirect('/dashboard');
});
