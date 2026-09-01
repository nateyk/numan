import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Freight {
    ulid: string;
    status: string;
    cargo_description: string | null;
    user?: { name: string; email: string };
    originLocation?: { name: string };
    destinationLocation?: { name: string };
}

interface Props {
    posts: { data: Freight[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    filters: { status: string };
    counts: Record<string, number>;
}

const STATUSES = ['all', 'draft', 'submitted', 'under_review', 'active', 'rejected'];

export default function AdminFreightIndex({ posts, filters, counts }: Props) {
    const filterBy = (status: string) => {
        router.get('/admin/freight', status === 'all' ? {} : { status }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Freight Moderation" />
            <div className="space-y-6 p-6">
                <h1 className="text-2xl font-bold">Freight Moderation</h1>
                <div className="flex flex-wrap gap-2">
                    {STATUSES.map((s) => (
                        <Button key={s} variant={filters.status === s ? 'default' : 'outline'} size="sm" onClick={() => filterBy(s)}>
                            {s} ({counts[s] ?? 0})
                        </Button>
                    ))}
                </div>
                {posts.data.length === 0 ? (
                    <div className="rounded-md border p-12 text-center text-muted-foreground">No freight in this queue.</div>
                ) : (
                    <div className="space-y-2">
                        {posts.data.map((p) => (
                            <Link key={p.ulid} href={`/admin/freight/${p.ulid}`} className="flex items-center justify-between rounded-md border p-3 hover:bg-muted/50">
                                <div>
                                    <div className="font-medium">{p.originLocation?.name ?? '—'} → {p.destinationLocation?.name ?? '—'}</div>
                                    <div className="text-sm text-muted-foreground">{p.user?.name ?? '—'} • {p.cargo_description ?? '—'}</div>
                                </div>
                                <Badge>{p.status}</Badge>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
