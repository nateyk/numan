<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $towns = [
                ['name' => 'Addis Ababa', 'type' => 'city', 'region_name' => 'Addis Ababa', 'latitude' => 9.03, 'longitude' => 38.74],
                ['name' => 'Dire Dawa', 'type' => 'city', 'region_name' => 'Dire Dawa', 'latitude' => 9.59, 'longitude' => 41.86],
                ['name' => 'Hawassa', 'type' => 'city', 'region_name' => 'Sidama', 'latitude' => 7.05, 'longitude' => 38.47],
                ['name' => 'Adama', 'type' => 'city', 'region_name' => 'Oromia', 'latitude' => 8.54, 'longitude' => 39.27],
                ['name' => 'Bahir Dar', 'type' => 'city', 'region_name' => 'Amhara', 'latitude' => 11.59, 'longitude' => 37.39],
                ['name' => 'Gondar', 'type' => 'city', 'region_name' => 'Amhara', 'latitude' => 12.60, 'longitude' => 37.47],
                ['name' => 'Mekelle', 'type' => 'city', 'region_name' => 'Tigray', 'latitude' => 13.49, 'longitude' => 39.47],
                ['name' => 'Jimma', 'type' => 'city', 'region_name' => 'Oromia', 'latitude' => 7.67, 'longitude' => 36.83],
                ['name' => 'Kombolcha', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 11.08, 'longitude' => 39.73],
                ['name' => 'Dessie', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 11.13, 'longitude' => 39.63],
                ['name' => 'Shashamane', 'type' => 'town', 'region_name' => 'Oromia', 'latitude' => 7.20, 'longitude' => 38.60],
                ['name' => 'Arba Minch', 'type' => 'town', 'region_name' => 'South Ethiopia', 'latitude' => 6.03, 'longitude' => 37.55],
                ['name' => 'Hosaena', 'type' => 'town', 'region_name' => 'Central Ethiopia', 'latitude' => 7.55, 'longitude' => 37.85],
                ['name' => 'Nekemte', 'type' => 'town', 'region_name' => 'Oromia', 'latitude' => 9.08, 'longitude' => 36.55],
                ['name' => 'Debre Birhan', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 9.68, 'longitude' => 39.53],
                ['name' => 'Asella', 'type' => 'town', 'region_name' => 'Oromia', 'latitude' => 7.95, 'longitude' => 39.12],
                ['name' => 'Debre Markos', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 10.35, 'longitude' => 37.73],
                ['name' => 'Adigrat', 'type' => 'town', 'region_name' => 'Tigray', 'latitude' => 14.28, 'longitude' => 39.46],
                ['name' => 'Ziway', 'type' => 'town', 'region_name' => 'Oromia', 'latitude' => 7.93, 'longitude' => 38.70],
                ['name' => 'Butajira', 'type' => 'town', 'region_name' => 'Central Ethiopia', 'latitude' => 8.12, 'longitude' => 38.38],
                ['name' => 'Woldiya', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 11.83, 'longitude' => 39.59],
                ['name' => 'Assosa', 'type' => 'town', 'region_name' => 'Benishangul-Gumuz', 'latitude' => 10.06, 'longitude' => 34.53],
                ['name' => 'Jijiga', 'type' => 'town', 'region_name' => 'Somali', 'latitude' => 9.35, 'longitude' => 42.79],
                ['name' => 'Semera', 'type' => 'town', 'region_name' => 'Afar', 'latitude' => 11.79, 'longitude' => 41.01],
                ['name' => 'Gambela', 'type' => 'town', 'region_name' => 'Gambela', 'latitude' => 8.25, 'longitude' => 34.59],
                ['name' => 'Harar', 'type' => 'city', 'region_name' => 'Harari', 'latitude' => 9.31, 'longitude' => 42.12],
                ['name' => 'Axum', 'type' => 'town', 'region_name' => 'Tigray', 'latitude' => 14.12, 'longitude' => 38.72],
                ['name' => 'Lalibela', 'type' => 'town', 'region_name' => 'Amhara', 'latitude' => 12.03, 'longitude' => 39.04],
                ['name' => 'Bale Robe', 'type' => 'town', 'region_name' => 'Oromia', 'latitude' => 7.12, 'longitude' => 40.00],
                ['name' => 'Mizan Teferi', 'type' => 'town', 'region_name' => 'South West Ethiopia', 'latitude' => 6.99, 'longitude' => 35.58],
            ];

            foreach ($towns as $town) {
                Location::firstOrCreate(
                    ['name' => $town['name'], 'region_name' => $town['region_name']],
                    [
                        'type' => $town['type'],
                        'latitude' => $town['latitude'],
                        'longitude' => $town['longitude'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
