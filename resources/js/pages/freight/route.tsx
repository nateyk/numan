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
                    {/* Left — form inspired by Transport Booking */}
                    <div className="lg:col-span-5">
                        <div className="rounded-xl border bg-card p-5 shadow-sm">
                            <div className="space-y-4">
                                <div>
                                    <Label className="flex items-center gap-2 text-sm">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <svg className="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </span>
                                        Pickup Date
                                    </Label>
                                    <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} className="mt-1" />
                                    {form.errors.pickup_date && <p className="text-xs text-destructive mt-1">{form.errors.pickup_date}</p>}
                                </div>

                                <div>
                                    <Label className="flex items-center gap-2 text-sm">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <svg className="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        Pickup Town *
                                    </Label>
                                    <LocationSearchInput
                                        initialName={post.originLocation ? `${post.originLocation.name}, ${post.originLocation.region_name}` : undefined}
                                        onSelectLocation={handleOriginSelect}
                                        placeholder="Enter Pick-Up Town"
                                        className="mt-1"
                                    />
                                    {form.errors.origin_location_id && <p className="text-xs text-destructive mt-1">{form.errors.origin_location_id}</p>}
                                </div>

                                <div>
                                    <Label className="flex items-center gap-2 text-sm">
                                        <span className="flex size-6 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <svg className="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </span>
                                        Drop-Off Town *
                                    </Label>
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
                                    <svg className="mr-2 size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                    Search Route
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Right — map with distance/time footer like inspiration */}
                    <div className="lg:col-span-7">
                        <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                            <RoutePreviewMap fromLat={originLat} fromLng={originLng} toLat={destLat} toLng={destLng} geometry={routePreview?.geometry} className="h-[420px] rounded-none border-0" />
                            <div className="grid grid-cols-2 divide-x border-t bg-muted/30">
                                <div className="flex items-center gap-3 p-4">
                                    <span className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4" /></svg>
                                    </span>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">TOTAL DISTANCE</p>
                                        <p className="font-bold">{routePreview ? `${(routePreview.distance_meters / 1000).toFixed(1)} KM` : '0 KM'}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 p-4">
                                    <span className="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </span>
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">TOTAL TIME</p>
                                        <p className="font-bold">{routePreview ? `${Math.round(routePreview.duration_seconds / 60)} min` : '0 min'}</p>
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
