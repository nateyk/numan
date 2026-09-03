<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EthiopiaMajorTownsSeeder extends Seeder
{
    /**
     * Run the database seeds — 934 Ethiopian major towns (idempotent upsert).
     */
    public function run(): void
    {
        $path = database_path('data/ethiopia-major-towns.json');

        if (! file_exists($path)) {
            $this->command?->warn("Missing {$path} — skipping Ethiopia towns seeder.");

            return;
        }

        $raw = file_get_contents($path);
        $json = json_decode($raw, true);

        $features = $json['features'] ?? [];

        $now = now();

        $rows = [];

        foreach ($features as $feature) {
            $props = $feature['properties'] ?? [];
            $name = trim((string) ($props['TOWN_NAME'] ?? ''));
            $region = trim((string) ($props['REGION'] ?? ''));
            $lat = $props['LAT'] ?? $feature['geometry']['coordinates'][1] ?? null;
            $lon = $props['LON'] ?? $feature['geometry']['coordinates'][0] ?? null;

            if ($name === '' || $lat === null || $lon === null) {
                continue;
            }

            $slug = Str::slug($name.'-'.$region);
            // Ensure uniqueness for duplicate town names across regions
            $baseSlug = $slug;
            $suffix = 1;
            while (isset($rows[$slug]) || DB::table('locations')->where('slug', $slug)->exists()) {
                // If DB already has this slug with same name/region, reuse it
                $existing = DB::table('locations')->where('slug', $slug)->where('name', $name)->where('region_name', $region)->first();
                if ($existing) {
                    break;
                }
                $slug = $baseSlug.'-'.$suffix++;
            }

            $rows[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'type' => 'town',
                'region_name' => $region !== '' ? $region : 'UNKNOWN',
                'latitude' => round((float) $lat, 7),
                'longitude' => round((float) $lon, 7),
                'is_active' => true,
                'is_major_town' => true,
                'population' => isset($props['POP']) ? (int) $props['POP'] : null,
                'elevation' => isset($props['ELEVATION']) ? (int) round((float) $props['ELEVATION']) : null,
                'wereda' => $props['WEREDA'] ?? null,
                'zone' => $props['ZONES'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Chunked upsert for performance
        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            DB::table('locations')->upsert(
                $chunk,
                ['slug'],
                ['name', 'region_name', 'latitude', 'longitude', 'is_active', 'is_major_town', 'population', 'elevation', 'wereda', 'zone', 'updated_at']
            );
        }

        $this->command?->info('Seeded '.count($rows).' Ethiopian major towns.');
    }
}
