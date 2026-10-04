<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Materialises a picked OpenStreetMap result as a real locations row.
 *
 * Location search falls back to Nominatim when the local table is thin, which for
 * Ethiopia and Djibouti is most of the time. A consignor can pick one of those
 * results, and the wizard needs an actual location_id for it: origin_location_id
 * is a foreign key, the route step will not save without one, and the public
 * discovery filter matches on it.
 *
 * So the pick is written to the locations table and the row is used from then on,
 * exactly like a hand-entered location. That also means the discovery filter
 * starts matching it for everybody else, which is the point.
 *
 * Identity is the OSM type and id, never the name. Two searches for the same town
 * must converge on one row, and matching on name would also merge genuinely
 * distinct places that share a name.
 */
class OsmLocationResolver
{
    /**
     * Resolve an OSM feature to a locations row, creating it if new.
     *
     * @param  array<string, mixed>  $feature
     */
    public function resolve(array $feature): ?Location
    {
        $osmType = $feature['osm_type'] ?? null;
        $osmId = $feature['osm_id'] ?? null;
        $latitude = $feature['latitude'] ?? null;
        $longitude = $feature['longitude'] ?? null;
        $name = trim((string) ($feature['name'] ?? ''));

        if (! $osmType || $osmId === null || $name === '') {
            return null;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $osmId = (string) $osmId;

        $existing = Location::query()->where('osm_id', $osmId)->first();

        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($osmId, $name, $latitude, $longitude, $feature): ?Location {
                // Re-checked inside the transaction: two searches for the same
                // town can race, and the unique index means the loser gets an
                // exception rather than a duplicate.
                $existing = Location::query()->where('osm_id', $osmId)->lockForUpdate()->first();

                if ($existing) {
                    return $existing;
                }

                return Location::create([
                    'name' => Str::limit($name, 255, ''),
                    'slug' => $this->uniqueSlug($name),
                    'type' => $feature['type'] ?? 'place',
                    'region_name' => $feature['region_name'] ?? null,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'is_active' => true,
                    'is_major_town' => (bool) ($feature['is_major_town'] ?? false),
                    'osm_id' => $osmId,
                    'source' => 'osm',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request inserted the same feature between our check and
            // our insert. Its row is just as good as the one we would have made.
            return Location::query()->where('osm_id', $osmId)->first();
        }
    }

    /**
     * A slug that is unique, since two OSM features can share a name.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'location';

        if (! Location::query()->where('slug', $base)->exists()) {
            return $base;
        }

        // Random rather than a counter so two concurrent inserts cannot both
        // decide on the same suffix.
        return $base.'-'.Str::lower(Str::random(6));
    }
}
