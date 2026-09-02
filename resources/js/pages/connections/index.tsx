import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';

interface Connection {
    public_id: string;
    freightPost: { ulid: string; originLocation?: { name: string }; destinationLocation?: { name: string } };
    outcome?: { outcome: string } | null;
}

interface Props {
    connections: { data: Connection[] };
}

export default function ConnectionsIndex({ connections }: Props) {
    return (
        <AppLayout>
            <Head title="Connections" />
            <div className="mx-auto max-w-4xl space-y-4 p-6">
                <h1 className="text-xl font-bold">Connections</h1>
                {connections.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No connections yet.</p>
                ) : (
                    <div className="space-y-2">
                        {connections.data.map((c) => (
                            <Link key={c.public_id} href={`/connections/${c.public_id}`} className="block rounded-md border p-3 hover:bg-muted/50">
                                <div className="font-medium">{c.freightPost.originLocation?.name ?? '—'} → {c.freightPost.destinationLocation?.name ?? '—'}</div>
                                <div className="text-sm text-muted-foreground">{c.freightPost.ulid} • {c.outcome?.outcome ?? 'pending'}</div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
