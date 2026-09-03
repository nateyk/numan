import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import LocationSearchInput from '@/components/maps/location-search-input';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
        pickup_date: string | null;
        originLocation?: { id: number; name: string; region_name: string } | null;
        destinationLocation?: { id: number; name: string; region_name: string } | null;
    };
    locations: Array<{ id: number; name: string; region_name: string; slug: string }>;
    routePreview: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
}

export default function FreightRoute({ post, routePreview }: Props) {
    const form = useForm({
        origin_location_id: post.origin_location_id ?? '',
        destination_location_id: post.destination_location_id ?? '',
        origin_exact_lat: post.origin_exact_lat ?? '',
        origin_exact_lng: post.origin_exact_lng ?? '',
        destination_exact_lat: post.destination_exact_lat ?? '',
        destination_exact_lng: post.destination_exact_lng ?? '',
        origin_point_confirmed_at: post.origin_point_confirmed_at ?? '',
        destination_point_confirmed_at: post.destination_point_confirmed_at ?? '',
        pickup_date: post.pickup_date ? String(post.pickup_date).slice(0, 10) : '',
    });

    const handleOriginSelect = (loc: { id: number; name: string; region_name: string; latitude: string | null; longitude: string | null } | null) => {
        if (!loc) {
            form.setData('origin_location_id', '');
            form.setData('origin_exact_lat', '');
            form.setData('origin_exact_lng', '');
            return;
        }
        form.setData('origin_location_id', loc.id);
        if (loc.latitude && loc.longitude) {
            form.setData('origin_exact_lat', String(loc.latitude));
            form.setData('origin_exact_lng', String(loc.longitude));
        }
    };

    const handleDestinationSelect = (loc: { id: number; name: string; region_name: string; latitude: string | null; longitude: string | null } | null) => {
        if (!loc) {
            form.setData('destination_location_id', '');
            form.setData('destination_exact_lat', '');
            form.setData('destination_exact_lng', '');
            return;
        }
        form.setData('destination_location_id', loc.id);
        if (loc.latitude && loc.longitude) {
            form.setData('destination_exact_lat', String(loc.latitude));
            form.setData('destination_exact_lng', String(loc.longitude));
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/my-freight/${post.ulid}/route`);
    };

    const originLat = Number(form.data.origin_exact_lat) || null;
    const originLng = Number(form.data.origin_exact_lng) || null;
    const destLat = Number(form.data.destination_exact_lat) || null;
    const destLng = Number(form.data.destination_exact_lng) || null;

    return (
        <AppLayout>
            <Head title="Route & Schedule" />
            <FreightWizardShell ulid={post.ulid} currentStep={0}>
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-12">
                    <div className="space-y-4 lg:col-span-5">
                        <p className="text-sm text-muted-foreground">Search 934 major Ethiopian towns — e.g., Addis Abeba, Hawassa, Mekelle — then see route on Leaflet map. Towns include region, population, elevation.</p>
                        <div>
                            <Label>Origin Town *</Label>
                            <LocationSearchInput
                                initialName={post.originLocation ? `${post.originLocation.name}, ${post.originLocation.region_name}` : undefined}
                                onSelectLocation={handleOriginSelect}
                                placeholder="Search origin — e.g., Addis Abeba…"
                                className="mt-1"
                            />
                            {form.data.origin_exact_lat && form.data.origin_exact_lng && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {form.data.origin_exact_lat}, {form.data.origin_exact_lng} {form.data.origin_location_id ? `• ID ${form.data.origin_location_id}` : ''}
                                </p>
                            )}
                            {form.errors.origin_location_id && <p className="text-xs text-destructive">{form.errors.origin_location_id}</p>}
                        </div>
                        <div>
                            <Label>Destination Town *</Label>
                            <LocationSearchInput
                                initialName={post.destinationLocation ? `${post.destinationLocation.name}, ${post.destinationLocation.region_name}` : undefined}
                                onSelectLocation={handleDestinationSelect}
                                placeholder="Search destination — e.g., Dire Dawa…"
                                className="mt-1"
                            />
                            {form.data.destination_exact_lat && form.data.destination_exact_lng && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {form.data.destination_exact_lat}, {form.data.destination_exact_lng} {form.data.destination_location_id ? `• ID ${form.data.destination_location_id}` : ''}
                                </p>
                            )}
                            {form.errors.destination_location_id && <p className="text-xs text-destructive">{form.errors.destination_location_id}</p>}
                        </div>
                        {/* Hidden exact coords for submission, auto-filled from town selection */}
                        <input type="hidden" name="origin_exact_lat" value={form.data.origin_exact_lat} />
                        <input type="hidden" name="origin_exact_lng" value={form.data.origin_exact_lng} />
                        <input type="hidden" name="destination_exact_lat" value={form.data.destination_exact_lat} />
                        <input type="hidden" name="destination_exact_lng" value={form.data.destination_exact_lng} />
                        <div>
                            <Label>Pickup Date</Label>
                            <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} />
                            {form.errors.pickup_date && <p className="text-xs text-destructive">{form.errors.pickup_date}</p>}
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing || !form.data.origin_location_id || !form.data.destination_location_id}>
                                Continue →
                            </Button>
                        </div>
                    </div>
                    <div className="lg:col-span-7">
                        <RoutePreviewMap fromLat={originLat} fromLng={originLng} toLat={destLat} toLng={destLng} geometry={routePreview?.geometry} className="h-[420px]" />
                        {routePreview ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                {(routePreview.distance_meters / 1000).toFixed(1)} km — approx. {Math.round(routePreview.duration_seconds / 60)} min {originLat && destLat ? '• click map to fine-tune' : ''}
                            </p>
                        ) : originLat && destLat ? (
                            <p className="mt-2 text-sm text-muted-foreground">Fetching OSRM route for selected towns… Map shows markers; select both towns to see route.</p>
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">Select origin & destination towns to see route. Or click map after search to adjust.</p>
                        )}
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
