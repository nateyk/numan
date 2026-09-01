<?php

use App\Services\RoutePreviewService;
use Illuminate\Support\Facades\Http;

it('returns null when OSRM unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));
    expect(app(RoutePreviewService::class)->preview(9, 38, 7, 38))->toBeNull();
});

it('parses OSRM success response', function () {
    Http::fake(fn () => Http::response([
        'routes' => [[
            'distance' => 275000,
            'duration' => 17100,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[38.74, 9.03], [38.42, 7.49]]],
        ]],
        'code' => 'Ok',
    ], 200));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    expect($result)->not->toBeNull()
        ->and($result['distance_meters'])->toBe(275000)
        ->and($result['duration_seconds'])->toBe(17100)
        ->and($result['provider'])->toBe('osrm');
});

it('returns null when routes missing', function () {
    Http::fake(fn () => Http::response(['routes' => []], 200));
    expect(app(RoutePreviewService::class)->preview(9, 38, 7, 38))->toBeNull();
});
