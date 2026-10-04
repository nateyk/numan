import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import LocationSearchInput, { type Location } from '@/components/maps/location-search-input';
import { resolveOsmLocation } from '@/lib/osm-locations';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect, useState } from 'react';

function formatDuration(seconds: number): string {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.round((seconds % 3600) / 60);

    if (hours > 0) {
        return minutes > 0 ? `${hours}h ${minutes}min` : `${hours}h`;
    }
    return `${minutes} min`;
}

interface RoutePreviewData {
    distance_meters: number;
    duration_seconds: number;
    geometry: unknown;
}

interface Props {
    post: {
        ulid: string;
        origin_location_id: number | null;
        destination_location_id: number | null;
        origin_exact_lat: string | null;
        origin_exact_lng: string | null;
        destination_exact_lat: string | null;
        destination_exact_lng: string | null;
        origin_point_confirmed_at: string | null;
        destination_point_confirmed_at: string | null;
        origin_site_note: string | null;
        destination_site_note: string | null;
        pickup_date: string | null;
        originLocation?: { id: number; name: string; region_name: string } | null;
        destinationLocation?: { id: number; name: string; region_name: string } | null;
    };
    locations: Array<{ id: number; name: string; region_name: string; slug: string }>;
    routePreview: RoutePreviewData | null;
}

export default function FreightRoute({ post, routePreview: initialRoutePreview }: Props) {
    const [routePreview, setRoutePreview] = useState<RoutePreviewData | null>(initialRoutePreview);
    const [calculatingRoute, setCalculatingRoute] = useState(false);
    // True while an OSM pick is being written to the database, so the form cannot
    // be saved with an empty location id in the gap.
    const [resolvingLocation, setResolvingLocation] = useState(false);

    const form = useForm({
        origin_location_id: post.origin_location_id ?? '',
        destination_location_id: post.destination_location_id ?? '',
        origin_exact_lat: post.origin_exact_lat ?? '',
        origin_exact_lng: post.origin_exact_lng ?? '',
        destination_exact_lat: post.destination_exact_lat ?? '',
        destination_exact_lng: post.destination_exact_lng ?? '',
        origin_point_confirmed_at: post.origin_point_confirmed_at ?? '',
        destination_point_confirmed_at: post.destination_point_confirmed_at ?? '',
        origin_site_note: post.origin_site_note ?? '',
        destination_site_note: post.destination_site_note ?? '',
    });

    // An OSM result has no database id, so it is resolved to a real locations row
    // before it is stored. Without this the route step could not be saved at all,
    // because the submit button needs a non-empty location id.
    const handleOriginSelect = async (loc: Location | null) => {
        if (!loc) {
            form.setData('origin_location_id', '');
            form.setData('origin_exact_lat', '');
            form.setData('origin_exact_lng', '');
            form.setData('origin_point_confirmed_at', '');
            return;
        }

        setResolvingLocation(true);

        try {
            const locationId = await resolveOsmLocation(loc);

            if (locationId === null) {
                return;
            }

            form.setData('origin_location_id', locationId);

            if (loc.latitude != null && loc.longitude != null) {
                form.setData('origin_exact_lat', String(loc.latitude));
                form.setData('origin_exact_lng', String(loc.longitude));
                form.setData('origin_point_confirmed_at', new Date().toISOString());
            }
        } finally {
            setResolvingLocation(false);
        }
    };

    const handleDestinationSelect = async (loc: Location | null) => {
        if (!loc) {
            form.setData('destination_location_id', '');
            form.setData('destination_exact_lat', '');
            form.setData('destination_exact_lng', '');
            form.setData('destination_point_confirmed_at', '');
            return;
        }

        setResolvingLocation(true);

        try {
            const locationId = await resolveOsmLocation(loc);

            if (locationId === null) {
                return;
            }

            form.setData('destination_location_id', locationId);

            if (loc.latitude != null && loc.longitude != null) {
                form.setData('destination_exact_lat', String(loc.latitude));
                form.setData('destination_exact_lng', String(loc.longitude));
                form.setData('destination_point_confirmed_at', new Date().toISOString());
            }
        } finally {
            setResolvingLocation(false);
        }
    };

    const originLat = Number(form.data.origin_exact_lat) || null;
    const originLng = Number(form.data.origin_exact_lng) || null;
    const destLat = Number(form.data.destination_exact_lat) || null;
    const destLng = Number(form.data.destination_exact_lng) || null;

    // Auto-calculate route when both origin and destination are selected
    useEffect(() => {
        // == null, not a falsy check: 0 is a real latitude and longitude.
        if (originLat == null || originLng == null || destLat == null || destLng == null) {
            return;
        }

        const calculateRoute = async () => {
            setCalculatingRoute(true);
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                const response = await fetch('/freight/routes/preview', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        from_lat: originLat,
                        from_lng: originLng,
                        to_lat: destLat,
                        to_lng: destLng,
                    }),
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data && data.distance_meters) {
                        setRoutePreview(data);
                    }
                }
            } catch {
                // A failed preview is not worth interrupting the form over: the
                // distances below simply stay at zero and the user can still
                // save the route.
            } finally {
                setCalculatingRoute(false);
            }
        };

        // Debounce the route calculation
        const timeoutId = setTimeout(calculateRoute, 500);
        return () => clearTimeout(timeoutId);
    }, [originLat, originLng, destLat, destLng]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/my-freight/${post.ulid}/route`);
    };

    return (
        <AppLayout>
            <Head title="Route & Schedule" />
            <FreightWizardShell ulid={post.ulid} currentStep={0}>
                <form onSubmit={submit}>
                    <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div className="grid gap-0 lg:grid-cols-12">
                            {/* Left — form */}
                            <div className="space-y-4 p-6 lg:col-span-5">
                                <div>
                                    <Label>Pickup Town *</Label>
                                    <LocationSearchInput
                                        initialName={post.originLocation ? `${post.originLocation.name}, ${post.originLocation.region_name}` : undefined}
                                        onSelectLocation={handleOriginSelect}
                                        placeholder="Enter Pick-Up Town"
                                        type="freight"
                                        country="ET"
                                        className="mt-1"
                                    />
                                    {form.errors.origin_location_id && <p className="text-xs text-destructive mt-1">{form.errors.origin_location_id}</p>}
                                </div>
                                <div>
                                    <Label>Drop-Off Town *</Label>
                                    <LocationSearchInput
                                        initialName={post.destinationLocation ? `${post.destinationLocation.name}, ${post.destinationLocation.region_name}` : undefined}
                                        onSelectLocation={handleDestinationSelect}
                                        placeholder="Enter Drop-Off Town"
                                        type="freight"
                                        country="ET"
                                        className="mt-1"
                                    />
                                    {form.errors.destination_location_id && <p className="text-xs text-destructive mt-1">{form.errors.destination_location_id}</p>}
                                </div>
                                <div>
                                    <Label>Pickup Site Note</Label>
                                    <Input
                                        value={form.data.origin_site_note}
                                        onChange={(e) => form.setData('origin_site_note', e.target.value)}
                                        placeholder="e.g., Gate 3, near the main entrance"
                                        className="mt-1"
                                    />
                                    {form.errors.origin_site_note && <p className="text-xs text-destructive mt-1">{form.errors.origin_site_note}</p>}
                                </div>
                                <div>
                                    <Label>Drop-Off Site Note</Label>
                                    <Input
                                        value={form.data.destination_site_note}
                                        onChange={(e) => form.setData('destination_site_note', e.target.value)}
                                        placeholder="e.g., Warehouse B, loading dock"
                                        className="mt-1"
                                    />
                                    {form.errors.destination_site_note && <p className="text-xs text-destructive mt-1">{form.errors.destination_site_note}</p>}
                                </div>
                                <input type="hidden" name="origin_exact_lat" value={form.data.origin_exact_lat} />
                                <input type="hidden" name="origin_exact_lng" value={form.data.origin_exact_lng} />
                                <input type="hidden" name="destination_exact_lat" value={form.data.destination_exact_lat} />
                                <input type="hidden" name="destination_exact_lng" value={form.data.destination_exact_lng} />
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        resolvingLocation ||
                                        !form.data.origin_location_id ||
                                        !form.data.destination_location_id
                                    }
                                    className="w-full"
                                >
                                    {resolvingLocation ? 'Saving location…' : 'Save Route'}
                                </Button>
                            </div>

                            {/* Right — map */}
                            <div className="lg:col-span-7 border-t lg:border-l lg:border-t-0">
                                <RoutePreviewMap fromLat={originLat} fromLng={originLng} toLat={destLat} toLng={destLng} geometry={routePreview?.geometry} className="h-[420px] rounded-none border-0" />
                                <div className="grid grid-cols-2 divide-x border-t">
                                    <div className="flex items-center gap-3 p-4">
                                        <span className="flex size-8 items-center justify-center rounded-full bg-muted">
                                            <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4" /></svg>
                                        </span>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">TOTAL DISTANCE</p>
                                            <p className="text-sm font-bold">
                                                {calculatingRoute ? (
                                                    <span className="text-muted-foreground">Calculating...</span>
                                                ) : routePreview ? (
                                                    `${(routePreview.distance_meters / 1000).toFixed(1)} KM`
                                                ) : (
                                                    '0 KM'
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 p-4">
                                        <span className="flex size-8 items-center justify-center rounded-full bg-muted">
                                            <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">TOTAL TIME</p>
                                            <p className="text-sm font-bold">
                                                {calculatingRoute ? (
                                                    <span className="text-muted-foreground">Calculating...</span>
                                                ) : routePreview ? (
                                                    formatDuration(routePreview.duration_seconds)
                                                ) : (
                                                    '0 min'
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
