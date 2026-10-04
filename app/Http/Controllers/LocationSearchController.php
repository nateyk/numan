<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Location search for the freight and transporter forms.
 *
 * Searches the local table first and supplements with OpenStreetMap Nominatim.
 * The supplement matters: the local table has no coverage of most of Ethiopia or
 * Djibouti, so a local-only search returns nothing for most of the market.
 *
 * OSM results carry an "osm_" prefixed id so the client can tell them apart from
 * local rows. Picking one is not a dead end -- the frontend posts it back and
 * OsmLocationResolver turns it into a real locations row, so everything
 * downstream (the foreign key, the discovery filter) keeps working.
 */
class LocationSearchController extends Controller
{
    /**
     * Nominatim blocks requests without an identifying User-Agent. Laravel's HTTP
     * client sends Guzzle's default, which is refused with a 403.
     */
    private const NOMINATIM_USER_AGENT = 'NumanFreight/1.0 (freight platform; contact ops@numanfreight.com)';

    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/search';

    /**
     * OSM feature types that are never a useful freight origin or destination.
     * A search for "Addis" returning a single ATM is noise, not a result.
     *
     * @var list<string>
     */
    private const EXCLUDED_OSM_TYPES = [
        'building', 'house', 'apartment', 'office', 'shop', 'restaurant',
        'hotel', 'school', 'hospital', 'bank', 'atm', 'post_office',
        'police', 'fire_station', 'library', 'museum', 'university',
        'college', 'parking', 'fuel', 'charging_station', 'bus_stop',
        'train_station', 'subway_station', 'airport', 'aerodrome',
        'helipad', 'gate', 'entrance', 'exit', 'fence', 'wall',
        'hedge', 'retaining_wall', 'city_wall', 'bollard', 'block',
        'civic', 'townhall', 'courthouse', 'embassy', 'public_building',
        'government', 'military', 'bunker', 'checkpoint', 'boundary_stone',
        'survey_point', 'milepost', 'benchmark', 'memorial', 'monument',
        'wayside_shrine', 'wayside_cross', 'cross', 'statue', 'artwork',
        'fountain', 'clock', 'drinking_water', 'water_point', 'toilet',
        'shower', 'waste_basket', 'recycling', 'bin', 'vending_machine',
        'telephone', 'emergency_phone', 'defibrillator', 'fire_hydrant',
        'fire_extinguisher', 'life_ring', 'life_buoy',
    ];

    /**
     * Freight searches want terminals and depots as well as towns, so they get a
     * lower importance floor than a general place search.
     */
    private const FREIGHT_MIN_IMPORTANCE = 0.005;

    private const GENERAL_MIN_IMPORTANCE = 0.1;

    /**
     * Ethiopian and neighbouring ISO 3166-1 alpha-2 codes.
     *
     * @var array<string, list<string>>
     */
    private const COUNTRY_NEIGHBOURS = [
        'et' => ['et', 'dj', 'er', 'so', 'ke', 'ss', 'sd'],
        'dj' => ['dj', 'et', 'er', 'so'],
        'er' => ['er', 'et', 'dj', 'sd'],
        'so' => ['so', 'et', 'dj', 'ke'],
        'ke' => ['ke', 'et', 'so', 'ss', 'tz', 'ug'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,freight,city,town'],
            'country' => ['nullable', 'string', 'max:2'],
            // A client that only wants local rows sets this. The discovery
            // filters do: they filter by location id, and an OSM id would
            // silently match nothing.
            'local_only' => ['nullable', 'boolean'],
        ]);

        $term = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', 'all');
        $country = (string) $request->query('country', 'ET');
        $localOnly = $request->boolean('local_only');

        if ($term === '') {
            return response()->json(['locations' => []]);
        }

        $local = $this->searchLocal($term, $type);

        if ($local->count() >= 10 || $localOnly) {
            return response()->json(['locations' => $local->take(15)->values()]);
        }

        return response()->json([
            'locations' => $this->merge($local, $this->searchOpenStreetMap($term, $type, $country)),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchLocal(string $term, string $type): Collection
    {
        $query = Location::query()
            ->active()
            ->search($term)
            ->orderByDesc('is_major_town')
            ->orderBy('name');

        match ($type) {
            'freight' => $query->where('is_major_town', true),
            'city' => $query->whereIn('type', ['city', 'subcity', 'town']),
            'town' => $query->whereIn('type', ['town', 'village']),
            default => null,
        };

        return $query->limit(15)->get([
            'id', 'name', 'region_name', 'slug', 'type', 'latitude', 'longitude',
            'is_major_town', 'population', 'wereda', 'zone',
        ])->map(fn (Location $location): array => [
            'id' => $location->id,
            'name' => $location->name,
            'region_name' => $location->region_name,
            'slug' => $location->slug,
            'type' => $location->type,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'is_major_town' => $location->is_major_town,
            'population' => $location->population,
            'wereda' => $location->wereda,
        ])->values();
    }

    /**
     * Local results win ties: the same town can appear in both sources, and the
     * local row is the one with a real id the database can reference.
     *
     * @param  Collection<int, array<string, mixed>>  $local
     * @param  Collection<int, array<string, mixed>>  $osm
     * @return Collection<int, array<string, mixed>>
     */
    private function merge(Collection $local, Collection $osm): Collection
    {
        // Cross-source matching has to be by name and region, because a local row
        // and its OSM twin share nothing else: different ids, and an osm_id the
        // local row cannot have. So the OSM side is filtered against the local
        // names first and only then concatenated. A single unique() key over the
        // merged list would never match anything across sources, which is exactly
        // the duplicate this exists to remove.
        $localNames = $local->map(fn (array $item): string => $this->nameKey($item))->all();

        $freshOsm = $osm
            ->reject(fn (array $item): bool => in_array($this->nameKey($item), $localNames, true))
            ->values();

        return $local->concat($freshOsm)->take(15)->values();
    }

    /**
     * Normalised name and region: the only thing the two sources agree on.
     *
     * @param  array<string, mixed>  $item
     */
    private function nameKey(array $item): string
    {
        return mb_strtolower(trim((string) ($item['name'] ?? '')))
            .'|'.mb_strtolower(trim((string) ($item['region_name'] ?? '')));
    }

    /**
     * Dedupe within the OSM results, keyed on the feature id rather than the
     * name: two genuinely different places can share a display name, and merging
     * them would send a consignor to the wrong one with no way to tell.
     *
     * @param  array<string, mixed>  $item
     */
    private function dedupeKey(array $item): string
    {
        return 'osm:'.($item['osm_type'] ?? '').':'.($item['osm_id'] ?? '');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function searchOpenStreetMap(string $term, string $type, string $country): Collection
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => self::NOMINATIM_USER_AGENT])
                ->get(self::NOMINATIM_URL, [
                    'q' => $term,
                    'format' => 'json',
                    'limit' => 20,
                    'addressdetails' => 1,
                    // Asked for explicitly: without it the only name available is
                    // the full display_name ("Adwa, Amhara, Ethiopia"), which can
                    // never match a local row called "Adwa" and so defeats the
                    // cross-source dedupe entirely.
                    'namedetails' => 1,
                    'accept-language' => 'en',
                    // Neighbouring countries too, because a freight route from
                    // Ethiopia to Djibouti is an ordinary thing to want.
                    'countrycodes' => $this->countryCodes($country),
                ]);

            if (! $response->ok()) {
                return collect();
            }

            $results = $response->json();

            if (! is_array($results)) {
                return collect();
            }

            return collect($results)
                ->filter(fn (mixed $item): bool => is_array($item) && $this->isUsable($item, $type))
                ->map(fn (array $item): array => $this->toResultArray($item))
                ->filter()
                ->unique(fn (array $item): string => $this->dedupeKey($item))
                ->values();
        } catch (\Throwable $e) {
            // A search box must degrade to "no extra results", never to a 500.
            report($e);

            return collect();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function toResultArray(array $item): array
    {
        return [
            'id' => 'osm_'.$item['osm_id'],
            // The short name, not the display name, so it is comparable with a
            // local row. display_name is passed through for display.
            'name' => $this->shortName($item),
            'display_name' => (string) ($item['display_name'] ?? ''),
            'slug' => null,
            'region_name' => $this->region($item),
            'type' => $item['type'] ?? 'place',
            'latitude' => $item['lat'] ?? null,
            'longitude' => $item['lon'] ?? null,
            'is_major_town' => $this->isMajorTown($item),
            'osm_type' => $item['osm_type'] ?? null,
            'osm_id' => $item['osm_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isUsable(array $item, string $type): bool
    {
        $osmType = (string) ($item['type'] ?? '');

        if (in_array($osmType, self::EXCLUDED_OSM_TYPES, true)) {
            return false;
        }

        // "Bole Office" or "Ministry Of Foreign Affairs" come back as
        // administrative features and are never a loading point.
        if ($osmType === 'administrative' && stripos((string) ($item['display_name'] ?? ''), 'office') !== false) {
            return false;
        }

        if (! is_numeric($item['lat'] ?? null) || ! is_numeric($item['lon'] ?? null)) {
            return false;
        }

        $importance = (float) ($item['importance'] ?? 0);

        if ($type === 'freight') {
            return $importance >= self::FREIGHT_MIN_IMPORTANCE
                && in_array($osmType, self::FREIGHT_TYPES, true);
        }

        if ($importance < self::GENERAL_MIN_IMPORTANCE) {
            return false;
        }

        $allowed = match ($type) {
            'city' => self::CITY_TYPES,
            'town' => self::TOWN_TYPES,
            default => self::GENERAL_TYPES,
        };

        return in_array($osmType, $allowed, true);
    }

    /**
     * The feature's own name, falling back to the first segment of the display
     * name so a result is never blank.
     *
     * @param  array<string, mixed>  $item
     */
    private function shortName(array $item): string
    {
        $named = $item['namedetails']['name'] ?? null;

        if (is_string($named) && trim($named) !== '') {
            return trim($named);
        }

        return trim(explode(',', (string) ($item['display_name'] ?? ''))[0]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isMajorTown(array $item): bool
    {
        return in_array((string) ($item['type'] ?? ''), ['city', 'town', 'municipality'], true)
            && (float) ($item['importance'] ?? 0) > 0.5;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function region(array $item): string
    {
        $address = is_array($item['address'] ?? null) ? $item['address'] : [];

        foreach (['state', 'region', 'province', 'state_district', 'county', 'district', 'country'] as $key) {
            if (! empty($address[$key])) {
                return (string) $address[$key];
            }
        }

        return '';
    }

    private function countryCodes(string $country): string
    {
        $country = mb_strtolower($country);

        return implode(',', self::COUNTRY_NEIGHBOURS[$country] ?? [$country]);
    }

    /** @var list<string> */
    private const FREIGHT_TYPES = [
        'city', 'town', 'village', 'municipality', 'suburb', 'district', 'county',
        'province', 'state', 'region', 'country', 'administrative', 'boundary',
        'industrial', 'warehouse', 'terminal', 'depot',
    ];

    /** @var list<string> */
    private const CITY_TYPES = [
        'city', 'town', 'municipality', 'suburb', 'district', 'county',
        'administrative', 'boundary',
    ];

    /** @var list<string> */
    private const TOWN_TYPES = [
        'town', 'village', 'hamlet', 'municipality', 'suburb', 'district', 'city',
    ];

    /** @var list<string> */
    private const GENERAL_TYPES = [
        'city', 'town', 'village', 'municipality', 'suburb', 'district', 'county',
        'province', 'state', 'region', 'administrative', 'boundary',
    ];
}
