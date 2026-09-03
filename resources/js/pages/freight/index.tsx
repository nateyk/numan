import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Package, MapPin, Calendar, Truck, ArrowRight, Search, Plus, MoreHorizontal, Eye, Pencil, Users, Trash2, ExternalLink } from 'lucide-react';
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

const statusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-900/20';
        case 'submitted':
        case 'under_review':
            return 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/20';
        case 'rejected':
        case 'expired':
            return 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-900/20';
        case 'closed':
            return 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-900/20';
        default:
            return 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-900/20';
    }
};

export default function FreightIndex({ posts, filters, counts }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const createDraft = () => router.post('/my-freight');
    const filterBy = (status: string) => router.get('/my-freight', { status, search: search || undefined }, { preserveState: true });
    const doSearch = () => router.get('/my-freight', { status: filters.status, search: search || undefined }, { preserveState: true });

    return (
        <AppLayout>
            <Head title="My Freight — Manage Posts" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight">My Freight</h1>
                    <p className="text-muted-foreground">Manage your freight posts — track by status, search, and act via dropdown.</p>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative flex-1 max-w-sm">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input placeholder="Search cargo, towns, ULID…" value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doSearch()} className="pl-9" />
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={filters.status} onValueChange={(v) => filterBy(v)}>
                            <SelectTrigger className="w-40"><SelectValue placeholder="Status" /></SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>{s.label} ({counts[s.value] ?? 0})</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button onClick={createDraft}><Plus className="mr-1 size-4" /> Post Freight</Button>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    {STATUSES.map((s) => (
                        <Button key={s.value} variant={filters.status === s.value ? 'default' : 'outline'} size="sm" onClick={() => filterBy(s.value)} className="h-7 px-2.5 text-xs">
                            {s.label} <Badge variant="secondary" className="ml-1 px-1.5 py-0 text-[10px]">{counts[s.value] ?? 0}</Badge>
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Route</TableHead>
                                    <TableHead>Cargo</TableHead>
                                    <TableHead>Truck</TableHead>
                                    <TableHead>Pickup</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {posts.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center">
                                            <div className="flex flex-col items-center gap-2 py-6">
                                                <Package className="size-8 text-muted-foreground" />
                                                <p className="text-sm font-medium">No freight in this filter</p>
                                                <p className="text-xs text-muted-foreground">You have no {filters.status !== 'all' ? filters.status : ''} posts yet.</p>
                                                <Button onClick={createDraft} size="sm" className="mt-2"><Plus className="mr-1 size-4" /> Post Freight</Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    posts.data.map((p) => {
                                        const origin = (p as unknown as Record<string, unknown>).originLocation as Freight['originLocation'] ?? (p as unknown as Record<string, unknown>).origin_location as Freight['originLocation'];
                                        const destination = (p as unknown as Record<string, unknown>).destinationLocation as Freight['destinationLocation'] ?? (p as unknown as Record<string, unknown>).destination_location as Freight['destinationLocation'];
                                        const cargoType = (p as unknown as Record<string, unknown>).cargoType as Freight['cargoType'] ?? (p as unknown as Record<string, unknown>).cargo_type as Freight['cargoType'];
                                        const truckType = (p as unknown as Record<string, unknown>).truckType as Freight['truckType'] ?? (p as unknown as Record<string, unknown>).truck_type as Freight['truckType'];
                                        const isDraft = p.status === 'draft';
                                        return (
                                            <TableRow key={p.ulid}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2 font-medium">
                                                        <MapPin className="size-4 text-primary" />
                                                        <span>{origin?.name ?? '—'}</span>
                                                        <ArrowRight className="size-3 text-muted-foreground" />
                                                        <span>{destination?.name ?? '—'}</span>
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">{origin?.region_name ?? ''} → {destination?.region_name ?? ''} • {p.ulid.slice(0, 8)}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Package className="size-4 text-muted-foreground" />
                                                        <span className="font-medium">{cargoType?.name ?? 'General'}</span>
                                                    </div>
                                                    <div className="text-xs text-muted-foreground truncate max-w-[160px]">{p.cargo_description ?? '—'}</div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1 text-sm">
                                                        <Truck className="size-4 text-muted-foreground" /> {truckType?.name ?? 'Any'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1 text-sm">
                                                        <Calendar className="size-4 text-muted-foreground" /> {p.pickup_date ? String(p.pickup_date).slice(0, 10) : '—'}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary" className={`${statusColor(p.status)} capitalize`}>{p.status.replace('_', ' ')}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon" className="size-7" asChild>
                                                            <Link href={isDraft ? `/my-freight/${p.ulid}/route` : `/freight/${p.ulid}`}><Eye className="size-4" /></Link>
                                                        </Button>
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="icon" className="size-7"><MoreHorizontal className="size-4" /></Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end" className="w-48">
                                                                <DropdownMenuItem asChild><Link href={isDraft ? `/my-freight/${p.ulid}/route` : `/freight/${p.ulid}`} className="cursor-pointer"><Eye className="mr-2 size-4" /> View Details</Link></DropdownMenuItem>
                                                                {isDraft && <DropdownMenuItem asChild><Link href={`/my-freight/${p.ulid}/route`} className="cursor-pointer"><Pencil className="mr-2 size-4" /> Edit Route</Link></DropdownMenuItem>}
                                                                {isDraft && <DropdownMenuItem asChild><Link href={`/my-freight/${p.ulid}/cargo`} className="cursor-pointer"><Package className="mr-2 size-4" /> Edit Cargo</Link></DropdownMenuItem>}
                                                                {isDraft && <DropdownMenuItem asChild><Link href={`/my-freight/${p.ulid}/review`} className="cursor-pointer"><ExternalLink className="mr-2 size-4" /> Review & Submit</Link></DropdownMenuItem>}
                                                                {!isDraft && <DropdownMenuItem asChild><Link href={`/my-freight/${p.ulid}/route`} className="cursor-pointer"><Users className="mr-2 size-4" /> Interests</Link></DropdownMenuItem>}
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem className="cursor-pointer text-destructive"><Trash2 className="mr-2 size-4" /> Delete (draft)</DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="text-sm text-muted-foreground">
                    {posts.total ? `${posts.total} posts` : `${posts.data.length} posts`} {posts.from && posts.to ? `• ${posts.from}–${posts.to}` : ''} • {filters.status !== 'all' ? `Filtered by ${filters.status}` : 'All statuses'}
                </div>

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
