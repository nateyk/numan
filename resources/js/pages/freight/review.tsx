import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import { useState } from 'react';

interface Props {
    post: { ulid: string; origin_location_id: number | null; destination_location_id: number | null; cargo_description: string | null; truck_count: number | null; pickup_date: string | null; origin_exact_lat: string | null; origin_exact_lng: string | null; destination_exact_lat: string | null; destination_exact_lng: string | null; originLocation?: { name: string }; destinationLocation?: { name: string }; cargoType?: { name: string }; truckType?: { name: string }; routeSnapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null };
    completionScore: number;
}

export default function FreightReview({ post, completionScore }: Props) {
    const [confirmed, setConfirmed] = useState(false);
    const form = useForm<{ confirm?: string }>({});

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirmed) return;
        form.post(`/my-freight/${post.ulid}/submit`);
    };

    return (
        <AppLayout>
            <Head title="Review & Submit" />
            <FreightWizardShell ulid={post.ulid} currentStep={3}>
                <div className="space-y-6">
                    <RoutePreviewMap fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null} fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null} toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null} toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null} geometry={post.routeSnapshot?.geometry} />
                    <div className="rounded-md border p-4">
                        <h3 className="font-medium">Freight Summary</h3>
                        <div className="mt-2 space-y-1 text-sm">
                            <p>{post.originLocation?.name ?? 'Origin'} → {post.destinationLocation?.name ?? 'Destination'}</p>
                            <p>Cargo: {post.cargoType?.name ?? post.cargo_description ?? '—'} | Truck: {post.truckType?.name ?? '—'} × {post.truck_count ?? '—'}</p>
                            <p>Pickup: {post.pickup_date ? String(post.pickup_date).slice(0, 10) : '—'}</p>
                            {post.routeSnapshot && <p>Distance: {(post.routeSnapshot.distance_meters / 1000).toFixed(1)} km — approx. {Math.round(post.routeSnapshot.duration_seconds / 60)} min</p>}
                        </div>
                        <div className="mt-2 flex gap-2 text-xs">
                            <a href={`/my-freight/${post.ulid}/route`} className="text-primary hover:underline">Edit Route</a>
                            <a href={`/my-freight/${post.ulid}/cargo`} className="text-primary hover:underline">Edit Cargo</a>
                            <a href={`/my-freight/${post.ulid}/schedule`} className="text-primary hover:underline">Edit Schedule</a>
                        </div>
                    </div>
                    <p className="text-sm text-muted-foreground">Your plan — {completionScore}% complete</p>
                    <form onSubmit={submit} className="space-y-4">
                        <label className="flex items-center gap-2 text-sm">
                            <input type="checkbox" checked={confirmed} onChange={(e) => setConfirmed(e.target.checked)} />
                            I confirm that the freight information is accurate.
                        </label>
                        {form.errors.confirm && <p className="text-xs text-destructive">{form.errors.confirm}</p>}
                        <div className="flex gap-2">
                            <Button type="submit" disabled={!confirmed || form.processing}>Submit for review</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Save draft</Button>
                        </div>
                    </form>
                </div>
            </FreightWizardShell>
        </AppLayout>
    );
}
