<?php

use App\Enums\FreightPostStatus;
use App\Models\FreightInterest;
use App\Models\FreightPost;
use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketplaceEntitlementService;
use App\Services\MarketplaceSettingsService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->entitlement = app(MarketplaceEntitlementService::class);
});

/**
 * Create a freight post using enum status values.
 */
function makeFreightPost(int $workspaceId, int $userId, FreightPostStatus $status): FreightPost
{
    return FreightPost::create([
        'ulid' => (string) Str::ulid(),
        'user_id' => $userId,
        'workspace_id' => $workspaceId,
        'status' => $status->value,
        'cargo_description' => 'test',
    ]);
}

it('gives a free workspace the configured free limits', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    expect($this->entitlement->getPlanForWorkspace($workspace)['slug'])->toBe('free');
    expect($this->entitlement->getLimitValue($workspace, 'active_freight_posts'))->toBe(1);
    expect($this->entitlement->getLimitValue($workspace, 'interests_per_week'))->toBe(3);
    expect($this->entitlement->getLimitValue($workspace, 'contact_reveals_per_week'))->toBe(3);
});

it('reads free limits from settings so an admin change applies immediately', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    expect($this->entitlement->getLimitValue($workspace, 'active_freight_posts'))->toBe(1);

    MarketplaceSetting::where('key', 'free_active_posts')->update(['value' => 5]);

    expect(app(MarketplaceEntitlementService::class)->getLimitValue($workspace, 'active_freight_posts'))->toBe(5);
});

it('falls back to config defaults when a setting row is missing', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    MarketplaceSetting::query()->delete();

    $service = app(MarketplaceEntitlementService::class);

    // Must not crash and must fall back to 1 / 3 / 3.
    expect($service->getLimitValue($workspace, 'active_freight_posts'))->toBe(1);
    expect($service->getLimitValue($workspace, 'interests_per_week'))->toBe(3);
    expect($service->getLimitValue($workspace, 'contact_reveals_per_week'))->toBe(3);
});

it('blocks a free user from creating a second live post', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    expect($this->entitlement->canCreateFreightPost($workspace))->toBeTrue();

    makeFreightPost($workspace->id, $user->id, FreightPostStatus::Active);

    expect($this->entitlement->canCreateFreightPost($workspace))->toBeFalse();
    expect($this->entitlement->remainingLivePosts($workspace))->toBe(0);
});

it('counts a submitted post as live so posts cannot be queued for review', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    makeFreightPost($workspace->id, $user->id, FreightPostStatus::Submitted);

    expect($this->entitlement->livePostCount($workspace))->toBe(1);
    expect($this->entitlement->canCreateFreightPost($workspace))->toBeFalse();
});

it('counts an under review post as live', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    makeFreightPost($workspace->id, $user->id, FreightPostStatus::UnderReview);

    expect($this->entitlement->livePostCount($workspace))->toBe(1);
    expect($this->entitlement->canCreateFreightPost($workspace))->toBeFalse();
});

it('does not count drafts toward the limit', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    makeFreightPost($workspace->id, $user->id, FreightPostStatus::Draft);
    makeFreightPost($workspace->id, $user->id, FreightPostStatus::Draft);

    expect($this->entitlement->livePostCount($workspace))->toBe(0);
    expect($this->entitlement->canCreateFreightPost($workspace))->toBeTrue();
});

it('frees the slot when the first post is closed', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $post = makeFreightPost($workspace->id, $user->id, FreightPostStatus::Active);
    expect($this->entitlement->canCreateFreightPost($workspace))->toBeFalse();

    $post->update(['status' => FreightPostStatus::Closed->value]);

    expect($this->entitlement->canCreateFreightPost($workspace))->toBeTrue();
    expect($this->entitlement->remainingLivePosts($workspace))->toBe(1);
});

it('frees the slot when the first post expires', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $post = makeFreightPost($workspace->id, $user->id, FreightPostStatus::Active);
    expect($this->entitlement->canCreateFreightPost($workspace))->toBeFalse();

    $post->update(['status' => FreightPostStatus::Expired->value]);

    expect($this->entitlement->canCreateFreightPost($workspace))->toBeTrue();
});

it('limits a free user to 3 interests in a rolling 7 days', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);
    $post = makeFreightPost($otherWorkspace->id, $other->id, FreightPostStatus::Active);

    expect($this->entitlement->canExpressInterest($workspace))->toBeTrue();

    foreach (range(1, 3) as $i) {
        FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'message' => 'hi',
            'contact_name' => 'Test',
            'contact_phone' => '+25190000000'.$i,
            'contact_email' => "t{$i}@example.com",
        ]);
    }

    expect($this->entitlement->canExpressInterest($workspace))->toBeFalse();
});

it('ignores interests older than the 7 day window for free users', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);
    $post = makeFreightPost($otherWorkspace->id, $other->id, FreightPostStatus::Active);

    foreach (range(1, 3) as $i) {
        FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'message' => 'old',
            'contact_name' => 'Test',
            'contact_phone' => '+25190000000'.$i,
            'contact_email' => "old{$i}@example.com",
        ]);
        FreightInterest::where('user_id', $user->id)->where('contact_email', "old{$i}@example.com")
            ->update(['created_at' => now()->subDays(8)]);
    }

    expect($this->entitlement->canExpressInterest($workspace))->toBeTrue();
});

it('gives the standard plan unlimited limits', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    $service = app(MarketplaceEntitlementService::class);

    expect($service->getLimitValue($workspace, 'active_freight_posts'))->toBe(-1);
    expect($service->getLimitValue($workspace, 'interests_per_week'))->toBe(-1);
    expect($service->getLimitValue($workspace, 'contact_reveals_per_week'))->toBe(-1);
    expect($service->remainingLivePosts($workspace))->toBe(-1);
    expect($service->canCreateFreightPost($workspace))->toBeTrue();
    expect($service->canExpressInterest($workspace))->toBeTrue();
});

it('uses a 30 day interest window for archived plans carrying interests_per_month', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    // A retired plan, archived but still referenced by an existing subscriber.
    $legacyPlan = MarketplacePlan::create([
        'slug' => 'legacy-monthly',
        'name' => 'Legacy Monthly',
        'price_weekly_etb' => 0,
        'price_monthly_etb' => 999,
        'price_yearly_etb' => 9990,
        'is_active' => false,
        'limits' => [
            'active_freight_posts' => 25,
            'interests_per_month' => 10,
        ],
    ]);

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $legacyPlan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    $service = app(MarketplaceEntitlementService::class);

    expect($service->getPlanForWorkspace($workspace)['slug'])->toBe('legacy-monthly');
    expect($service->interestWindowDays($workspace))->toBe(30);
    expect($service->getLimitValue($workspace, 'interests_per_month'))->toBe(10);

    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);
    $post = makeFreightPost($otherWorkspace->id, $other->id, FreightPostStatus::Active);

    // 10 interests spread over 10 days: inside a 30 day window, outside a 7 day one.
    foreach (range(1, 10) as $i) {
        FreightInterest::create([
            'freight_post_id' => $post->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'message' => 'hi',
            'contact_name' => 'Test',
            'contact_phone' => '+25190000000'.$i,
            'contact_email' => "l{$i}@example.com",
        ]);
        FreightInterest::where('user_id', $user->id)->where('contact_email', "l{$i}@example.com")
            ->update(['created_at' => now()->subDays($i)]);
    }

    expect($service->canExpressInterest($workspace))->toBeFalse();
});

it('keeps an archived plan subscriber entitled after the plan is deactivated', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $legacyPlan = MarketplacePlan::create([
        'slug' => 'legacy-active',
        'name' => 'Legacy Active',
        'price_monthly_etb' => 999,
        'is_active' => false,
        'limits' => ['active_freight_posts' => 25],
    ]);

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $legacyPlan->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(10),
        'ends_at' => now()->addDays(10),
        'provider' => 'chapa',
    ]);

    $service = app(MarketplaceEntitlementService::class);

    expect($legacyPlan->is_active)->toBeFalse();
    expect($service->getLimitValue($workspace, 'active_freight_posts'))->toBe(25);
    expect($service->getPlanForWorkspace($workspace)['slug'])->toBe('legacy-active');
});

it('reports usage with live posts and the correct interest window', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    makeFreightPost($workspace->id, $user->id, FreightPostStatus::Active);

    $usage = app(MarketplaceEntitlementService::class)->usageOverview($workspace);

    expect($usage['active_freight_posts']['count'])->toBe(1);
    expect($usage['active_freight_posts']['limit'])->toBe(1);
    expect($usage['active_freight_posts']['remaining'])->toBe(0);
    expect($usage['interests_per_week']['limit'])->toBe(3);
    expect($usage['contact_reveals_per_week']['limit'])->toBe(3);
});

it('exposes free limits through the settings service', function () {
    $settings = app(MarketplaceSettingsService::class)->all();

    expect($settings)->toHaveKeys(['free_active_posts', 'free_interests_per_week', 'free_contact_reveals_per_week']);
    expect($settings['free_active_posts'])->toBe(1);
});
