<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RoutePreviewService
{
    /**
     * @return array{distance_meters:int,duration_seconds:int,geometry:array,provider:string}|null
     */
    public function preview(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        $baseUrl = config('services.osrm.url') ?? env('OSRM_URL', 'https://router.project-osrm.org');

        $baseUrl = rtrim((string) $baseUrl, '/');
        $url = "{$baseUrl}/route/v1/driving/{$fromLng},{$fromLat};{$toLng},{$toLat}";

        try {
            $response = Http::timeout(5)->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            if (! isset($data['routes'][0])) {
                return null;
            }

            $route = $data['routes'][0];

            if (! isset($route['distance'], $route['duration'], $route['geometry'])) {
                return null;
            }

            return [
                'distance_meters' => (int) round($route['distance']),
                'duration_seconds' => (int) round($route['duration']),
                'geometry' => $route['geometry'],
                'provider' => 'osrm',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
