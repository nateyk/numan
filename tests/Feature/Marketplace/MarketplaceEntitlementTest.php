<?php

use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\MarketplaceEntitlementService;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

it('limits active freight posts per plan', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $service = app(MarketplaceEntitlementService::class);

    // Free plan = 3 active
    expect($service->getLimitValue($workspace, 'active_freight_posts'))->toBe(3);
    expect($service->canCreateFreightPost($workspace))->toBeTrue();

    // Create 3 active posts
    foreach (range(1, 3) as $i) {
        FreightPost::create([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'status' => 'Active',
            'cargo_description' => 'test',
        ]);
    }

    expect($service->canCreateFreightPost($workspace))->toBeFalse();
    expect($service->remainingActivePosts($workspace))->toBe(0);
});

it('allows unlimited for business', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    // Assign business plan via DB subscription
    $plan = MarketplacePlan::where('slug', 'business')->first();
    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    $service = app(MarketplaceEntitlementService::class);
    expect($service->canCreateFreightPost($workspace))->toBeTrue();
    expect($service->remainingActivePosts($workspace))->toBe(-1);
});

it('enforces interests per month', function () {
    $user = User::factory()->create();
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->update(['current_workspace_id' => $workspace->id]);

    $service = app(MarketplaceEntitlementService::class);
    expect($service->canExpressInterest($workspace))->toBeTrue();

    // Simulate 5 interests (free limit =5)
    $otherUser = User::factory()->create();
    $post = FreightPost::create(['ulid' => (string) Str::ulid(), 'user_id' => $otherUser->id, 'workspace_id' => $workspace->id, 'status' => 'Active', 'cargo_description' => 'x']);
    foreach (range(1, 5) as $i) {
        FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'message' => 'hi',
            'contact_name' => 'Test',
            'contact_phone' => '+25190000000'.$i,
            'contact_email' => "test{$i}@example.com",
        ]);
    }

    expect($service->canExpressInterest($workspace))->toBeFalse();
});
