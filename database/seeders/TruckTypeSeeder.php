<?php

namespace Database\Seeders;

use App\Models\TruckType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TruckTypeSeeder extends Seeder
{
    public function run(): void
    {
        $truckTypes = [
            ['name' => 'Isuzu N-Series', 'icon' => 'truck', 'description' => 'Light duty, 3-5 tons', 'sort_order' => 1],
            ['name' => 'Sino Truck Howo', 'icon' => 'truck', 'description' => 'Heavy duty, 20+ tons', 'sort_order' => 2],
            ['name' => 'Flatbed', 'icon' => 'truck', 'description' => 'Open flatbed for construction', 'sort_order' => 3],
            ['name' => 'Semi-trailer', 'icon' => 'truck', 'description' => 'Long haul, 30 tons', 'sort_order' => 4],
            ['name' => 'Tanker', 'icon' => 'truck', 'description' => 'Liquid transport', 'sort_order' => 5],
            ['name' => 'Refrigerated', 'icon' => 'truck', 'description' => 'Cold chain, food', 'sort_order' => 6],
            ['name' => 'Dump Truck', 'icon' => 'truck', 'description' => 'Bulk, construction', 'sort_order' => 7],
            ['name' => 'Lowbed', 'icon' => 'truck', 'description' => 'Heavy equipment', 'sort_order' => 8],
            ['name' => 'Pickup', 'icon' => 'truck', 'description' => 'Small cargo, 1-2 tons', 'sort_order' => 9],
        ];

        foreach ($truckTypes as $data) {
            TruckType::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'description' => $data['description'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }
}
