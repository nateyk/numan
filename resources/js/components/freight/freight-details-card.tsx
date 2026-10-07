import CollapsibleRouteMap, {
    type RouteSnapshot,
} from '@/components/freight/collapsible-route-map';
import { Badge } from '@/components/ui/badge';
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

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="shrink-0 text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
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

    const weight = post.actual_weight
        ? `${post.actual_weight} ${post.weight_unit ?? 'ton'}`
        : post.tariff_weight
          ? `${post.tariff_weight} ${post.tariff_weight_unit ?? 'ton'} (tariff)`
          : '—';

    // Two columns at xl, one below. An xl breakpoint rather than lg because the
    // right-hand Consignor card is already competing for width at lg.
    const twoColumns = 'grid gap-x-8 gap-y-2 sm:grid-cols-2';

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <Package className="size-4" /> Freight Details
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <CollapsibleRouteMap
                    fromLat={latitudeOf(origin)}
                    fromLng={longitudeOf(origin)}
                    toLat={latitudeOf(destination)}
                    toLng={longitudeOf(destination)}
                    routeSnapshot={post.route_snapshot}
                />

                <div className={twoColumns}>
                    <div className="space-y-2">
                        <Row label="Origin" value={origin?.name ?? '—'} />
                        {origin?.region_name && (
                            <Row
                                label="Origin region"
                                value={origin.region_name}
                            />
                        )}
                        {origin?.latitude !== null &&
                            origin?.latitude !== undefined && (
                                <Row
                                    label="Coordinates"
                                    value={`${Number(origin.latitude).toFixed(4)}, ${Number(origin.longitude).toFixed(4)}`}
                                />
                            )}
                        {post.origin_site_note && (
                            <Row
                                label="Pickup note"
                                value={post.origin_site_note}
                            />
                        )}
                    </div>

                    <div className="space-y-2">
                        <Row
                            label="Destination"
                            value={destination?.name ?? '—'}
                        />
                        {destination?.region_name && (
                            <Row
                                label="Destination region"
                                value={destination.region_name}
                            />
                        )}
                        {post.destination_site_note && (
                            <Row
                                label="Delivery note"
                                value={post.destination_site_note}
                            />
                        )}
                    </div>
                </div>

                <div className="border-t pt-4">
                    <div className={twoColumns}>
                        <div className="space-y-2">
                            <Row label="Item" value={post.item_name ?? '—'} />
                            <Row label="Weight" value={weight} />
                            <Row
                                label="Tariff"
                                value={
                                    post.tariff_amount
                                        ? `${post.tariff_amount} ${post.tariff_currency ?? 'ETB'}`
                                        : '—'
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Row
                                label="Cargo type"
                                value={post.cargo_type?.name ?? '—'}
                            />
                            <Row
                                label="Truck"
                                value={`${post.truck_type?.name ?? 'Any truck'}${post.truck_count ? ` ×${post.truck_count}` : ''}`}
                            />
                            <Row
                                label="Status"
                                value={
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {post.status}
                                    </Badge>
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="border-t pt-4">
                    <div className={twoColumns}>
                        <div className="space-y-2">
                            <Row
                                label="Pickup"
                                value={
                                    post.pickup_date
                                        ? `${post.pickup_date}${post.pickup_time ? ` ${post.pickup_time.slice(0, 5)}` : ''}${
                                              post.pickup_date_flexibility_days
                                                  ? ` ±${post.pickup_date_flexibility_days}d`
                                                  : ''
                                          }`
                                        : 'Flexible'
                                }
                            />
                            <Row
                                label="Schedule"
                                value={post.schedule_type ?? 'one_time'}
                            />
                        </div>

                        {/* Only rendered when it says something. A row reading
                            "Insurance — No" on every post is noise. */}
                        {(post.insurance_required ||
                            post.depreciation_rate) && (
                            <div className="space-y-2">
                                {post.insurance_required && (
                                    <Row
                                        label="Insurance"
                                        value={
                                            post.insurance_value
                                                ? `${post.insurance_value} ${post.tariff_currency ?? 'ETB'}`
                                                : 'Required, value not set'
                                        }
                                    />
                                )}
                                {post.depreciation_rate && (
                                    <Row
                                        label="Depreciation"
                                        value={`${post.depreciation_rate}%`}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
