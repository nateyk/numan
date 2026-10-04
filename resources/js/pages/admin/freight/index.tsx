import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTable } from '@/components/freight/freight-data-table';
import { freightColumns, type FreightPost } from '@/components/freight/freight-columns';
import { useState } from 'react';

interface Props {
    posts: { data: FreightPost[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number };
    filters: { status: string; search?: string | null };
    counts: Record<string, number>;
}

const STATUSES = ['all', 'draft', 'submitted', 'under_review', 'active', 'closed', 'expired', 'rejected'];

export default function AdminFreightIndex({ posts, filters, counts }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const filterBy = (status: string) => {
        router.get('/admin/freight', { status: status === 'all' ? 'all' : status, search: search || undefined }, { preserveState: true });
    };
    const doSearch = () => {
        router.get('/admin/freight', { status: filters.status, search: search || undefined }, { preserveState: true });
    };

    return (
        <AdminLayout>
            <Head title="Freight Moderation" />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-bold">Freight Moderation</h1>
                    <div className="flex gap-2">
                        <Input placeholder="Search cargo, user, town…" value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doSearch()} className="w-64" />
                        <Button variant="outline" size="sm" onClick={doSearch}>Search</Button>
                    </div>
                </div>
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
                    <DataTable columns={freightColumns} data={posts.data} />
                )}
            </div>
        </AdminLayout>
    );
}
