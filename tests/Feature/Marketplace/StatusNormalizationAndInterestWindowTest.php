<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use App\Models\MarketplaceSubscription;
use App\Models\User;
use App\Models\Workspace;
use App\Services\MarketplaceEntitlementService;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MarketplacePlanSeeder::class);
    $this->entitlement = app(MarketplaceEntitlementService::class);
});

/**
 * Load the status normalisation migration and run it.
 */
function runStatusNormalizationMigration(): void
{
    $migration = require database_path('migrations/2026_10_03_000005_normalize_status_columns_to_enum_values.php');
    $migration->up();
}

it('the normalisation migration exists as a file', function () {
    expect(file_exists(database_path('migrations/2026_10_03_000005_normalize_status_columns_to_enum_values.php')))->toBeTrue();
});

it('lower-cases a legacy capitalised status in place', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $postId = DB::table('freight_posts')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => 'Active', // legacy capitalised value
        'cargo_description' => 'legacy row',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('freight_posts')->where('id', $postId)->value('status'))->toBe('Active');

    runStatusNormalizationMigration();

    expect(DB::table('freight_posts')->where('id', $postId)->value('status'))->toBe('active');
});

it('leaves already lowercase statuses untouched and deletes nothing', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $ids = [];
    foreach (['draft', 'submitted', 'Active'] as $status) {
        $ids[] = DB::table('freight_posts')->insertGetId([
            'ulid' => (string) Str::ulid(),
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'status' => $status,
            'cargo_description' => 'row',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    runStatusNormalizationMigration();

    $statuses = DB::table('freight_posts')->whereIn('id', $ids)->pluck('status', 'id')->all();

    expect($statuses[$ids[0]])->toBe('draft');
    expect($statuses[$ids[1]])->toBe('submitted');
    expect($statuses[$ids[2]])->toBe('active');

    // Nothing removed.
    expect(DB::table('freight_posts')->whereIn('id', $ids)->count())->toBe(3);
});

it('leaves an unrecognised status value untouched', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

    $postId = DB::table('freight_posts')->insertGetId([
        'ulid' => (string) Str::ulid(),
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => 'MysteryState',
        'cargo_description' => 'row',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runStatusNormalizationMigration();

    expect(DB::table('freight_posts')->where('id', $postId)->value('status'))->toBe('MysteryState');
});

it('normalising makes the live post limit actually count legacy rows', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->refresh();
    $workspace->addUser($user, 'owner');

    DB::table('freight_posts')->insert([
        'ulid' => (string) Str::ulid(),
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => 'Active',
        'cargo_description' => 'legacy active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    runStatusNormalizationMigration();

    // The free limit is 1 live post, so one legacy row now fills it.
    expect($this->entitlement->livePostCount($workspace->fresh()))->toBe(1);
    expect($this->entitlement->canCreateFreightPost($workspace->fresh()))->toBeFalse();
});

it('standard declares an explicit 7 day interest window', function () {
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    expect($plan->limits)->toHaveKey('interest_window_days');
    expect($plan->limits['interest_window_days'])->toBe(7);
});

it('free settings carry an explicit interest window', function () {
    expect(MarketplaceSetting::where('key', 'free_interest_window_days')->value('value'))->toBe(7);
});

it('a free workspace uses a 7 day window', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    expect($this->entitlement->interestWindowDays($workspace))->toBe(7);
});

it('adding interests_per_month to standard does not change its window', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $plan->update([
        'limits' => array_merge($plan->limits, ['interests_per_month' => 3]),
    ]);

    $limits = $plan->fresh()->limits;
    expect($limits)->toHaveKey('interests_per_month');

    // The window must stay at the explicit 7, not switch to 30.
    expect($this->entitlement->interestWindowDays($workspace))->toBe(7);
});

it('removing the explicit window falls back to the free setting, not to 30', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    MarketplaceSetting::where('key', 'free_interest_window_days')->update(['value' => 14]);

    expect($this->entitlement->interestWindowDays($workspace))->toBe(14);
});

it('an archived plan with a 30 day window is honoured', function () {
    [$user, $workspace] = marketplaceTestUserWithWorkspace();

    $legacy = MarketplacePlan::create([
        'slug' => 'legacy-30',
        'name' => 'Legacy 30',
        'price_monthly_etb' => 999,
        'is_active' => false,
        'limits' => [
            'active_freight_posts' => 25,
            'interests_per_month' => 10,
            'interest_window_days' => 30,
        ],
    ]);

    MarketplaceSubscription::create([
        'workspace_id' => $workspace->id,
        'plan_id' => $legacy->id,
        'user_id' => $user->id,
        'status' => 'active',
        'billing_interval' => 'monthly',
        'current_period_start' => now(),
        'current_period_ends_at' => now()->addDays(30),
        'ends_at' => now()->addDays(30),
        'provider' => 'chapa',
    ]);

    expect($this->entitlement->interestWindowDays($workspace))->toBe(30);
});

it('admin editing other limits does not wipe the interest window', function () {
    $admin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($admin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
        'limits' => ['active_freight_posts' => 5, 'interests_per_week' => 9],
    ])->assertRedirect();

    $limits = MarketplacePlan::find($plan->id)->limits;

    // Editable keys applied.
    expect($limits['active_freight_posts'])->toBe(5);
    expect($limits['interests_per_week'])->toBe(9);
    // Non-editable structural keys preserved.
    expect($limits['interest_window_days'])->toBe(7);
});

it('admin cannot set the interest window through plan validation', function () {
    $admin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);
    $plan = MarketplacePlan::where('slug', 'standard')->first();

    $this->actingAs($admin)->put("/admin/marketplace-plans/{$plan->slug}", [
        'slug' => 'standard',
        'name' => 'Standard',
        'price_monthly_etb' => 449,
        'price_yearly_etb' => 3999,
        'limits' => ['active_freight_posts' => 5, 'interest_window_days' => 30],
    ])->assertRedirect();

    // The attempted change to the window is discarded.
    expect(MarketplacePlan::find($plan->id)->limits['interest_window_days'])->toBe(7);
});

it('admin can still edit the free interest window setting', function () {
    $admin = User::factory()->create(['is_superadmin' => true, 'email_verified_at' => now()]);

    $this->actingAs($admin)->put('/admin/marketplace-settings', [
        'free_active_posts' => 1,
        'free_interests_per_week' => 3,
        'free_contact_reveals_per_week' => 3,
        'free_interest_window_days' => 14,
    ])->assertRedirect();

    expect(MarketplaceSetting::where('key', 'free_interest_window_days')->value('value'))->toBe(14);
});
