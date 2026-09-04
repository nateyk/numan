import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Package, MapPin, Calendar, Truck, ArrowRight, MoreHorizontal, Eye, X } from 'lucide-react';

interface Interest {
    id: number;
    status: string;
    message: string | null;
    created_at?: string | null;
    freightPost?: { ulid: string; status: string; originLocation?: { name: string; region_name: string }; destinationLocation?: { name: string; region_name: string }; cargoType?: { name: string }; truckType?: { name: string }; user?: { name: string } };
    freight_post?: { ulid: string; status: string; origin_location?: { name: string; region_name: string }; destination_location?: { name: string; region_name: string }; cargo_type?: { name: string }; truck_type?: { name: string }; user?: { name: string } };
}

interface Props {
    interests: { data: Interest[]; links?: Array<{ url: string | null; label: string; active: boolean }>; total?: number };
    sort: string;
    counts: Record<string, number>;
}

const statusColor = (status: string) => {
    switch (status) {
        case 'selected':
            return 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-900/20';
        case 'shortlisted':
            return 'text-amber-600 bg-amber-50 dark:text-amber-400 dark:bg-amber-900/20';
        case 'rejected':
        case 'withdrawn':
            return 'text-red-600 bg-red-50 dark:text-red-400 dark:bg-red-900/20';
        default:
            return 'text-gray-600 bg-gray-50 dark:text-gray-400 dark:bg-gray-900/20';
    }
};

export default function MyInterests({ interests, sort, counts }: Props) {
    return (
        <AppLayout>
            <Head title="My Interests — Manage" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight">My Interests</h1>
                    <p className="text-muted-foreground">Track your expressed interests — submitted, shortlisted, and selected freight.</p>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary" className="px-2.5 py-1">All: {counts.all ?? interests.data.length}</Badge>
                        <Badge variant="outline" className={statusColor('submitted')}>Submitted: {counts.submitted ?? 0}</Badge>
                        <Badge variant="outline" className={statusColor('shortlisted')}>Shortlisted: {counts.shortlisted ?? 0}</Badge>
                        <Badge variant="outline" className={statusColor('selected')}>Selected: {counts.selected ?? 0}</Badge>
                    </div>
                    <Select value={sort} onValueChange={(v) => router.get('/my-interests', { sort: v }, { preserveState: true })}>
                        <SelectTrigger className="w-40"><SelectValue placeholder="Sort" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="latest">Latest</SelectItem>
                            <SelectItem value="oldest">Oldest</SelectItem>
                            <SelectItem value="status">Status</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {interests.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Package className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold">No interests yet</h3>
                            <p className="max-w-md text-sm text-muted-foreground">Browse freight at <Link href="/freight" className="text-primary hover:underline">Discover Freight</Link> and express interest. Your contact will be shared only when shortlisted.</p>
                            <Button asChild>
                                <Link href="/freight">Discover Freight</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Card className="hidden md:block">
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Freight</TableHead>
                                            <TableHead>Route</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {interests.data.map((i) => {
                                            const fp = (i as unknown as Record<string, unknown>).freightPost as Interest['freightPost'] ?? (i as unknown as Record<string, unknown>).freight_post as unknown as Interest['freightPost'];
                                            const origin = (fp as unknown as Record<string, unknown>)?.originLocation as { name: string; region_name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.origin_location as { name: string; region_name: string } | undefined;
                                            const destination = (fp as unknown as Record<string, unknown>)?.destinationLocation as { name: string; region_name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.destination_location as { name: string; region_name: string } | undefined;
                                            const cargoType = (fp as unknown as Record<string, unknown>)?.cargoType as { name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.cargo_type as { name: string } | undefined;
                                            return (
                                                <TableRow key={i.id}>
                                                    <TableCell>
                                                        <div className="font-medium flex items-center gap-2">
                                                            <MapPin className="size-4 text-primary" /> {origin?.name ?? '—'} <ArrowRight className="size-3 text-muted-foreground" /> {destination?.name ?? '—'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">{fp?.ulid?.slice(0, 8) ?? '—'} • {cargoType?.name ?? 'General'}</div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="text-sm">{i.message ?? '—'}</div>
                                                        <div className="text-xs text-muted-foreground">{i.created_at ? new Date(i.created_at).toLocaleDateString() : ''}</div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="secondary" className={`${statusColor(i.status)} capitalize`}>{i.status}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button variant="ghost" size="icon" className="size-7" asChild>
                                                                <Link href={`/freight/${fp?.ulid ?? ''}`}><Eye className="size-4" /></Link>
                                                            </Button>
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button variant="ghost" size="icon" className="size-7"><MoreHorizontal className="size-4" /></Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end" className="w-40">
                                                                    <DropdownMenuItem asChild><Link href={`/freight/${fp?.ulid ?? ''}`} className="cursor-pointer"><Eye className="mr-2 size-4" /> View Freight</Link></DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => router.post(`/interests/${i.id}/withdraw`)} disabled={i.status === 'withdrawn' || i.status === 'selected'} className="cursor-pointer"><X className="mr-2 size-4" /> Withdraw</DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        <div className="grid gap-3 md:hidden">
                            {interests.data.map((i) => {
                                const fp = (i as unknown as Record<string, unknown>).freightPost as Interest['freightPost'] ?? (i as unknown as Record<string, unknown>).freight_post as unknown as Interest['freightPost'];
                                const origin = (fp as unknown as Record<string, unknown>)?.originLocation as { name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.origin_location as { name: string } | undefined;
                                const destination = (fp as unknown as Record<string, unknown>)?.destinationLocation as { name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.destination_location as { name: string } | undefined;
                                return (
                                    <Card key={i.id} className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="font-medium flex items-center gap-2">
                                                <MapPin className="size-4 text-primary" /> {origin?.name ?? '—'} <ArrowRight className="size-3 text-muted-foreground" /> {destination?.name ?? '—'}
                                            </div>
                                            <Badge variant="secondary" className={`${statusColor(i.status)} capitalize text-xs`}>{i.status}</Badge>
                                        </div>
                                        <div className="mt-2 text-sm text-muted-foreground truncate">{i.message ?? '—'}</div>
                                        <div className="mt-3 flex gap-2">
                                            <Button variant="outline" size="sm" className="flex-1 h-8" asChild><Link href={`/freight/${fp?.ulid ?? ''}`}>View</Link></Button>
                                            <Button size="sm" variant="outline" className="h-8" onClick={() => router.post(`/interests/${i.id}/withdraw`)} disabled={i.status === 'withdrawn'}>Withdraw</Button>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>

                        {interests.links && (interests as unknown as { links: Array<{ url: string | null; label: string; active: boolean }> }).links.length > 3 && (
                            <div className="flex flex-wrap gap-1">
                                {(interests as unknown as { links: Array<{ url: string | null; label: string; active: boolean }> }).links.map((link, idx) => (
                                    <Button key={idx} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} className="h-7 px-2.5 text-xs">
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Button>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
