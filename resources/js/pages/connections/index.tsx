import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import {
    freightRoute,
    postUlid,
    type FreightRow,
} from '@/lib/freight-route';

interface Connection {
    public_id: string;
    /**
     * ConnectionResource nests the post under freight_post and sends origin and
     * destination as bare names, not location objects. Read through
     * freightRoute() rather than reaching for originLocation, which this
     * payload never had.
     */
    freight_post?: FreightRow;
    freightPost?: FreightRow;
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
                        {connections.data.map((c) => {
                            const post = c.freight_post ?? c.freightPost;
                            const { origin, destination } =
                                freightRoute(post);
                            const ulid = postUlid(post);

                            return (
                                <Link
                                    key={c.public_id}
                                    href={`/connections/${c.public_id}`}
                                    className="block rounded-md border p-3 hover:bg-muted/50"
                                >
                                    <div className="font-medium">
                                        {origin?.name ?? '—'} →{' '}
                                        {destination?.name ?? '—'}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {ulid ?? '—'} •{' '}
                                        {c.outcome?.outcome ?? 'pending'}
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
