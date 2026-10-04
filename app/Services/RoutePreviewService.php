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
            $response = Http::timeout(10)->get($url, [
                'overview' => 'full',
                'geometries' => 'geojson',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['routes'][0])) {
                    $route = $data['routes'][0];

                    if (isset($route['distance'], $route['duration'], $route['geometry']) && $route['distance'] > 0) {
                        return [
                            'distance_meters' => (int) round($route['distance']),
                            'duration_seconds' => (int) round($route['duration']),
                            'geometry' => $route['geometry'],
                            'provider' => 'osrm',
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // Fall through to straight-line fallback
        }

        // Fallback: straight-line (Haversine) distance estimate for regions OSRM doesn't cover
        return $this->straightLineEstimate($fromLat, $fromLng, $toLat, $toLng);
    }

    /**
     * Fallback: Haversine straight-line distance with estimated driving time.
     * Used when OSRM doesn't cover the region (e.g., Ethiopia, Djibouti).
     *
     * @return array{distance_meters:int,duration_seconds:int,geometry:array,provider:string}
     */
    private function straightLineEstimate(float $fromLat, float $fromLng, float $toLat, $toLng): array
    {
        $earthRadius = 6371000; // meters

        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($fromLat)) * cos(deg2rad($toLat)) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $straightLineMeters = $earthRadius * $c;

        // Apply a road factor of ~1.3 to approximate actual road distance
        $distanceMeters = (int) round($straightLineMeters * 1.3);

        // Assume average speed of 50 km/h for estimation
        $durationSeconds = (int) round(($distanceMeters / 1000) / 50 * 3600);

        // Simple straight-line geometry
        $geometry = [
            'type' => 'LineString',
            'coordinates' => [
                [$fromLng, $fromLat],
                [$toLng, $toLat],
            ],
        ];

        return [
            'distance_meters' => $distanceMeters,
            'duration_seconds' => $durationSeconds,
            'geometry' => $geometry,
            'provider' => 'straight_line_estimate',
        ];
    }
}
