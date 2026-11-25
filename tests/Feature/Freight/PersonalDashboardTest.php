<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;

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
    FreightPost::factory()->for($user)->active()->create([
        'item_name' => 'Past pickup',
        'pickup_date' => now()->subDays(10)->toDateString(),
        'pickup_date_flexibility_days' => 0,
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
        ->where('freightStats.total', 5)
        ->where('freightStats.active', 3)
        ->where('freightStats.drafts', 1)
        ->where('freightStats.arrived', 1)
        ->where('availableFreight', 2)
        ->has('recentFreight', 5)
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
            ->where('availableFreight', 0)
            ->has('recentFreight', 0)
        );
});

it('redirects legacy workspace usage to the freight dashboard in single-profile mode', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->switchWorkspace($workspace);

    $this->actingAs($user)->get('/usage')->assertRedirect('/dashboard');
});

it('does not expose marketplace availability to an expired paid member on dashboard', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $viewer = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($viewer);
    $viewer->switchWorkspace($workspace);

    $owner = User::factory()->create();
    FreightPost::factory()->for($owner)->active()->create(['item_name' => 'Other member freight']);

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'user_id' => $viewer->id,
        'status' => 'expired',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(31),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
        'provider' => 'chapa',
    ]);

    $this->actingAs($viewer)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('freightAccessLocked', true)
            ->where('availableFreight', null)
            ->has('recentFreight', 0));

    $subscription->update([
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
    ]);

    $this->actingAs($viewer)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('freightAccessLocked', false)
            ->where('availableFreight', 1));
});
