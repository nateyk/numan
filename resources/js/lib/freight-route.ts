/**
 * The one place that reads a freight post's route off a payload.
 *
 * A freight row reaches the browser in more than one shape, and until this
 * existed every page guessed at the key name:
 *
 *   FreightPostResource       -> origin, destination, cargo_type, truck_type
 *   a bare Eloquent model     -> origin_location, destination_location
 *   ConnectionResource        -> freight_post.origin as a plain string
 *
 * Each page read a different subset of those names, so a page paired with the
 * wrong producer rendered an em dash where the town should be. The origin was
 * present in the payload the whole time; nothing was reading it.
 *
 * Read a route through here and a page stops caring which producer sent it.
 */

export type FreightPlace = {
    id?: number | null;
    name: string;
    region_name?: string | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
};

/**
 * A row as it arrives: any shape, from any of the producers above.
 *
 * Deliberately loose. The producers disagree on key names, so the reader has
 * to accept whatever they send rather than insist on one interface.
 */
export type FreightRow = object | null | undefined;

function asRecord(row: FreightRow): Record<string, unknown> | null {
    return row != null && typeof row === 'object'
        ? (row as Record<string, unknown>)
        : null;
}

function readPlace(value: unknown): FreightPlace | null {
    if (value == null) return null;

    // ConnectionResource and FreightInterestForTransporterResource send a bare
    // string rather than a location object.
    if (typeof value === 'string') {
        const name = value.trim();

        return name === '' ? null : { name };
    }

    if (typeof value !== 'object') return null;

    const candidate = value as FreightPlace;
    const name = typeof candidate.name === 'string' ? candidate.name.trim() : '';

    return name === '' ? null : { ...candidate, name };
}

function first(row: FreightRow, keys: string[]): FreightPlace | null {
    const record = asRecord(row);

    if (record === null) return null;

    for (const key of keys) {
        const place = readPlace(record[key]);

        if (place !== null) return place;
    }

    return null;
}

export type FreightRoute = {
    origin: FreightPlace | null;
    destination: FreightPlace | null;
    cargoType: FreightPlace | null;
    truckType: FreightPlace | null;
};

/**
 * The origin, destination, cargo type and truck type of a freight row.
 *
 * Each is null when the row genuinely carries no such place, which is what
 * every caller already renders a dash for.
 */
export function freightRoute(row: FreightRow): FreightRoute {
    return {
        origin: first(row, ['origin', 'originLocation', 'origin_location']),
        destination: first(row, [
            'destination',
            'destinationLocation',
            'destination_location',
        ]),
        cargoType: first(row, ['cargo_type', 'cargoType']),
        truckType: first(row, ['truck_type', 'truckType']),
    };
}

/** "Kombolcha → Hawassa", with a dash for a leg that has no place. */
export function routeLabel(row: FreightRow): string {
    const { origin, destination } = freightRoute(row);

    return `${origin?.name ?? '—'} → ${destination?.name ?? '—'}`;
}

/**
 * The post nested under an interest or connection row.
 *
 * Those resources nest the post under freight_post (snake) or freightPost
 * (camel), so the name is tried both ways.
 */
export function nestedFreightPost(row: FreightRow): FreightRow {
    const record = asRecord(row);

    if (record === null) return null;

    return (record.freightPost ?? record.freight_post ?? null) as FreightRow;
}

/**
 * The ulid of a nested post, or null.
 *
 * Read through here because the nested post is a loose record, and `post.ulid`
 * on one is `unknown` rather than a string.
 */
export function nestedFreightUlid(row: FreightRow): string | null {
    return postUlid(nestedFreightPost(row));
}

/** The ulid off an already-unwrapped post row, or null. */
export function postUlid(post: FreightRow): string | null {
    const ulid = asRecord(post)?.ulid;

    return typeof ulid === 'string' && ulid !== '' ? ulid : null;
}