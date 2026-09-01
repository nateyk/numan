import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useState } from 'react';

interface Freight {
    ulid: string;
    status: string;
    cargo_description: string | null;
    pickup_date: string | null;
    originLocation?: { name: string; region_name: string };
    destinationLocation?: { name: string; region_name: string };
    cargoType?: { name: string };
    truckType?: { name: string };
    routeSnapshot?: { distance_meters: number; duration_seconds: number } | null;
}

interface Props {
    freight: { data: Freight[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    filters: Record<string, string>;
}

export default function DiscoverIndex({ freight, filters }: Props) {
    const [origin, setOrigin] = useState(filters.origin ?? '');
    const [destination, setDestination] = useState(filters.destination ?? '');

    const search = () => {
        router.get('/freight', { origin, destination }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Discover Freight" />
            <div className="mx-auto max-w-6xl space-y-6 p-6">
                <h1 className="text-2xl font-bold">Discover Freight</h1>
                <div className="flex flex-wrap gap-2">
                    <Input placeholder="Origin ID" value={origin} onChange={(e) => setOrigin(e.target.value)} className="w-40" />
                    <Input placeholder="Destination ID" value={destination} onChange={(e) => setDestination(e.target.value)} className="w-40" />
                    <Button variant="outline" onClick={search}>Filter</Button>
                    <span className="py-2 text-sm text-muted-foreground">{freight.total ?? freight.data.length} results</span>
                </div>
                {freight.data.length === 0 ? (
                    <div className="rounded-md border p-12 text-center text-muted-foreground">No active freight found.</div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {freight.data.map((f) => (
                            <div key={f.ulid} className="rounded-md border p-4">
                                <div className="font-medium">{f.originLocation?.name ?? '—'} → {f.destinationLocation?.name ?? '—'}</div>
                                <p className="text-sm text-muted-foreground">
                                    {f.cargoType?.name ?? f.cargo_description ?? '—'} • {f.truckType?.name ?? '—'} • {f.pickup_date ? String(f.pickup_date).slice(0, 10) : '—'}
                                </p>
                                {f.routeSnapshot && <p className="text-xs text-muted-foreground">{(f.routeSnapshot.distance_meters / 1000).toFixed(1)} km • {Math.round(f.routeSnapshot.duration_seconds / 60)} min</p>}
                                <Link href={`/my-freight/${f.ulid}`} className="mt-2 inline-block text-sm text-primary hover:underline">View</Link>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
