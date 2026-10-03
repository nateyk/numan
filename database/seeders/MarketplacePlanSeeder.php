<?php

namespace Database\Seeders;

use App\Models\MarketplacePlan;
use App\Models\MarketplaceSetting;
use Illuminate\Database\Seeder;

class MarketplacePlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('marketplace.plans', []) as $slug => $data) {
            $plan = MarketplacePlan::withTrashed()->firstWhere('slug', $slug);

            if ($plan === null) {
                MarketplacePlan::create([
                    'slug' => $slug,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price_weekly_etb' => $data['price_weekly_etb'] ?? 0,
                    'price_monthly_etb' => $data['price_monthly_etb'] ?? 0,
                    'price_yearly_etb' => $data['price_yearly_etb'] ?? 0,
                    'is_active' => true,
                    'is_popular' => $data['popular'] ?? false,
                    'sort_order' => $data['sort_order'] ?? 1,
                    'limits' => $data['limits'] ?? [],
                    'features' => $data['features'] ?? [],
                    'faq' => $data['faq'] ?? [],
                ]);

                continue;
            }

            // Existing plan: never touch prices. An admin may have set them,
            // and re-seeding must not silently revert a price change.
            // is_active is left alone so retired plans stay archived.
        }

        foreach (config('marketplace.free_limits', []) as $key => $value) {
            MarketplaceSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
