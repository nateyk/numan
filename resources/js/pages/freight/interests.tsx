import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Package, MapPin, Calendar, Phone, Mail, User, MoreHorizontal, Eye, Check, X, Star, ArrowLeft } from 'lucide-react';

interface RevealedContact {
    name: string;
    phone: string | null;
    email: string | null;
    whatsapp: string | null;
}

interface Interest {
    id: number;
    status: string;
    message: string | null;
    created_at?: string | null;
    // Display identity only. Contact details are never part of the payload.
    transporter?: {
        id: number;
        display_name: string;
        is_verified: boolean;
        routes: Array<{ id: number; origin: string | null; destination: string | null }>;
    } | null;
}

interface FreightPost {
    ulid: string;
    status: string;
    originLocation?: { name: string; region_name: string } | null;
    origin_location?: { name: string; region_name: string } | null;
    destinationLocation?: { name: string; region_name: string } | null;
    destination_location?: { name: string; region_name: string } | null;
    cargo_description: string | null;
}

interface Props {
    interests: Interest[];
    total?: number;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    canReveal?: Record<string, boolean>;
    revealedContacts?: Record<string, RevealedContact>;
    post: FreightPost;
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

export default function FreightInterests({ interests, links, canReveal, revealedContacts, post }: Props) {
    const origin = (post as unknown as Record<string, unknown>).originLocation as FreightPost['originLocation'] ?? (post as unknown as Record<string, unknown>).origin_location as FreightPost['originLocation'];
    const destination = (post as unknown as Record<string, unknown>).destinationLocation as FreightPost['destinationLocation'] ?? (post as unknown as Record<string, unknown>).destination_location as FreightPost['destinationLocation'];

    const counts = {
        all: interests.length,
        submitted: interests.filter((i) => i.status === 'submitted').length,
        shortlisted: interests.filter((i) => i.status === 'shortlisted').length,
        selected: interests.filter((i) => i.status === 'selected').length,
    };

    return (
        <AppLayout>
            <Head title={`Interests — ${origin?.name ?? ''} → ${destination?.name ?? ''}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/my-freight"><ArrowLeft className="size-4" /></Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Interests for {origin?.name ?? '—'} → {destination?.name ?? '—'}</h1>
                        <p className="text-sm text-muted-foreground">ULID {post.ulid.slice(0, 8)} • {post.item_name ?? 'General cargo'} • {counts.all} total</p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Badge variant="secondary" className="px-2.5 py-1">All: {counts.all}</Badge>
                    <Badge variant="outline" className={statusColor('submitted')}>Submitted: {counts.submitted}</Badge>
                    <Badge variant="outline" className={statusColor('shortlisted')}>Shortlisted: {counts.shortlisted}</Badge>
                    <Badge variant="outline" className={statusColor('selected')}>Selected: {counts.selected}</Badge>
                </div>

                {interests.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Users className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold">No interests yet</h3>
                            <p className="max-w-md text-sm text-muted-foreground">When transporters express interest on this freight, they will appear here with contact details for you to shortlist or accept.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Desktop Table */}
                        <Card className="hidden md:block">
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Transporter</TableHead>
                                            <TableHead>Message</TableHead>
                                            <TableHead>Contact</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {interests.map((i) => (
                                            <TableRow key={i.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="size-8">
                                                            <AvatarFallback>{(i.transporter?.display_name ?? '?').slice(0, 2).toUpperCase()}</AvatarFallback>
                                                        </Avatar>
                                                        <div>
                                                            <div className="font-medium">{i.transporter?.display_name ?? '—'}</div>
                                                            {i.transporter?.is_verified && (
                                                                <div className="text-xs text-muted-foreground">Verified</div>
                                                            )}
                                                            {i.transporter?.routes?.map((r) => (
                                                                <div key={r.id} className="text-xs text-muted-foreground">
                                                                    {r.origin ?? '—'} → {r.destination ?? '—'}
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="max-w-[200px]">
                                                    <div className="truncate text-sm">{i.message ?? '—'}</div>
                                                    {i.created_at && <div className="text-xs text-muted-foreground">{new Date(i.created_at).toLocaleDateString()}</div>}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        <div className="flex items-center gap-1">
                                                            <User className="size-3" /> {i.transporter?.display_name ?? '—'}
                                                        </div>
                                                        {revealedContacts?.[String(i.id)] ? (
                                                            <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                                                <div className="flex items-center gap-1">
                                                                    <Phone className="size-3" /> {revealedContacts[String(i.id)]?.phone ?? '—'}
                                                                </div>
                                                                <div className="flex items-center gap-1">
                                                                    <Mail className="size-3" /> {revealedContacts[String(i.id)]?.email ?? '—'}
                                                                </div>
                                                            </div>
                                                        ) : canReveal?.[String(i.id)] ? (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="mt-1 h-7 text-xs"
                                                                onClick={() => router.post(`/interests/${i.id}/reveal`)}
                                                            >
                                                                Reveal contact
                                                            </Button>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">Not available</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary" className={`${statusColor(i.status)} capitalize`}>{i.status}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon" className="size-7"><MoreHorizontal className="size-4" /></Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end" className="w-48">
                                                            <DropdownMenuItem onClick={() => router.post(`/interests/${i.id}/shortlist`)} disabled={i.status !== 'submitted'} className="cursor-pointer"><Star className="mr-2 size-4" /> Shortlist</DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => router.post(`/interests/${i.id}/accept`)} disabled={i.status === 'selected' || i.status === 'rejected'} className="cursor-pointer"><Check className="mr-2 size-4" /> Accept & Connect</DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => router.post(`/interests/${i.id}/reject`)} disabled={i.status === 'rejected' || i.status === 'selected'} className="cursor-pointer"><X className="mr-2 size-4" /> Reject</DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem asChild><Link href={`/freight/${post.ulid}`} className="cursor-pointer"><Eye className="mr-2 size-4" /> View Freight</Link></DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {/* Mobile Cards */}
                        <div className="grid gap-3 md:hidden">
                            {interests.map((i) => (
                                <Card key={i.id} className="p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-3">
                                            <Avatar className="size-8">
                                                <AvatarFallback>{(i.transporter?.display_name ?? '?').slice(0, 2).toUpperCase()}</AvatarFallback>
                                            </Avatar>
                                            <div>
                                                <div className="font-medium">{i.transporter?.display_name ?? '—'}</div>
                                                <div className="text-xs text-muted-foreground">{i.message ?? '—'}</div>
                                            </div>
                                        </div>
                                        <Badge variant="secondary" className={`${statusColor(i.status)} capitalize text-xs`}>{i.status}</Badge>
                                    </div>
                                    {revealedContacts?.[String(i.id)] && (
                                        <div className="mt-3 rounded-md border bg-muted/20 p-2 text-sm">
                                            <div className="font-medium">{revealedContacts[String(i.id)]?.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {revealedContacts[String(i.id)]?.phone ?? '—'} • {revealedContacts[String(i.id)]?.email ?? '—'}
                                            </div>
                                        </div>
                                    )}
                                    <div className="mt-3 flex gap-2">
                                        <Button size="sm" variant="outline" className="flex-1 h-8" onClick={() => router.post(`/interests/${i.id}/shortlist`)} disabled={i.status !== 'submitted'}>Shortlist</Button>
                                        <Button size="sm" className="flex-1 h-8" onClick={() => router.post(`/interests/${i.id}/accept`)} disabled={i.status === 'selected'}>Accept</Button>
                                        <Button size="sm" variant="destructive" className="h-8" onClick={() => router.post(`/interests/${i.id}/reject`)} disabled={i.status === 'rejected'}>Reject</Button>
                                    </div>
                                </Card>
                            ))}
                        </div>

                        {links && links.length > 3 && (
                            <div className="flex flex-wrap gap-1">
                                {links.map((link, idx) => (
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

function Users(props: React.ComponentProps<'svg'>) {
    return (
        <svg {...props} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
    );
}
