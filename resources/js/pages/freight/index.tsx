import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Package, MapPin, Calendar, Truck, ArrowRight, Search, Plus } from 'lucide-react';
import { useState } from 'react';

interface Freight {
    ulid: string;
    status: string;
    originLocation?: { name: string; region_name: string } | null;
    destinationLocation?: { name: string; region_name: string } | null;
    origin_location?: { name: string; region_name: string } | null;
    destination_location?: { name: string; region_name: string } | null;
    cargo_description: string | null;
    cargoType?: { name: string } | null;
    cargo_type?: { name: string } | null;
    truckType?: { name: string } | null;
    truck_type?: { name: string } | null;
    pickup_date: string | null;
    completion_score: number;
    created_at?: string | null;
}

interface Props {
    posts: { data: Freight[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number; from: number | null; to: number | null };
    filters: { status: string; search?: string | null };
    counts: Record<string, number>;
}

const STATUSES = [
    { value: 'all', label: 'All' },
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'under_review', label: 'Under Review' },
    { value: 'active', label: 'Active' },
    { value: 'closed', label: 'Closed' },
    { value: 'expired', label: 'Expired' },
    { value: 'rejected', label: 'Rejected' },
];

const statusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default' as const;
        case 'submitted':
        case 'under_review':
            return 'secondary' as const;
        case 'rejected':
        case 'expired':
            return 'destructive' as const;
        default:
            return 'outline' as const;
    }
};

export default function FreightIndex({ posts, filters, counts }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const createDraft = () => {
        router.post('/my-freight');
    };

    const filterBy = (status: string) => {
        router.get('/my-freight', { status, search: search || undefined }, { preserveState: true });
    };

    const doSearch = () => {
        router.get('/my-freight', { status: filters.status, search: search || undefined }, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title="My Freight" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight">My Freight</h1>
                    <p className="text-muted-foreground">Manage your freight posts — draft, submitted, and active loads.</p>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {STATUSES.map((s) => (
                            <Button key={s.value} variant={filters.status === s.value ? 'default' : 'outline'} size="sm" onClick={() => filterBy(s.value)} className="h-7 px-2.5 text-xs">
                                {s.label} <span className="ml-1 rounded bg-background/20 px-1.5 py-0.5 text-[10px]">{counts[s.value] ?? 0}</span>
                            </Button>
                        ))}
                    </div>
                    <Button onClick={createDraft} size="sm"><Plus className="mr-1 size-4" /> Post Freight</Button>
                </div>

                <div className="flex gap-2">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input placeholder="Search cargo, towns, ULID…" value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doSearch()} className="pl-8 h-9" />
                    </div>
                    <Button variant="outline" size="sm" onClick={doSearch} className="h-9">Search</Button>
                    {filters.search && <Button variant="ghost" size="sm" onClick={() => { setSearch(''); router.get('/my-freight', { status: filters.status }, { preserveState: true }); }}>Clear</Button>}
                </div>

                <div className="text-sm text-muted-foreground">
                    {posts.total ? `${posts.total} posts` : `${posts.data.length} posts`} {posts.from && posts.to ? `• ${posts.from}–${posts.to}` : ''} • {filters.status !== 'all' ? `Filtered by ${filters.status}` : 'All statuses'}
                </div>

                {posts.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Package className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold">No freight in this filter</h3>
                            <p className="max-w-md text-sm text-muted-foreground">You have no {filters.status !== 'all' ? filters.status : ''} posts yet. Create a draft and submit it for admin approval.</p>
                            <Button onClick={createDraft} className="mt-2"><Plus className="mr-2 size-4" /> Post Freight</Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {posts.data.map((p) => {
                            const origin = (p as unknown as Record<string, unknown>).originLocation as Freight['originLocation'] ?? (p as unknown as Record<string, unknown>).origin_location as Freight['originLocation'];
                            const destination = (p as unknown as Record<string, unknown>).destinationLocation as Freight['destinationLocation'] ?? (p as unknown as Record<string, unknown>).destination_location as Freight['destinationLocation'];
                            const cargoType = (p as unknown as Record<string, unknown>).cargoType as Freight['cargoType'] ?? (p as unknown as Record<string, unknown>).cargo_type as Freight['cargoType'];
                            const truckType = (p as unknown as Record<string, unknown>).truckType as Freight['truckType'] ?? (p as unknown as Record<string, unknown>).truck_type as Freight['truckType'];
                            const href = p.status === 'draft' ? `/my-freight/${p.ulid}/route` : p.status === 'active' || p.status === 'submitted' || p.status === 'under_review' ? `/freight/${p.ulid}` : `/my-freight/${p.ulid}/route`;
                            return (
                                <Card key={p.ulid} className="overflow-hidden hover:shadow-md transition-shadow">
                                    <CardContent className="p-4 space-y-3">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2 font-medium">
                                                <MapPin className="size-4 text-primary" />
                                                <span className="truncate">{origin?.name ?? '—'}</span>
                                                <ArrowRight className="size-3 text-muted-foreground" />
                                                <span className="truncate">{destination?.name ?? '—'}</span>
                                            </div>
                                            <Badge variant={statusVariant(p.status)} className="capitalize text-xs">{p.status.replace('_', ' ')}</Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">{origin?.region_name ?? ''} → {destination?.region_name ?? ''}</p>
                                        <div className="flex flex-wrap gap-2 text-xs">
                                            <Badge variant="secondary" className="font-normal"><Package className="mr-1 size-3" />{cargoType?.name ?? 'General'} — {p.cargo_description ? `${p.cargo_description.slice(0, 30)}${p.cargo_description.length > 30 ? '…' : ''}` : 'No desc'}</Badge>
                                            <Badge variant="secondary" className="font-normal"><Truck className="mr-1 size-3" />{truckType?.name ?? 'Any'} {p.completion_score ? `• ${p.completion_score}%` : ''}</Badge>
                                            <Badge variant="secondary" className="font-normal"><Calendar className="mr-1 size-3" />{p.pickup_date ? String(p.pickup_date).slice(0, 10) : '—'}</Badge>
                                        </div>
                                        <div className="space-y-1">
                                            <div className="flex justify-between text-xs">
                                                <span className="text-muted-foreground">Completion</span>
                                                <span className="font-medium">{p.completion_score}%</span>
                                            </div>
                                            <Progress value={p.completion_score} className="h-1.5" />
                                        </div>
                                        <div className="flex gap-2 pt-2">
                                            <Button asChild size="sm" className="flex-1 h-7 text-xs"><Link href={href}>{p.status === 'draft' ? 'Continue Editing' : 'View Details'}</Link></Button>
                                            {p.status === 'draft' && <Button variant="outline" size="sm" asChild className="h-7 text-xs"><Link href={`/my-freight/${p.ulid}/review`}>Review</Link></Button>}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}

                {posts.links && posts.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {posts.links.map((link, i) => (
                            <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} className="h-7 px-2.5 text-xs">
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
