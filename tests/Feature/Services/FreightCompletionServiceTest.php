<?php

use App\Models\FreightPost;
use App\Services\FreightCompletionService;
use App\Services\RoutePreviewService;
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

// This belongs to RoutePreviewService rather than FreightCompletionService, and
// the equivalent case is covered properly in RoutePreviewServiceTest. Kept here
// rather than deleted, but it now asserts the current contract.
it('returns a straight-line estimate when OSRM is unreachable', function () {
    Http::fake(fn () => Http::response(null, 500));

    $result = app(RoutePreviewService::class)->preview(9, 38, 7, 38);

    expect($result)->not->toBeNull()
        ->and($result['provider'])->toBe('straight_line_estimate');
});

it('counts item_name as cargo and cargo_description as nothing', function () {
    // cargo_description is no longer collected, so a post carrying only it is
    // missing its cargo. cargo_type_id is null on both sides so this isolates
    // the item_name half of the branch, and the load half is empty on both so
    // the cargo half is what moves the number.
    $described = FreightPost::factory()->make([
        'cargo_type_id' => null,
        'cargo_description' => '20 tons teff in 40kg bags',
        'item_name' => null,
        'actual_weight' => null,
        'tariff_amount' => null,
    ]);

    $named = FreightPost::factory()->make([
        'cargo_type_id' => null,
        'cargo_description' => null,
        'item_name' => 'Teff',
        'actual_weight' => null,
        'tariff_amount' => null,
    ]);

    expect(app(FreightCompletionService::class)->score($described))
        ->toBeLessThan(app(FreightCompletionService::class)->score($named));
});

it('gives cargo credit for a cargo type with no item name', function () {
    // The branch is an OR on purpose: picking a cargo type is enough for the
    // first half on its own. This pins that down so the item_name change cannot
    // quietly turn it into an AND and cost every typed-cargo post ten points.
    $typedOnly = FreightPost::factory()->make([
        'cargo_type_id' => 1,
        'item_name' => null,
        'actual_weight' => null,
        'tariff_amount' => null,
    ]);

    $named = FreightPost::factory()->make([
        'cargo_type_id' => 1,
        'item_name' => 'Teff',
        'actual_weight' => null,
        'tariff_amount' => null,
    ]);

    expect(app(FreightCompletionService::class)->score($typedOnly))
        ->toBe(app(FreightCompletionService::class)->score($named));
});
