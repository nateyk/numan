<?php

use App\Services\RoutePreviewService;
use Illuminate\Support\Facades\Http;

it('returns a straight-line estimate when OSRM is unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    // OSRM has no road data for Ethiopia or Djibouti, so returning null left
    // the primary market with no route preview at all. The estimate is
    // explicitly labelled by provider so nothing can mistake it for a road
    // distance.
    expect($result)->not->toBeNull()
        ->and($result['provider'])->toBe('straight_line_estimate')
        ->and($result['distance_meters'])->toBeGreaterThan(0)
        ->and($result['duration_seconds'])->toBeGreaterThan(0);
});

it('falls back to a straight-line estimate when the route array is empty', function () {
    Http::fake(fn () => Http::response(['routes' => []], 200));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    expect($result)->not->toBeNull()
        ->and($result['provider'])->toBe('straight_line_estimate');
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

it('prefers the OSRM route over the fallback', function () {
    Http::fake(fn () => Http::response([
        'routes' => [[
            'distance' => 275000,
            'duration' => 17100,
            'geometry' => ['type' => 'LineString', 'coordinates' => [[38.74, 9.03], [38.42, 7.49]]],
        ]],
        'code' => 'Ok',
    ], 200));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    // A real road distance and a straight-line estimate of the same two points
    // differ, so this proves the fallback is not quietly winning.
    expect($result['provider'])->toBe('osrm')
        ->and($result['distance_meters'])->toBe(275000);
});

it('falls back when OSRM reports success but the route has no distance', function () {
    Http::fake(fn () => Http::response([
        'routes' => [[
            'duration' => 17100,
            'geometry' => ['type' => 'LineString', 'coordinates' => []],
        ]],
        'code' => 'Ok',
    ], 200));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    expect($result['provider'])->toBe('straight_line_estimate');
});

it('the fallback geometry is a two-point LineString in lng/lat order', function () {
    Http::fake(fn () => Http::response(null, 500));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    // GeoJSON is [lng, lat], the reverse of the argument order. Getting this
    // backwards draws a line across the map.
    expect($result['geometry']['type'])->toBe('LineString')
        ->and($result['geometry']['coordinates'])->toBe([
            [38.74, 9.03],
            [38.42, 7.49],
        ]);
});

it('the fallback distance grows with the distance between the points', function () {
    Http::fake(fn () => Http::response(null, 500));

    $near = app(RoutePreviewService::class)->preview(9.03, 38.74, 9.13, 38.74);
    $far = app(RoutePreviewService::class)->preview(9.03, 38.74, 7.49, 38.42);

    expect($far['distance_meters'])->toBeGreaterThan($near['distance_meters'])
        ->and($far['duration_seconds'])->toBeGreaterThan($near['duration_seconds']);
});

it('the fallback is zero distance for two identical points', function () {
    Http::fake(fn () => Http::response(null, 500));

    $result = app(RoutePreviewService::class)->preview(9.03, 38.74, 9.03, 38.74);

    expect($result['distance_meters'])->toBe(0)
        ->and($result['duration_seconds'])->toBe(0);
});
