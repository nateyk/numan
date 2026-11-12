import CollapsibleRouteMap, {
    type RouteSnapshot,
} from '@/components/freight/collapsible-route-map';
import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
import { CalendarDays, Package, Route, Truck } from 'lucide-react';

/**
 * What FreightPostResource sends. Explicit, and only these keys.
 *
 * The page this replaces declared user/email/avatar_url, origin_exact_lat,
 * route_snapshot and friends — nine keys the resource never sent, which is why the
 * map had no coordinates, the distance line never rendered, and the consignor was
 * "Unknown consignor". Declaring the real shape is what stops that recurring.
 */
export type FreightDetailsPost = {
    ulid: string;
    status: string;
    item_name: string | null;
    cargo_description: string | null;
    actual_weight: string | null;
    weight_unit: string | null;
    tariff_weight: string | null;
    tariff_weight_unit: string | null;
    tariff_amount: string | null;
    tariff_currency: string | null;
    insurance_required: boolean;
    insurance_value: string | null;
    depreciation_rate: string | null;
    truck_count: number | null;
    schedule_type: string | null;
    pickup_date: string | null;
    pickup_time: string | null;
    pickup_date_flexibility_days: number | null;
    created_at: string | null;
    origin_site_note: string | null;
    destination_site_note: string | null;
    origin: {
        id: number;
        name: string;
        region_name: string;
        latitude: number | null;
        longitude: number | null;
    } | null;
    destination: {
        id: number;
        name: string;
        region_name: string;
        latitude: number | null;
        longitude: number | null;
    } | null;
    cargo_type: { id: number; name: string; slug: string } | null;
    truck_type: { id: number; name: string; slug: string } | null;
    route_snapshot: RouteSnapshot;
};

function latitudeOf(place: FreightDetailsPost['origin']): number | null {
    // The resource sends latitude/longitude on the location object. It used to be
    // read from post.origin_exact_lat, which was never sent, so the map received
    // null for every post.
    if (place?.latitude === null || place?.latitude === undefined) return null;

    return Number(place.latitude);
}

function longitudeOf(place: FreightDetailsPost['origin']): number | null {
    if (place?.longitude === null || place?.longitude === undefined)
        return null;

    return Number(place.longitude);
}

/**
 * The product's own names for the two ways a load can be awarded. The API
 * stores `one_time` / `recurring`; neither belongs on a public page.
 */
const scheduleCopy: Record<string, { label: string; detail: string }> = {
    one_time: {
        label: 'Direct offer',
        detail: 'One-time freight, first suitable transporter gets it.',
    },
    recurring: {
        label: 'Tender',
        detail: 'Recurring need, collect multiple offers.',
    },
};

function humanize(value: string): string {
    const words = value.replaceAll('_', ' ');

    return words.charAt(0).toUpperCase() + words.slice(1);
}

/** Aligned load-sheet rows wrap long place names without horizontal scrolling. */
function Fact({
    label,
    value,
    detail,
}: {
    label: string;
    value: React.ReactNode;
    detail?: string;
}) {
    return (
        <div className="grid grid-cols-[6.25rem_minmax(0,1fr)] gap-x-3 gap-y-1 py-2.5 sm:grid-cols-[7.25rem_minmax(0,1fr)]">
            <dt className="text-sm leading-relaxed text-muted-foreground">
                {label}
            </dt>
            <dd className="min-w-0 break-words whitespace-pre-line text-sm font-medium leading-relaxed">
                {value}
                {detail && (
                    <span className="mt-1 block text-xs font-normal leading-relaxed text-muted-foreground">
                        {detail}
                    </span>
                )}
            </dd>
        </div>
    );
}

function placeLabel(place: FreightDetailsPost['origin']): string {
    if (!place) return 'Not stated';

    return place.region_name
        ? `${place.name} (${place.region_name})`
        : place.name;
}

export default function FreightDetailsCard({
    post,
}: {
    post: FreightDetailsPost;
}) {
    const origin = post.origin;
    const destination = post.destination;

    const schedule = post.schedule_type
        ? scheduleCopy[post.schedule_type]
        : undefined;
    const scheduleLabel =
        schedule?.label ?? humanize(post.schedule_type ?? 'one_time');

    // The wizard collects the goods in item_name; cargo_description is an older
    // field the API still sends. Show it only when it adds something, so the
    // card never prints the same sentence twice.
    const description =
        post.cargo_description && post.cargo_description !== post.item_name
            ? post.cargo_description
            : null;

    const tariff = post.tariff_amount
        ? `${post.tariff_currency ?? 'ETB'} ${post.tariff_amount}`
        : 'Not stated';

    const laneFact = post.route_snapshot
        ? [
              formatDistance(post.route_snapshot.distance_meters),
              formatDuration(post.route_snapshot.duration_seconds),
          ]
              .filter(Boolean)
              .join(' · ')
        : 'Not calculated';

    // "T00:00:00" so the ISO date lands on the same day in every timezone.
    const pickupValue = post.pickup_date
        ? new Date(`${post.pickup_date}T00:00:00`).toLocaleDateString(
              undefined,
              { day: 'numeric', month: 'short', year: 'numeric' },
          )
        : 'Flexible';

    const pickupSub =
        [
            post.pickup_time ? post.pickup_time.slice(0, 5) : '',
            post.pickup_date_flexibility_days
                ? `±${post.pickup_date_flexibility_days} days`
                : '',
        ]
            .filter(Boolean)
            .join(' · ') || undefined;

    const createdValue = post.created_at
        ? new Date(post.created_at).toLocaleString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
          })
        : 'Not stated';

    return (
        <section
            aria-label="Freight details"
            className="@container min-w-0 space-y-6"
        >
            <div className="grid gap-6 @min-[38rem]:grid-cols-2 @min-[38rem]:gap-8">
                <section
                    aria-labelledby="freight-route-heading"
                    className="min-w-0"
                >
                    <div className="mb-3 flex min-h-11 flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
                        <h2
                            id="freight-route-heading"
                            className="flex items-center gap-2 text-base font-semibold"
                        >
                            <Route
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Route &amp; schedule
                        </h2>
                        <CollapsibleRouteMap
                            fromLat={latitudeOf(origin)}
                            fromLng={longitudeOf(origin)}
                            toLat={latitudeOf(destination)}
                            toLng={longitudeOf(destination)}
                            fromName={origin?.name}
                            toName={destination?.name}
                            routeSnapshot={post.route_snapshot}
                        />
                    </div>
                    <dl className="divide-y divide-border/50">
                        <Fact label="Origin" value={placeLabel(origin)} />
                        <Fact label="Destination" value={placeLabel(destination)} />
                        <Fact
                            label="Offer type"
                            value={scheduleLabel}
                            detail={schedule?.detail}
                        />
                        <Fact
                            label="Pickup"
                            value={[pickupValue, pickupSub]
                                .filter(Boolean)
                                .join(' · ')}
                        />
                        <Fact
                            label="Distance / time"
                            value={laneFact || 'Not calculated'}
                        />
                    </dl>
                </section>

                <section
                    aria-labelledby="freight-cargo-heading"
                    className="min-w-0"
                >
                    <h2
                        id="freight-cargo-heading"
                        className="mb-3 flex min-h-11 items-center gap-2 border-b border-border pb-3 text-base font-semibold"
                    >
                        <Package
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Cargo &amp; truck
                    </h2>
                    <dl className="divide-y divide-border/50">
                        <Fact
                            label="Item"
                            value={post.item_name ?? 'Not stated'}
                        />
                        <Fact
                            label="Cargo type"
                            value={post.cargo_type?.name ?? 'Not stated'}
                        />
                        <Fact
                            label="Actual weight"
                            value={
                                post.actual_weight
                                    ? post.actual_weight + ' ' + (post.weight_unit ?? 'ton')
                                    : 'Not stated'
                            }
                        />
                        {post.tariff_weight && (
                            <Fact
                                label="Tariff weight"
                                value={
                                    post.tariff_weight + ' ' +
                                    (post.tariff_weight_unit ?? 'ton')
                                }
                            />
                        )}
                        <Fact
                            label="Tariff rate"
                            value={
                                <span className="font-semibold tabular-nums">
                                    {tariff}
                                </span>
                            }
                        />
                        <Fact
                            label="Truck"
                            value={
                                (post.truck_type?.name ?? 'Any truck') +
                                (post.truck_count ? ' × ' + post.truck_count : '')
                            }
                        />
                        {description && (
                            <Fact label="Description" value={description} />
                        )}
                        {post.insurance_required && (
                            <Fact
                                label="Insurance"
                                value={
                                    post.insurance_value
                                        ? (post.tariff_currency ?? 'ETB') + ' ' + post.insurance_value
                                        : 'Required, value not set'
                                }
                            />
                        )}
                        {post.depreciation_rate && (
                            <Fact
                                label="Depreciation"
                                value={post.depreciation_rate + '%'}
                            />
                        )}
                    </dl>
                </section>
            </div>

            {(post.origin_site_note || post.destination_site_note) && (
                <section
                    aria-labelledby="freight-site-notes-heading"
                    className="border-t border-border pt-5"
                >
                    <h2
                        id="freight-site-notes-heading"
                        className="mb-3 flex items-center gap-2 text-base font-semibold"
                    >
                        <Truck
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Pickup &amp; delivery notes
                    </h2>
                    <dl className="grid gap-x-6 gap-y-3 @min-[38rem]:grid-cols-2">
                        {post.origin_site_note && (
                            <div className="min-w-0 space-y-1">
                                <dt className="text-xs text-muted-foreground">
                                    Pickup note
                                </dt>
                                <dd className="break-words whitespace-pre-line text-sm font-medium">
                                    {post.origin_site_note}
                                </dd>
                            </div>
                        )}
                        {post.destination_site_note && (
                            <div className="min-w-0 space-y-1">
                                <dt className="text-xs text-muted-foreground">
                                    Delivery note
                                </dt>
                                <dd className="break-words whitespace-pre-line text-sm font-medium">
                                    {post.destination_site_note}
                                </dd>
                            </div>
                        )}
                    </dl>
                </section>
            )}

            <p className="flex items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
                <CalendarDays className="size-3.5 shrink-0" aria-hidden="true" />
                Posted {createdValue}
            </p>
        </section>
    );
}
