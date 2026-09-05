<?php

namespace Database\Seeders;

use App\Models\MarketplacePlan;
use Illuminate\Database\Seeder;

class MarketplacePlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = config('marketplace.plans');

        foreach ($plans as $slug => $data) {
            MarketplacePlan::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price_weekly_etb' => $data['price_weekly_etb'] ?? 0,
                    'price_monthly_etb' => $data['price_monthly_etb'] ?? 0,
                    'price_yearly_etb' => $data['price_yearly_etb'] ?? 0,
                    'is_active' => true,
                    'is_popular' => $data['popular'] ?? false,
                    'sort_order' => match ($slug) {
                        'weekly' => 1,
                        'free' => 2,
                        'pro' => 3,
                        'business' => 4,
                        default => 99,
                    },
                    'limits' => $data['limits'] ?? [],
                    'features' => $data['features'] ?? [],
                    'faq' => $data['faq'] ?? [],
                ]
            );
        }
    }
}
