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
                <form onSubmit={submit}>
                    <div className="overflow-hidden rounded-lg border bg-card">
                        <div className="grid gap-0 lg:grid-cols-12">
                            {/* Left — form */}
                            <div className="space-y-4 p-6 lg:col-span-5">
                                <div>
                                    <Label>Pickup Date</Label>
                                    <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} className="mt-1" />
                                    {form.errors.pickup_date && <p className="text-xs text-destructive mt-1">{form.errors.pickup_date}</p>}
                                </div>
                                <div>
                                    <Label>Pickup Town *</Label>
                                    <LocationSearchInput
                                        initialName={post.originLocation ? `${post.originLocation.name}, ${post.originLocation.region_name}` : undefined}
                                        onSelectLocation={handleOriginSelect}
                                        placeholder="Enter Pick-Up Town"
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
                                        className="mt-1"
                                    />
                                    {form.errors.destination_location_id && <p className="text-xs text-destructive mt-1">{form.errors.destination_location_id}</p>}
                                </div>
                                <input type="hidden" name="origin_exact_lat" value={form.data.origin_exact_lat} />
                                <input type="hidden" name="origin_exact_lng" value={form.data.origin_exact_lng} />
                                <input type="hidden" name="destination_exact_lat" value={form.data.destination_exact_lat} />
                                <input type="hidden" name="destination_exact_lng" value={form.data.destination_exact_lng} />
                                <Button type="submit" disabled={form.processing || !form.data.origin_location_id || !form.data.destination_location_id} className="w-full">
                                    Search Route
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
                                            <p className="text-sm font-bold">{routePreview ? `${(routePreview.distance_meters / 1000).toFixed(1)} KM` : '0 KM'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 p-4">
                                        <span className="flex size-8 items-center justify-center rounded-full bg-muted">
                                            <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </span>
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">TOTAL TIME</p>
                                            <p className="text-sm font-bold">{routePreview ? `${Math.round(routePreview.duration_seconds / 60)} min` : '0 min'}</p>
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
