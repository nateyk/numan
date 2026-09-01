import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import LocationSearchInput from '@/components/maps/location-search-input';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    post: { ulid: string; origin_location_id: number | null; destination_location_id: number | null; origin_exact_lat: string | null; origin_exact_lng: string | null; destination_exact_lat: string | null; destination_exact_lng: string | null; origin_point_confirmed_at: string | null; destination_point_confirmed_at: string | null; pickup_date: string | null };
    locations: Array<{ id: number; name: string; region_name: string; slug: string }>;
    routePreview: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
}

export default function FreightRoute({ post, locations, routePreview }: Props) {
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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/my-freight/${post.ulid}/route`);
    };

    return (
        <AppLayout>
            <Head title="Route & Schedule" />
            <FreightWizardShell ulid={post.ulid} currentStep={0}>
                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-12">
                    <div className="space-y-4 lg:col-span-5">
                        <div>
                            <Label>Origin *</Label>
                            <select value={form.data.origin_location_id} onChange={(e) => form.setData('origin_location_id', e.target.value ? Number(e.target.value) : '')} className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">Select origin</option>
                                {locations.map((l) => (
                                    <option key={l.id} value={l.id}>{l.name} — {l.region_name}</option>
                                ))}
                            </select>
                            {form.errors.origin_location_id && <p className="text-xs text-destructive">{form.errors.origin_location_id}</p>}
                        </div>
                        <div>
                            <Label>Destination *</Label>
                            <select value={form.data.destination_location_id} onChange={(e) => form.setData('destination_location_id', e.target.value ? Number(e.target.value) : '')} className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm">
                                <option value="">Select destination</option>
                                {locations.map((l) => (
                                    <option key={l.id} value={l.id}>{l.name} — {l.region_name}</option>
                                ))}
                            </select>
                            {form.errors.destination_location_id && <p className="text-xs text-destructive">{form.errors.destination_location_id}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <Label>Origin Lat</Label>
                                <Input value={form.data.origin_exact_lat} onChange={(e) => form.setData('origin_exact_lat', e.target.value)} placeholder="9.03" />
                            </div>
                            <div>
                                <Label>Origin Lng</Label>
                                <Input value={form.data.origin_exact_lng} onChange={(e) => form.setData('origin_exact_lng', e.target.value)} placeholder="38.74" />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div>
                                <Label>Dest Lat</Label>
                                <Input value={form.data.destination_exact_lat} onChange={(e) => form.setData('destination_exact_lat', e.target.value)} placeholder="7.49" />
                            </div>
                            <div>
                                <Label>Dest Lng</Label>
                                <Input value={form.data.destination_exact_lng} onChange={(e) => form.setData('destination_exact_lng', e.target.value)} placeholder="38.42" />
                            </div>
                        </div>
                        <div>
                            <Label>Pickup Date</Label>
                            <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} />
                            {form.errors.pickup_date && <p className="text-xs text-destructive">{form.errors.pickup_date}</p>}
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>Continue →</Button>
                        </div>
                    </div>
                    <div className="lg:col-span-7">
                        <RoutePreviewMap fromLat={Number(form.data.origin_exact_lat) || null} fromLng={Number(form.data.origin_exact_lng) || null} toLat={Number(form.data.destination_exact_lat) || null} toLng={Number(form.data.destination_exact_lng) || null} geometry={routePreview?.geometry} />
                        {routePreview && (
                            <p className="mt-2 text-sm text-muted-foreground">
                                {(routePreview.distance_meters / 1000).toFixed(1)} km — approx. {Math.round(routePreview.duration_seconds / 60)} min
                            </p>
                        )}
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
