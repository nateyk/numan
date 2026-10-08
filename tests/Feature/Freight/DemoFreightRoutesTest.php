<?php

use App\Models\FreightPost;
use App\Models\FreightPostRoute;
use App\Models\Location;
use Database\Seeders\CargoTypeSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\TruckTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The demo data has to be able to demonstrate the page.
 *
 * Found by trying to: the Freight Details page had a map toggle and a distance
 * line, and neither could be seen because the seeded posts had no route to draw.
 * Demo data that cannot exercise the feature it exists to demonstrate is worse
 * than no demo data, because it reads as "the feature does not work".
 *
 * The map itself only needs coordinates, which come off the Location and the
 * location seeder does provide them. The distance and the drawn route come from
 * freight_post_routes, and the demo seeder created none.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // DemoDataSeeder reads Locations rather than creating them, so they have to
    // exist first — which is the whole point of the guard below.
    $this->seed(LocationSeeder::class);
    $this->seed(CargoTypeSeeder::class);
    $this->seed(TruckTypeSeeder::class);
    $this->seed(DemoDataSeeder::class);
});

it('seeds locations with coordinates, so a map can be drawn at all', function () {
    $located = Location::whereNotNull('latitude')->whereNotNull('longitude')->count();

    expect($located)->toBeGreaterThan(0)
        // Referenced by name from the freight posts below, so a missing one means
        // a post with a null origin and no map.
        ->and(Location::where('name', 'Adama')->exists())->toBeTrue()
        ->and(Location::where('name', 'Bahir Dar')->exists())->toBeTrue();
});

it('gives the demo freight posts a route snapshot', function () {
    $posts = FreightPost::whereIn('status', ['active', 'submitted'])->get();

    expect($posts)->not->toBeEmpty();

    $without = $posts->filter(fn (FreightPost $post) => $post->routeSnapshot === null);

    expect($without)->toHaveCount(0, implode(', ', $without->pluck('ulid')->all()));
});

it('gives every route snapshot a drawable geometry and a real distance', function () {
    $routes = FreightPostRoute::all();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->distance_meters)->toBeGreaterThan(0)
            ->and($route->duration_seconds)->toBeGreaterThan(0)
            ->and($route->geometry)->toBeArray()
            ->and($route->geometry['type'] ?? null)->toBe('LineString')
            ->and($route->geometry['coordinates'] ?? [])->not->toBeEmpty();
    }
});

it('anchors each route on the coordinates of its own two locations', function () {
    // A geometry that does not start and end at the post's locations draws a
    // line between two points that are not on the map. Cheap to get wrong and
    // it looks like a routing bug rather than a seeding one.
    foreach (FreightPostRoute::with('freightPost.originLocation', 'freightPost.destinationLocation')->get() as $route) {
        $origin = $route->freightPost?->originLocation;
        $destination = $route->freightPost?->destinationLocation;

        expect($origin)->not->toBeNull('route '.$route->id.' has no origin location')
            ->and($destination)->not->toBeNull('route '.$route->id.' has no destination location');

        $coordinates = $route->geometry['coordinates'];

        expect($coordinates)->toHaveCount(2)
            ->and((float) $coordinates[0][0])->toEqualWithDelta((float) $origin->longitude, 0.001)
            ->and((float) $coordinates[0][1])->toEqualWithDelta((float) $origin->latitude, 0.001)
            ->and((float) $coordinates[1][0])->toEqualWithDelta((float) $destination->longitude, 0.001)
            ->and((float) $coordinates[1][1])->toEqualWithDelta((float) $destination->latitude, 0.001);
    }
});

it('does not create a second copy of the demo posts when run twice', function () {
    $before = FreightPost::count();

    $this->seed(DemoDataSeeder::class);

    // The seeder uses firstOrCreate keyed on a freshly generated ULID, so the
    // lookup can never match and every run inserts again.
    expect(FreightPost::count())->toBe($before);
});
