import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { MapPin, Package, Truck, Calendar, Route, Edit } from 'lucide-react';
import { useState } from 'react';

interface Props {
    post: { ulid: string; origin_location_id: number | null; destination_location_id: number | null; cargo_description: string | null; truck_count: number | null; pickup_date: string | null; origin_exact_lat: string | null; origin_exact_lng: string | null; destination_exact_lat: string | null; destination_exact_lng: string | null; originLocation?: { name: string; region_name: string }; destinationLocation?: { name: string; region_name: string }; origin_location?: { name: string; region_name: string }; destination_location?: { name: string; region_name: string }; cargoType?: { name: string }; cargo_type?: { name: string }; truckType?: { name: string }; truck_type?: { name: string }; routeSnapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null; route_snapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null };
    completionScore: number;
}

export default function FreightReview({ post, completionScore }: Props) {
    const [confirmed, setConfirmed] = useState(false);
    const form = useForm<{ confirm?: string }>({});

    const origin = (post as unknown as Record<string, unknown>).originLocation as { name: string; region_name: string } | undefined ?? (post as unknown as Record<string, unknown>).origin_location as { name: string; region_name: string } | undefined;
    const destination = (post as unknown as Record<string, unknown>).destinationLocation as { name: string; region_name: string } | undefined ?? (post as unknown as Record<string, unknown>).destination_location as { name: string; region_name: string } | undefined;
    const cargoType = (post as unknown as Record<string, unknown>).cargoType as { name: string } | undefined ?? (post as unknown as Record<string, unknown>).cargo_type as { name: string } | undefined;
    const truckType = (post as unknown as Record<string, unknown>).truckType as { name: string } | undefined ?? (post as unknown as Record<string, unknown>).truck_type as { name: string } | undefined;
    const routeSnapshot = (post as unknown as Record<string, unknown>).routeSnapshot as { distance_meters: number; duration_seconds: number; geometry: unknown } | undefined ?? (post as unknown as Record<string, unknown>).route_snapshot as { distance_meters: number; duration_seconds: number; geometry: unknown } | undefined;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirmed) return;
        form.post(`/my-freight/${post.ulid}/submit`);
    };

    return (
        <AppLayout>
            <Head title="Review & Submit" />
            <FreightWizardShell ulid={post.ulid} currentStep={3} completionScore={completionScore}>
                <div className="space-y-6">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base"><MapPin className="size-4" /> Route Preview</CardTitle>
                            <CardDescription>{origin?.name ?? 'Origin'} → {destination?.name ?? 'Destination'} • OSRM routed</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RoutePreviewMap fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null} fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null} toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null} toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null} geometry={routeSnapshot?.geometry} className="h-[320px]" />
                            {routeSnapshot && (
                                <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground"><Route className="size-4" /> {(routeSnapshot.distance_meters / 1000).toFixed(1)} km • {Math.round(routeSnapshot.duration_seconds / 60)} min</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Freight Summary</CardTitle>
                            <CardDescription>Review before submitting for admin approval</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p className="flex items-center gap-2"><MapPin className="size-4 text-muted-foreground" /> {origin?.name ?? 'Origin'} ({origin?.region_name ?? ''}) → {destination?.name ?? 'Destination'} ({destination?.region_name ?? ''})</p>
                                <p className="flex items-center gap-2"><Calendar className="size-4 text-muted-foreground" /> Pickup: {post.pickup_date ? String(post.pickup_date).slice(0, 10) : '—'}</p>
                                <p className="flex items-center gap-2"><Package className="size-4 text-muted-foreground" /> Cargo: {cargoType?.name ?? '—'} — {post.cargo_description ?? '—'}</p>
                                <p className="flex items-center gap-2"><Truck className="size-4 text-muted-foreground" /> Truck: {truckType?.name ?? '—'} × {post.truck_count ?? '—'}</p>
                            </div>
                            <Separator />
                            <div className="flex flex-wrap gap-2 text-xs">
                                <Button variant="ghost" size="sm" asChild><Link href={`/my-freight/${post.ulid}/route`}><Edit className="mr-1 size-3" /> Edit Route</Link></Button>
                                <Button variant="ghost" size="sm" asChild><Link href={`/my-freight/${post.ulid}/cargo`}><Edit className="mr-1 size-3" /> Edit Cargo</Link></Button>
                                <Button variant="ghost" size="sm" asChild><Link href={`/my-freight/${post.ulid}/schedule`}><Edit className="mr-1 size-3" /> Edit Schedule</Link></Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium">Completion</span>
                                <Badge variant="secondary">{completionScore}% complete</Badge>
                            </div>
                            <div className="mt-2 h-2 w-full rounded-full bg-muted">
                                <div className="h-2 rounded-full bg-primary transition-all" style={{ width: `${completionScore}%` }} />
                            </div>
                        </CardContent>
                    </Card>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="flex items-center space-x-2 rounded-md border p-3">
                            <Checkbox id="confirm" checked={confirmed} onCheckedChange={(v) => setConfirmed(v === true)} />
                            <Label htmlFor="confirm" className="text-sm font-normal leading-none">I confirm that the freight information is accurate and ready for admin review.</Label>
                        </div>
                        {form.errors.confirm && <p className="text-xs text-destructive">{form.errors.confirm}</p>}
                        <div className="flex gap-2">
                            <Button type="submit" disabled={!confirmed || form.processing} size="lg">Submit for review</Button>
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>Save draft</Button>
                        </div>
                    </form>
                </div>
            </FreightWizardShell>
        </AppLayout>
    );
}
