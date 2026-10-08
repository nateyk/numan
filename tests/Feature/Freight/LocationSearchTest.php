<?php

use App\Models\FreightPost;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Location search: the local table first, OpenStreetMap as a supplement, and the
 * path that turns a picked OSM result into a real locations row.
 */
uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function osmFeature(array $overrides = []): array
{
    return array_merge([
        'place_id' => 12345,
        'osm_type' => 'relation',
        'osm_id' => 987654,
        'lat' => '9.0300',
        'lon' => '38.7400',
        'display_name' => 'Adwa, Amhara, Ethiopia',
        'importance' => 0.6,
        'type' => 'town',
        'class' => 'place',
        'address' => ['state' => 'Amhara', 'country' => 'Ethiopia'],
        'namedetails' => ['name' => 'Adwa'],
    ], $overrides);
}

/**
 * A response the controller should accept.
 *
 * @return array<string, mixed>
 */
function acceptableOsm(array $overrides = []): array
{
    return osmFeature($overrides);
}

/**
 * Something Nominatim returns that is never a useful freight location.
 *
 * @return array<string, mixed>
 */
function uselessOsm(array $overrides = []): array
{
    return osmFeature(array_merge([
        'place_id' => 999,
        'osm_id' => 111,
        'type' => 'atm',
        'importance' => 0.9,
        'display_name' => 'ATM, Addis Ababa, Ethiopia',
    ], $overrides));
}

it('returns local locations for a local match', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Amhara', 'is_major_town' => true]);
    Http::fake();

    $response = $this->getJson('/locations/search?q=Adwa');

    $response->assertOk();

    $names = collect($response->json('locations'))->pluck('name');
    expect($names)->toContain('Adwa');
});

it('does not call OpenStreetMap when there are enough local results', function () {
    Location::factory()->count(12)->create(['is_major_town' => true, 'name' => 'Adwa']);

    Http::fake();

    $this->getJson('/locations/search?q=Adwa')->assertOk();

    // A needless outbound call per keystroke is how a search box gets someone
    // rate-limited by Nominatim.
    Http::assertNothingSent();
});

it('supplements with OpenStreetMap when the local table is thin', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([acceptableOsm()], 200),
    ]);

    $response = $this->getJson('/locations/search?q=Adwa');

    $response->assertOk();

    $locations = collect($response->json('locations'));
    expect($locations)->toHaveCount(2)
        // Local first: the local row is the one with a real database id.
        ->and($locations->first()['id'])->toBeInt()
        ->and($locations->last()['id'])->toBeString()->toStartWith('osm_');
});

it('identifies itself to OpenStreetMap', function () {
    // Nominatim refuses a generic User-Agent with a 403, so this is the
    // difference between the feature working and silently returning nothing.
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    $this->getJson('/locations/search?q=Adwa')->assertOk();

    Http::assertSent(function ($request): bool {
        return str_contains((string) $request->header('User-Agent')[0], 'NumanFreight');
    });
});

it('includes neighbouring countries so border towns are findable', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    $this->getJson('/locations/search?q=Djibouti')->assertOk();

    Http::assertSent(function ($request): bool {
        $codes = $request->data()['countrycodes'] ?? '';

        return str_contains((string) $codes, 'dj') && str_contains((string) $codes, 'et');
    });
});

it('drops results that are not places', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            acceptableOsm(),
            uselessOsm(),
        ], 200),
    ]);

    $locations = collect($this->getJson('/locations/search?q=Adwa')->json('locations'));

    expect($locations)->toHaveCount(2) // the local row plus one real OSM result
        ->and($locations->pluck('type'))->not->toContain('atm');
});

it('drops low-importance results for a general search but keeps them for freight', function () {
    // is_major_town because a freight search deliberately narrows local rows to
    // major towns and terminals.
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia', 'is_major_town' => true]);

    $minor = acceptableOsm(['osm_id' => 222, 'importance' => 0.05, 'type' => 'village', 'display_name' => 'Small Village, Ethiopia']);

    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$minor], 200)]);
    $general = collect($this->getJson('/locations/search?q=Adwa')->json('locations'));
    expect($general)->toHaveCount(1);

    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([$minor], 200)]);
    $freight = collect($this->getJson('/locations/search?q=Adwa&type=freight')->json('locations'));
    expect($freight)->toHaveCount(2);
});

it('deduplicates a local row against the same place from OpenStreetMap', function () {
    Location::factory()->create([
        'name' => 'Adwa, Amhara',
        'region_name' => 'Amhara',
        'is_major_town' => true,
    ]);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            acceptableOsm([
                'display_name' => 'Adwa, Amhara, Ethiopia',
                'namedetails' => ['name' => 'Adwa, Amhara'],
                'address' => ['state' => 'Amhara'],
            ]),
        ], 200),
    ]);

    $locations = collect($this->getJson('/locations/search?q=Adwa')->json('locations'));

    // Same town from both sources, one row shown.
    expect($locations)->toHaveCount(1)
        ->and($locations->first()['id'])->toBeInt();
});

it('keeps two OpenStreetMap features that share a display name', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            acceptableOsm(['osm_id' => 111, 'display_name' => 'Adwa, Amhara, Ethiopia']),
            acceptableOsm(['place_id' => 2, 'osm_id' => 222, 'display_name' => 'Adwa, Amhara, Ethiopia']),
        ], 200),
    ]);

    $locations = collect($this->getJson('/locations/search?q=Adwa')->json('locations'));

    // Deliberately not deduplicated. Two features with the same display name are
    // two different places, and collapsing them would send a consignor to the
    // wrong one with nothing in the UI to tell them apart. The unique key within
    // OSM is the feature id precisely so this case survives.
    expect($locations)->toHaveCount(3)
        ->and($locations->pluck('osm_id')->filter()->unique())->toHaveCount(2);
});

// Note: every local row here pins region_name. LocationFactory picks it at
// random from a list that includes 'Amhara', which is the OSM fixture's region,
// so an unpinned row silently becomes a cross-source duplicate and these counts
// flake.
it('deduplicates the same OpenStreetMap feature returned twice', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            acceptableOsm(['osm_id' => 111]),
            acceptableOsm(['place_id' => 2, 'osm_id' => 111]),
        ], 200),
    ]);

    expect(collect($this->getJson('/locations/search?q=Adwa')->json('locations')))->toHaveCount(2);
});

it('returns local rows only when asked', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([acceptableOsm()], 200)]);

    $locations = collect($this->getJson('/locations/search?q=Adwa&local_only=1')->json('locations'));

    // This is what the discovery filters rely on: they match on location_id, and
    // an "osm_1234" would match nothing at all with no error.
    expect($locations)->toHaveCount(1)
        ->and($locations->first()['id'])->toBeInt();
    Http::assertNothingSent();
});

it('degrades to local results when OpenStreetMap is unreachable', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(null, 503)]);

    $this->getJson('/locations/search?q=Adwa')->assertOk()
        ->assertJsonCount(1, 'locations');
});

it('degrades to local results when OpenStreetMap times out', function () {
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->getJson('/locations/search?q=Adwa')->assertOk()
        ->assertJsonCount(1, 'locations');
});

it('never logs the search term or a payload', function () {
    // The standing LogHygieneTest covers the general rule; this pins the specific
    // line, because "log the search term while debugging" is a very natural edit
    // here and the term is free text a user typed.
    $logged = [];

    Log::listen(function ($event) use (&$logged): void {
        $logged[] = $event->message.' '.json_encode($event->context);
    });

    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    $this->getJson('/locations/search?q=Adwa')->assertOk();

    expect(implode("\n", $logged))->not->toContain('Adwa');
});

it('rejects an over-long search term', function () {
    $this->getJson('/locations/search?q='.str_repeat('a', 200))->assertStatus(422);
});

it('rejects an unknown result type', function () {
    $this->getJson('/locations/search?q=Adwa&type=nonsense')->assertStatus(422);
});

it('returns nothing for an empty term without calling out', function () {
    Http::fake();

    $this->getJson('/locations/search?q=')->assertOk()->assertJson(['locations' => []]);
    Http::assertNothingSent();
});

it('the discovery filter never matches an OpenStreetMap id', function () {
    // Defence in depth. The search box asks for local rows only, so this should
    // be unreachable -- but a non-numeric value passed straight to where()
    // coerces to 0 on MySQL and raises a type error on Postgres, so the server
    // refuses to treat it as an id rather than trusting the client.
    Location::factory()->create(['name' => 'Adwa', 'region_name' => 'Oromia']);
    FreightPost::factory()->create([
        'origin_location_id' => Location::factory()->create()->id,
        'status' => 'active',
    ]);

    $this->get('/freight?origin=osm_987654')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('freight.data', fn ($posts) => count($posts) === 0));
});

it('the discovery filter still matches a real location id', function () {
    $origin = Location::factory()->create();

    FreightPost::factory()->count(2)->create([
        'origin_location_id' => $origin->id,
        'status' => 'active',
    ]);

    $this->get('/freight?origin='.$origin->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('freight.data', fn ($posts) => count($posts) === 2));
});
