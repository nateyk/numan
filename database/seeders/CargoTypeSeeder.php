<?php

namespace Database\Seeders;

use App\Models\CargoType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CargoTypeSeeder extends Seeder
{
    public function run(): void
    {
        $cargoTypes = [
            ['name' => 'General Cargo', 'sort_order' => 1],
            ['name' => 'Construction Materials', 'sort_order' => 2],
            ['name' => 'Agricultural Products', 'sort_order' => 3],
            ['name' => 'Food & Beverages', 'sort_order' => 4],
            ['name' => 'Electronics', 'sort_order' => 5],
            ['name' => 'Textiles & Garments', 'sort_order' => 6],
            ['name' => 'Vehicles & Parts', 'sort_order' => 7],
            ['name' => 'Bulk Goods', 'sort_order' => 8],
            ['name' => 'Chemicals', 'sort_order' => 9],
            ['name' => 'Cold Chain', 'sort_order' => 10],
        ];

        foreach ($cargoTypes as $data) {
            CargoType::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }
}
