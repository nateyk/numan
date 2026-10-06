<?php

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use Database\Seeders\MarketplacePlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the standard plan at the target prices', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $plan = MarketplacePlan::where('slug', 'standard')->first();

    expect($plan)->not->toBeNull();
    expect((float) $plan->price_weekly_etb)->toBe(149.0);
    expect((float) $plan->price_monthly_etb)->toBe(449.0);
    expect((float) $plan->price_yearly_etb)->toBe(3999.0);
    expect($plan->is_active)->toBeTrue();
});

it('gives every duration identical features and unlimited limits', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $plan = MarketplacePlan::where('slug', 'standard')->first();

    expect($plan->limits)->toBe([
        'active_freight_posts' => -1,
        'interests_per_week' => -1,
        'contact_reveals_per_week' => -1,
        'interest_window_days' => 7,
    ]);
});

it('seeds only the standard plan, not the retired plans', function () {
    $this->seed(MarketplacePlanSeeder::class);

    expect(MarketplacePlan::where('slug', 'standard')->exists())->toBeTrue();

    foreach (['weekly', 'free', 'pro', 'business'] as $retired) {
        expect(MarketplacePlan::where('slug', $retired)->exists())->toBeFalse();
    }
});

it('seeds the free limit settings', function () {
    $this->seed(MarketplacePlanSeeder::class);

    expect(MarketplaceSetting::where('key', 'free_active_posts')->value('value'))->toBe(5);
    expect(MarketplaceSetting::where('key', 'free_interests_per_week')->value('value'))->toBe(3);
    expect(MarketplaceSetting::where('key', 'free_contact_reveals_per_week')->value('value'))->toBe(3);
});

it('seeding twice does not revert an admin edited price', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $plan = MarketplacePlan::where('slug', 'standard')->first();
    $plan->update([
        'price_weekly_etb' => 199,
        'price_monthly_etb' => 799,
        'price_yearly_etb' => 6999,
    ]);

    // Run the seeder again.
    $this->seed(MarketplacePlanSeeder::class);

    $plan->refresh();
    expect((float) $plan->price_weekly_etb)->toBe(199.0);
    expect((float) $plan->price_monthly_etb)->toBe(799.0);
    expect((float) $plan->price_yearly_etb)->toBe(6999.0);
});

it('seeding twice does not revert an admin edited free limit', function () {
    $this->seed(MarketplacePlanSeeder::class);

    MarketplaceSetting::where('key', 'free_active_posts')->update(['value' => 9]);

    $this->seed(MarketplacePlanSeeder::class);

    expect(MarketplaceSetting::where('key', 'free_active_posts')->value('value'))->toBe(9);
});

it('seeding twice never re-activates an archived plan', function () {
    $this->seed(MarketplacePlanSeeder::class);

    $plan = MarketplacePlan::where('slug', 'standard')->first();
    $plan->update(['is_active' => false]);

    $this->seed(MarketplacePlanSeeder::class);

    expect(MarketplacePlan::find($plan->id)->is_active)->toBeFalse();
});

it('archives the retired plans through the migration, not the seeder', function () {
    // Simulate legacy rows created before the archive migration.
    $slugs = ['weekly', 'free', 'pro', 'business'];

    foreach ($slugs as $slug) {
        MarketplacePlan::create([
            'slug' => $slug,
            'name' => ucfirst($slug),
            'price_monthly_etb' => 100,
            'price_yearly_etb' => 1000,
            'is_active' => true,
        ]);
    }

    $migration = require database_path('migrations/2026_10_03_000004_archive_legacy_marketplace_plans.php');
    $migration->up();

    foreach ($slugs as $slug) {
        $plan = MarketplacePlan::where('slug', $slug)->first();
        expect($plan->is_active)->toBeFalse();
        // Archived, never deleted.
        expect($plan->trashed())->toBeFalse();
    }

    expect(MarketplacePlan::whereIn('slug', $slugs)->count())->toBe(4);
});
