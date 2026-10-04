/**
 * Turns a picked OpenStreetMap search result into a real locations row.
 *
 * Search returns OSM results with an "osm_<id>" placeholder because they have no
 * database id. Anything that stores a location_id needs the real one, so the pick
 * is posted to /locations/resolve-osm and the server writes the row. Nominatim
 * returns the same feature for the same query forever, so this converges on one
 * row rather than inserting a new one per search.
 */

import type { Location } from '@/components/maps/location-search-input';

export function isOsmLocation(location: Location | null | undefined): boolean {
    return typeof location?.id === 'string' && location.id.startsWith('osm_');
}

export async function resolveOsmLocation(
    location: Location,
): Promise<number | null> {
    if (!isOsmLocation(location)) {
        return typeof location.id === 'number' ? location.id : null;
    }

    if (location.osm_id == null) {
        return null;
    }

    const response = await fetch('/locations/resolve-osm', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'application/json',
        },
        body: JSON.stringify({
            osm_type: location.osm_type,
            osm_id: location.osm_id,
            name: location.name,
            type: location.type,
            region_name: location.region_name,
            latitude: location.latitude,
            longitude: location.longitude,
            is_major_town: location.is_major_town ?? false,
        }),
    });

    if (!response.ok) {
        return null;
    }

    const data = await response.json();

    return typeof data?.location?.id === 'number' ? data.location.id : null;
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
