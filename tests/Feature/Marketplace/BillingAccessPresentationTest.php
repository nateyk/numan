<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Services\WorkspaceService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
});

function billingAccessMember(): array
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $workspace = app(WorkspaceService::class)->createPersonalWorkspace($user);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    return [$user->fresh(), $workspace];
}

it('distinguishes a free member from an expired subscriber on both billing pages', function () {
    [$user, $workspace] = billingAccessMember();

    $this->actingAs($user)->get(route('billing.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplaceAccess.status', 'free')
            ->where('marketplaceAccess.ends_at', null)
            ->etc());

    $subscription = MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'status' => 'expired',
        'provider' => 'chapa',
        'billing_interval' => 'weekly',
        'current_period_start' => now()->subDays(8),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->get(route('billing.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplaceAccess.status', 'expired')
            ->where('marketplaceAccess.plan', 'Standard')
            ->where('marketplaceAccess.billing_interval', 'weekly')
            ->where('currentMarketplacePlan', 'free')
            ->etc());

    $this->actingAs($user)->get(route('billing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplaceAccess.status', 'expired')
            ->where('marketplaceAccess.plan', 'Standard')
            ->etc());

    $subscription->update([
        'status' => 'active',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(7),
        'ends_at' => now()->addDays(7),
    ]);

    $this->actingAs($user)->get(route('billing.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplaceAccess.status', 'active')
            ->where('currentMarketplacePlan', 'standard')
            ->where('currentMarketplaceBillingPeriod', 'weekly')
            ->etc());
});

it('never exposes a canceled checkout attempt as a paid subscription', function () {
    [$user, $workspace] = billingAccessMember();

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'plan_id' => MarketplacePlan::where('slug', 'standard')->firstOrFail()->id,
        'status' => 'expired',
        'provider' => 'chapa',
        'billing_interval' => 'monthly',
        'current_period_start' => now()->subDays(31),
        'current_period_ends_at' => now()->subDay(),
        'ends_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->get(route('billing.plans'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('marketplaceAccess.status', 'expired')
            ->etc());
});
