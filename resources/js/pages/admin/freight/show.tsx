import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';

interface Props {
    post: {
        ulid: string;
        status: string;
        cargo_description: string | null;
        pickup_date: string | null;
        user?: { name: string; email: string };
        originLocation?: { name: string; region_name: string };
        destinationLocation?: { name: string; region_name: string };
        cargoType?: { name: string };
        truckType?: { name: string };
        origin_exact_lat: string | null;
        origin_exact_lng: string | null;
        destination_exact_lat: string | null;
        destination_exact_lng: string | null;
        routeSnapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
    };
}

export default function AdminFreightShow({ post }: Props) {
    const form = useForm({ to_status: 'active', reason: '' });

    const moderate = (status: string) => {
        form.setData('to_status', status);
        form.post(`/admin/freight/${post.ulid}/moderate`, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title={`Freight ${post.ulid}`} />
            <div className="space-y-6 p-6">
                <h1 className="text-xl font-bold">Freight {post.ulid.slice(0, 8)} — {post.status}</h1>
                <RoutePreviewMap fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null} fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null} toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null} toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null} geometry={post.routeSnapshot?.geometry} />
                <div className="rounded-md border p-4 text-sm space-y-1">
                    <p>{post.originLocation?.name ?? '—'} → {post.destinationLocation?.name ?? '—'}</p>
                    <p>Cargo: {post.cargoType?.name ?? post.cargo_description ?? '—'}</p>
                    <p>Truck: {post.truckType?.name ?? '—'}</p>
                    <p>Pickup: {post.pickup_date ? String(post.pickup_date).slice(0, 10) : '—'}</p>
                    <p>User: {post.user?.name ?? '—'} ({post.user?.email ?? '—'})</p>
                </div>
                <div className="space-y-2">
                    <Label>Reason (required if rejecting)</Label>
                    <textarea value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} rows={3} className="w-full rounded-md border bg-background px-3 py-2 text-sm" placeholder="Reason" />
                    {form.errors.reason && <p className="text-xs text-destructive">{form.errors.reason}</p>}
                    {form.errors.to_status && <p className="text-xs text-destructive">{form.errors.to_status}</p>}
                    <div className="flex gap-2">
                        <Button onClick={() => moderate('active')} disabled={form.processing}>Approve → Active</Button>
                        <Button variant="destructive" onClick={() => moderate('rejected')} disabled={form.processing}>Reject</Button>
                        <Button variant="outline" onClick={() => moderate('under_review')} disabled={form.processing}>Mark Under Review</Button>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
