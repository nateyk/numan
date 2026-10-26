<?php

use App\Enums\FreightPostStatus;
use App\Models\CargoType;
use App\Models\FreightPost;
use App\Models\FreightPostRoute;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only lists active freight publicly', function () {
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    FreightPost::factory()->create(['status' => FreightPostStatus::Draft->value]);

    $this->get('/freight')->assertOk()->assertInertia(fn ($page) => $page->component('discover/index')->where('freight.data', fn ($d) => count($d) === 1));
});

it('sends the paginated shape the discover page renders', function () {
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);

    // The discover page reads freight.data / freight.links / freight.total.
    // A flat list here renders as an empty marketplace even with live posts
    // in the database, which is exactly what this guards.
    $this->get('/freight')->assertOk()->assertInertia(fn ($page) => $page
        ->where('freight.data', fn ($d) => count($d) === 1)
        ->where('freight.total', 1)
        ->has('freight.links')
    );
});

it('filters by origin and cargo type', function () {
    $loc1 = Location::factory()->create();
    $loc2 = Location::factory()->create();
    $cargo1 = CargoType::factory()->create();
    $cargo2 = CargoType::factory()->create();

    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'origin_location_id' => $loc1->id, 'cargo_type_id' => $cargo1->id]);
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'origin_location_id' => $loc2->id, 'cargo_type_id' => $cargo2->id]);

    $this->get('/freight?origin='.$loc1->id)->assertOk()->assertInertia(fn ($page) => $page->where('freight.data', fn ($d) => count($d) === 1));
    $this->get('/freight?cargo_type='.$cargo1->id)->assertOk()->assertInertia(fn ($page) => $page->where('freight.data', fn ($d) => count($d) === 1));
});

it('is accessible via discover route as well', function () {
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    $this->get('/discover')->assertOk()->assertInertia(fn ($page) => $page->component('discover/index'));
});

/**
 * The two distance sort options used to map to the same arm as the default
 * sort — the menu promised "Distance: Near to Far" and quietly delivered
 * "Latest". Pickup dates below are set so the old ordering lands on a known
 * sequence that is not the distance ordering.
 */
it('sorts near to far by real distance, with unrouted posts last', function () {
    $far = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-01']);
    $mid = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-05']);
    $near = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-10']);
    $unrouted = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-15']);

    FreightPostRoute::create(['freight_post_id' => $far->id, 'distance_meters' => 500000, 'duration_seconds' => 6000]);
    FreightPostRoute::create(['freight_post_id' => $mid->id, 'distance_meters' => 300000, 'duration_seconds' => 4000]);
    FreightPostRoute::create(['freight_post_id' => $near->id, 'distance_meters' => 100000, 'duration_seconds' => 1500]);
    // $unrouted was never routed: no snapshot row at all.

    $props = null;
    $this->get('/freight?sort=distance_asc')->assertOk()->assertInertia(function ($page) use (&$props) {
        $page->component('discover/index');
        $props = $page->toArray()['props'];
    });

    expect(collect($props['freight']['data'])->pluck('ulid')->all())
        ->toBe([$near->ulid, $mid->ulid, $far->ulid, $unrouted->ulid]);
});

it('sorts far to near by real distance, with unrouted posts last', function () {
    $far = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-01']);
    $mid = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-05']);
    $near = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-10']);
    $unrouted = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value, 'pickup_date' => '2026-11-15']);

    FreightPostRoute::create(['freight_post_id' => $far->id, 'distance_meters' => 500000, 'duration_seconds' => 6000]);
    FreightPostRoute::create(['freight_post_id' => $mid->id, 'distance_meters' => 300000, 'duration_seconds' => 4000]);
    FreightPostRoute::create(['freight_post_id' => $near->id, 'distance_meters' => 100000, 'duration_seconds' => 1500]);

    $props = null;
    $this->get('/freight?sort=distance_desc')->assertOk()->assertInertia(function ($page) use (&$props) {
        $page->component('discover/index');
        $props = $page->toArray()['props'];
    });

    expect(collect($props['freight']['data'])->pluck('ulid')->all())
        ->toBe([$far->ulid, $mid->ulid, $near->ulid, $unrouted->ulid]);
});

it('echoes back only a sort it actually honors', function () {
    // The results header reads filters.sort to print "Sorted by …", so an
    // unrecognized value must not be echoed as if it had been applied.
    FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);

    $props = null;
    $this->get('/freight?sort=cheapest-first')->assertOk()->assertInertia(function ($page) use (&$props) {
        $page->component('discover/index');
        $props = $page->toArray()['props'];
    });

    expect($props['filters']['sort'])->toBe('latest');
});

/**
 * A browse card is a load, not a person. Guests and strangers get the lane,
 * the distance and the route numbers — never who posted it. Own-post detection
 * travels as a boolean so the identity itself never has to be sent.
 */
it('sends the route numbers but never the consignor identity on a card', function () {
    $owner = User::factory()->create(['name' => 'Account Name On File']);
    $owner->profile()->create(['display_name' => 'Hidden Consignor']);
    $post = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'user_id' => $owner->id,
    ]);
    FreightPostRoute::create([
        'freight_post_id' => $post->id,
        'distance_meters' => 932000,
        'duration_seconds' => 50400,
    ]);

    $props = null;
    $response = $this->get('/freight')->assertOk()->assertInertia(function ($page) use (&$props) {
        $page->component('discover/index');
        $props = $page->toArray()['props'];
    });

    $card = $props['freight']['data'][0];

    expect($card)->not->toHaveKey('owner')
        ->and($card['is_own_post'])->toBeFalse()
        ->and($card['route_snapshot']['distance_meters'])->toBe(932000)
        ->and($card['route_snapshot']['duration_seconds'])->toBe(50400)
        ->and($response->getContent())
        ->not->toContain('Account Name On File')
        ->not->toContain('Hidden Consignor');
});

it('marks the viewer own listing without sending its identity', function () {
    $owner = User::factory()->create(['name' => 'Account Name On File']);
    $owner->profile()->create(['display_name' => 'Owner Display Name']);
    $post = FreightPost::factory()->create([
        'status' => FreightPostStatus::Active->value,
        'user_id' => $owner->id,
    ]);

    $props = null;
    $this->actingAs($owner)
        ->get('/freight')
        ->assertOk()
        ->assertInertia(function ($page) use (&$props) {
            $page->component('discover/index');
            $props = $page->toArray()['props'];
        });

    $card = $props['freight']['data'][0];

    expect($card)->not->toHaveKey('owner')
        ->and($card['is_own_post'])->toBeTrue();
});

/**
 * geometry is the OSRM polyline — tens of kilobytes per route, drawn behind
 * a button on the detail surfaces only. Twelve polylines per list page load
 * to power nothing was dead weight the server kept shipping.
 */
it('omits the route geometry from the discovery list', function () {
    $post = FreightPost::factory()->create(['status' => FreightPostStatus::Active->value]);
    FreightPostRoute::create([
        'freight_post_id' => $post->id,
        'distance_meters' => 932000,
        'duration_seconds' => 50400,
        'geometry' => ['type' => 'LineString', 'coordinates' => [[8.98, 38.75], [9.0, 38.8]]],
    ]);

    $props = null;
    $this->get('/freight')->assertOk()->assertInertia(function ($page) use (&$props) {
        $page->component('discover/index');
        $props = $page->toArray()['props'];
    });

    $snapshot = $props['freight']['data'][0]['route_snapshot'];

    expect($snapshot)->not->toHaveKey('geometry')
        ->and($snapshot['distance_meters'])->toBe(932000);
});
