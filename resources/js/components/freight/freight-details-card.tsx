import CollapsibleRouteMap, {
    type RouteSnapshot,
} from '@/components/freight/collapsible-route-map';
import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
import {
    formatFreightDate,
    freightLifecycleLabel,
    freightPublicationLabel,
} from '@/lib/freight-dates';
import { CalendarDays, ClipboardList, Package, Route } from 'lucide-react';

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
    expires_on: string | null;
    published_at: string | null;
    closure_reason: string | null;
    arrived_at: string | null;
    closed_at: string | null;
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

/** One full-width list row per fact; no paired mini-fields within a section. */
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
        <div className="grid min-w-0 grid-cols-[6.25rem_minmax(0,1fr)] items-start gap-x-3 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-[7rem_minmax(0,1fr)]">
            <dt className="text-xs leading-relaxed text-muted-foreground">
                {label}
            </dt>
            <dd className="min-w-0 text-sm leading-relaxed font-medium break-words whitespace-pre-line">
                {value}
                {detail && (
                    <span className="mt-0.5 block text-xs font-normal text-muted-foreground">
                        {detail}
                    </span>
                )}
            </dd>
        </div>
    );
}

export default function FreightDetailsCard({
    post,
}: {
    post: FreightDetailsPost;
}) {
    const scheduleCopy: Record<string, string> = {
        one_time: 'Direct offer',
        recurring: 'Tender',
    };
    const offerType = post.schedule_type
        ? (scheduleCopy[post.schedule_type] ??
          post.schedule_type.replaceAll('_', ' '))
        : null;

    // The route title already names the two towns. Keep location details in
    // the expanded map, not repeated as half-width rows in the load summary.
    const routeEstimate = post.route_snapshot
        ? [
              formatDistance(post.route_snapshot.distance_meters),
              formatDuration(post.route_snapshot.duration_seconds),
          ]
              .filter(Boolean)
              .join(' · ')
        : null;

    const pickup = post.pickup_date
        ? formatFreightDate(post.pickup_date)
        : 'Flexible';
    const pickupWindow = post.pickup_date_flexibility_days ?? 0;
    const itemDescription =
        post.cargo_description?.trim() &&
        post.cargo_description.trim() !== post.item_name?.trim()
            ? post.cargo_description
            : null;

    const hasDifferentTariffWeight =
        Boolean(post.tariff_weight) &&
        (!post.actual_weight ||
            Number(post.tariff_weight) !== Number(post.actual_weight) ||
            (post.tariff_weight_unit ?? post.weight_unit) !== post.weight_unit);

    const actualWeight = post.actual_weight
        ? `${post.actual_weight} ${post.weight_unit ?? 'ton'}`
        : null;
    const tariffWeight = post.tariff_weight
        ? `${post.tariff_weight} ${post.tariff_weight_unit ?? 'ton'}`
        : null;
    const tariff = post.tariff_amount
        ? `${post.tariff_currency ?? 'ETB'} ${post.tariff_amount}`
        : null;
    const truck = post.truck_type?.name
        ? `${post.truck_type.name}${post.truck_count ? ` × ${post.truck_count}` : ''}`
        : post.truck_count
          ? `${post.truck_count} trucks`
          : null;

    return (
        <section
            aria-label="Freight details"
            className="@container min-w-0 space-y-5"
        >
            <div className="grid min-w-0 gap-6 @min-[42rem]:grid-cols-2 @min-[42rem]:gap-8">
                <section
                    aria-labelledby="freight-route-heading"
                    className="min-w-0 space-y-4"
                >
                    <h2
                        id="freight-route-heading"
                        className="flex items-center gap-2 border-b border-border pb-3 text-sm font-semibold"
                    >
                        <Route
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Route &amp; pickup
                    </h2>
                    <dl className="divide-y divide-border/50">
                        <Fact
                            label="Pickup date"
                            value={pickup}
                            detail={post.pickup_time?.slice(0, 5) || undefined}
                        />
                        {pickupWindow > 0 && (
                            <Fact
                                label="Pickup window"
                                value={`+${pickupWindow} days`}
                            />
                        )}
                        {offerType && <Fact label="Offer" value={offerType} />}
                        {routeEstimate && (
                            <Fact
                                label="Distance / time"
                                value={routeEstimate}
                            />
                        )}
                        <Fact
                            label="Listing"
                            value={freightLifecycleLabel(post)}
                        />
                    </dl>
                </section>

                <section
                    aria-labelledby="freight-cargo-heading"
                    className="min-w-0 space-y-4"
                >
                    <h2
                        id="freight-cargo-heading"
                        className="flex items-center gap-2 border-b border-border pb-3 text-sm font-semibold"
                    >
                        <Package
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Cargo &amp; truck
                    </h2>
                    <div className="min-w-0 space-y-1">
                        <p className="text-sm leading-relaxed font-semibold break-words">
                            {post.item_name ?? 'Cargo not specified'}
                        </p>
                        {itemDescription && (
                            <p className="text-sm leading-relaxed break-words whitespace-pre-line text-muted-foreground">
                                {itemDescription}
                            </p>
                        )}
                    </div>
                    <dl className="divide-y divide-border/50">
                        {post.cargo_type && (
                            <Fact
                                label="Cargo type"
                                value={post.cargo_type.name}
                            />
                        )}
                        {actualWeight && (
                            <Fact label="Weight" value={actualWeight} />
                        )}
                        {hasDifferentTariffWeight && tariffWeight && (
                            <Fact label="Tariff weight" value={tariffWeight} />
                        )}
                        {tariff && (
                            <Fact
                                label="Tariff rate"
                                value={
                                    <span className="tabular-nums">
                                        {tariff}
                                    </span>
                                }
                            />
                        )}
                        {truck && <Fact label="Truck" value={truck} />}
                        {post.insurance_required && (
                            <Fact
                                label="Insurance"
                                value={
                                    post.insurance_value
                                        ? `${post.tariff_currency ?? 'ETB'} ${post.insurance_value}`
                                        : 'Required'
                                }
                            />
                        )}
                        {post.depreciation_rate && (
                            <Fact
                                label="Depreciation"
                                value={`${post.depreciation_rate}%`}
                            />
                        )}
                    </dl>
                </section>
            </div>

            <CollapsibleRouteMap
                fromLat={latitudeOf(post.origin)}
                fromLng={longitudeOf(post.origin)}
                toLat={latitudeOf(post.destination)}
                toLng={longitudeOf(post.destination)}
                fromName={post.origin?.name}
                toName={post.destination?.name}
                routeSnapshot={post.route_snapshot}
            />

            {(post.origin_site_note || post.destination_site_note) && (
                <section
                    aria-labelledby="freight-site-notes-heading"
                    className="space-y-3 border-t border-border pt-4"
                >
                    <h2
                        id="freight-site-notes-heading"
                        className="flex items-center gap-2 text-sm font-semibold"
                    >
                        <ClipboardList
                            className="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                        Pickup &amp; delivery notes
                    </h2>
                    <dl className="divide-y divide-border/50">
                        {post.origin_site_note && (
                            <Fact
                                label="Pickup"
                                value={post.origin_site_note}
                            />
                        )}
                        {post.destination_site_note && (
                            <Fact
                                label="Delivery"
                                value={post.destination_site_note}
                            />
                        )}
                    </dl>
                </section>
            )}

            <p className="flex items-center gap-2 border-t border-border pt-3 text-xs text-muted-foreground">
                <CalendarDays
                    className="size-3.5 shrink-0"
                    aria-hidden="true"
                />
                {freightPublicationLabel(post)}
            </p>
        </section>
    );
}
