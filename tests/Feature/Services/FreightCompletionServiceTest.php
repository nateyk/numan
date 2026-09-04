<?php

use App\Models\FreightPost;
use App\Services\FreightCompletionService;
use Illuminate\Support\Facades\Http;

it('scores freight post completion', function () {
    $post = FreightPost::factory()->make(['origin_location_id' => null]);
    expect(app(FreightCompletionService::class)->score($post))->toBeLessThan(100);
});

it('scores 100 when fully complete', function () {
    $post = FreightPost::factory()->make([
        'origin_location_id' => 1,
        'destination_location_id' => 2,
        'cargo_type_id' => 1,
        'cargo_description' => 'Test cargo',
        'item_name' => 'Teff',
        'actual_weight' => 20,
        'tariff_amount' => 15000,
        'required_truck_type_id' => 1,
        'truck_count' => 2,
        'schedule_type' => 'one_time',
        'pickup_date' => now()->addDays(2)->toDateString(),
        'origin_point_confirmed_at' => now(),
        'destination_point_confirmed_at' => now(),
    ]);
    expect(app(FreightCompletionService::class)->score($post))->toBe(100);
});

it('updates completion score on model', function () {
    $post = FreightPost::factory()->create(['origin_location_id' => null]);
    $service = app(FreightCompletionService::class);
    $updated = $service->update($post);
    expect($updated->completion_score)->toBe($service->score($post));
});

it('returns null when OSRM unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));
    expect(app(\App\Services\RoutePreviewService::class)->preview(9, 38, 7, 38))->toBeNull();
});
