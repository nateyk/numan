import CollapsibleRouteMap, {
    type RouteSnapshot,
} from '@/components/freight/collapsible-route-map';
import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Package } from 'lucide-react';

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

/**
 * A fact in the load sheet. The label sits above the value so a long item
 * description wraps at the card edge instead of being squeezed into the
 * right-hand half of a justify-between row.
 */
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
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium">
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

function Note({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-0.5 text-sm whitespace-pre-line">{children}</p>
        </div>
    );
}

export default function FreightDetailsCard({
    post,
}: {
    post: FreightDetailsPost;
}) {
    const origin = post.origin;
    const destination = post.destination;

    // The route is the page's headline and the map. Repeating its four
    // label/value rows here said the same thing three times on one screen, so
    // this card keeps only what the rest of the page does not already say.
    const weight: { value: string; detail?: string } = post.actual_weight
        ? { value: `${post.actual_weight} ${post.weight_unit ?? 'ton'}` }
        : post.tariff_weight
          ? {
                value: `${post.tariff_weight} ${post.tariff_weight_unit ?? 'ton'}`,
                detail: 'Tariff weight',
            }
          : { value: '—' };

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

    // The three decision facts used to sit in a strip above this card. They
    // belong to the load sheet, so they move in without their boxes: a stat
    // card inside a card is a card inside a card.
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

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Package className="size-4" /> Freight Details
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div>
                        <p className="text-xs text-muted-foreground">Tariff</p>
                        <p className="mt-0.5 text-xl font-bold tracking-tight sm:text-2xl">
                            {tariff}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Route</p>
                        <p className="mt-0.5 text-sm font-semibold sm:text-base">
                            {laneFact}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">Pickup</p>
                        <p className="mt-0.5 text-sm font-semibold sm:text-base">
                            {pickupValue}
                        </p>
                        {pickupSub && (
                            <p className="text-xs text-muted-foreground">
                                {pickupSub}
                            </p>
                        )}
                    </div>
                </div>

                <CollapsibleRouteMap
                    fromLat={latitudeOf(origin)}
                    fromLng={longitudeOf(origin)}
                    toLat={latitudeOf(destination)}
                    toLng={longitudeOf(destination)}
                    routeSnapshot={post.route_snapshot}
                />

                <dl className="space-y-4 border-t pt-4">
                    <Fact label="Item" value={post.item_name ?? '—'} />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Fact
                            label="Weight"
                            value={weight.value}
                            detail={weight.detail}
                        />
                        <Fact
                            label="Truck"
                            value={`${post.truck_type?.name ?? 'Any truck'}${post.truck_count ? ` ×${post.truck_count}` : ''}`}
                        />
                        <Fact
                            label="Schedule"
                            value={scheduleLabel}
                            detail={schedule?.detail}
                        />
                    </div>
                </dl>

                {description && (
                    <div className="border-t pt-4">
                        <p className="text-xs text-muted-foreground">
                            Cargo description
                        </p>
                        <p className="mt-1 text-sm whitespace-pre-line">
                            {description}
                        </p>
                    </div>
                )}

                {(post.origin_site_note || post.destination_site_note) && (
                    <div className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                        {post.origin_site_note && (
                            <Note label="Pickup note">
                                {post.origin_site_note}
                            </Note>
                        )}
                        {post.destination_site_note && (
                            <Note label="Delivery note">
                                {post.destination_site_note}
                            </Note>
                        )}
                    </div>
                )}

                {/* Only rendered when it says something. A row reading
                    "Insurance — No" on every post is noise. */}
                {(post.insurance_required || post.depreciation_rate) && (
                    <dl className="grid gap-4 border-t pt-4 sm:grid-cols-2">
                        {post.insurance_required && (
                            <Fact
                                label="Insurance"
                                value={
                                    post.insurance_value
                                        ? `${post.insurance_value} ${post.tariff_currency ?? 'ETB'}`
                                        : 'Required, value not set'
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
                )}
            </CardContent>
        </Card>
    );
}
