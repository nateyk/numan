import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';

interface Props {
    post: {
        ulid: string;
        status: string;
        cargo_description: string | null;
        cargo_type?: { name: string } | null;
        truck_type?: { name: string } | null;
        truck_count?: number | null;
        schedule_type?: string | null;
        pickup_date: string | null;
        pickup_time?: string | null;
        origin_exact_lat: string | null;
        origin_exact_lng: string | null;
        destination_exact_lat: string | null;
        destination_exact_lng: string | null;
        origin_location?: { name: string; region_name: string } | null;
        destination_location?: { name: string; region_name: string } | null;
        user?: { id: number; name: string; email: string } | null;
        route_snapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
        moderation_logs?: Array<{ id: number; from_status: string; to_status: string; reason: string | null; moderator: string | null; created_at: string | null }>;
        created_at: string | null;
    };
}

export default function AdminFreightShow({ post }: Props) {
    const form = useForm({ to_status: 'active', reason: '' });

    const moderate = (status: string) => {
        form.setData('to_status', status);
        form.post(`/admin/freight/${post.ulid}/moderate`, { preserveScroll: true });
    };

    const canApprove = post.status === 'submitted' || post.status === 'under_review';
    const canReject = post.status === 'submitted' || post.status === 'under_review';
    const canUnderReview = post.status === 'submitted';

    return (
        <AdminLayout>
            <Head title={`Freight ${post.ulid.slice(0, 8)}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">Freight {post.ulid.slice(0, 8)}</h1>
                    <Badge variant={post.status === 'active' ? 'default' : post.status === 'rejected' ? 'destructive' : 'secondary'}>{post.status}</Badge>
                </div>

                <RoutePreviewMap
                    fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null}
                    fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null}
                    toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null}
                    toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null}
                    geometry={(post.route_snapshot as { geometry?: unknown })?.geometry}
                    className="h-[360px]"
                />
                {post.route_snapshot && (
                    <p className="text-sm text-muted-foreground">
                        {(post.route_snapshot as { distance_meters: number; duration_seconds: number }).distance_meters / 1000 > 0
                            ? `${((post.route_snapshot as { distance_meters: number }).distance_meters / 1000).toFixed(1)} km — approx. ${Math.round((post.route_snapshot as { duration_seconds: number }).duration_seconds / 60)} min`
                            : 'Route snapshot available'}
                    </p>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle className="text-base">Route & Schedule</CardTitle></CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p><span className="text-muted-foreground">Origin:</span> {post.origin_location?.name ?? '—'} ({post.origin_location?.region_name ?? '—'})</p>
                            <p><span className="text-muted-foreground">Destination:</span> {post.destination_location?.name ?? '—'} ({post.destination_location?.region_name ?? '—'})</p>
                            <p><span className="text-muted-foreground">Exact:</span> {post.origin_exact_lat ?? '—'},{post.origin_exact_lng ?? '—'} → {post.destination_exact_lat ?? '—'},{post.destination_exact_lng ?? '—'}</p>
                            <p><span className="text-muted-foreground">Schedule:</span> {post.schedule_type ?? '—'} • {post.pickup_date ? String(post.pickup_date).slice(0, 10) : '—'} {post.pickup_time ?? ''}</p>
                            <p><span className="text-muted-foreground">Created:</span> {post.created_at ? new Date(post.created_at).toLocaleString() : '—'}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader><CardTitle className="text-base">Cargo & Truck</CardTitle></CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p><span className="text-muted-foreground">Cargo:</span> {post.cargo_type?.name ?? '—'} — {post.cargo_description ?? '—'}</p>
                            <p><span className="text-muted-foreground">Truck:</span> {post.truck_type?.name ?? '—'} × {post.truck_count ?? '—'}</p>
                            <p><span className="text-muted-foreground">Consignor:</span> {post.user?.name ?? '—'} ({post.user?.email ?? '—'})</p>
                        </CardContent>
                    </Card>
                </div>

                {post.moderation_logs && post.moderation_logs.length > 0 && (
                    <Card>
                        <CardHeader><CardTitle className="text-base">Moderation History</CardTitle></CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {post.moderation_logs.map((log) => (
                                <div key={log.id} className="flex justify-between border-b py-2 last:border-0">
                                    <span>{log.from_status} → <span className="font-medium">{log.to_status}</span> by {log.moderator ?? '—'}</span>
                                    <span className="text-muted-foreground">{log.created_at ? new Date(log.created_at).toLocaleString() : ''}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader><CardTitle className="text-base">Moderate</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        <div>
                            <Label>Reason (required if rejecting)</Label>
                            <textarea value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} rows={3} className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Reason for rejection or note" />
                            {form.errors.reason && <p className="text-xs text-destructive">{form.errors.reason}</p>}
                            {form.errors.to_status && <p className="text-xs text-destructive">{form.errors.to_status}</p>}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button onClick={() => moderate('active')} disabled={form.processing || !canApprove}>Approve → Active</Button>
                            <Button variant="destructive" onClick={() => moderate('rejected')} disabled={form.processing || !canReject}>Reject</Button>
                            <Button variant="outline" onClick={() => moderate('under_review')} disabled={form.processing || !canUnderReview}>Mark Under Review</Button>
                        </div>
                        {!canApprove && <p className="text-xs text-muted-foreground">Only Submitted / Under Review can be approved or rejected. Current: {post.status}</p>}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}
