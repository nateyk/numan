import { Head, Link, router, usePage } from '@inertiajs/react';
import { LandingFooter, LandingHeader } from '@/components/landing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Truck, Package, Calendar, MapPin, Route, ArrowRight, Search, SlidersHorizontal, ChevronDown, X, Weight, Banknote } from 'lucide-react';
import { useState } from 'react';
import LocationSearchInput from '@/components/maps/location-search-input';
import type { SharedData } from '@/types';

interface Freight {
    id: number;
    ulid: string;
    status: string;
    cargo_description: string | null;
    item_name?: string | null;
    actual_weight?: string | null;
    weight_unit?: string | null;
    tariff_amount?: string | null;
    tariff_currency?: string | null;
    pickup_date: string | null;
    pickup_time?: string | null;
    truck_count?: number | null;
    originLocation?: { id: number; name: string; region_name: string };
    destinationLocation?: { id: number; name: string; region_name: string };
    origin_location?: { id: number; name: string; region_name: string };
    destination_location?: { id: number; name: string; region_name: string };
    cargoType?: { name: string };
    truckType?: { name: string };
    cargo_type?: { name: string };
    truck_type?: { name: string };
    routeSnapshot?: { distance_meters: number; duration_seconds: number } | null;
    route_snapshot?: { distance_meters: number; duration_seconds: number } | null;
    user?: { id: number; name: string; email: string; avatar_url?: string | null };
}

interface Props {
    freight: { data: Freight[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
    canRegister?: boolean;
    userInterestMap?: Record<number, { id: number; status: string }>;
}

const sortOptions = [
    { id: 'latest', label: 'Latest' },
    { id: 'distance_asc', label: 'Distance: Near to Far' },
    { id: 'distance_desc', label: 'Distance: Far to Near' },
    { id: 'pickup_asc', label: 'Pickup: Earliest' },
];

export default function DiscoverIndex({ freight, filters, canRegister = true, userInterestMap = {} }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [searchQuery, setSearchQuery] = useState('');
    const [origin, setOrigin] = useState(filters.origin ?? '');
    const [destination, setDestination] = useState(filters.destination ?? '');
    const [selectedCargo, setSelectedCargo] = useState('all');
    const [selectedSort, setSelectedSort] = useState('latest');

    const activeFilters: Array<{ type: string; label: string }> = [];
    if (origin) activeFilters.push({ type: 'origin', label: `Origin #${origin}` });
    if (destination) activeFilters.push({ type: 'destination', label: `Destination #${destination}` });
    if (selectedCargo !== 'all') activeFilters.push({ type: 'cargo', label: selectedCargo });
    if (searchQuery) activeFilters.push({ type: 'search', label: `"${searchQuery}"` });

    const clearFilter = (type: string) => {
        if (type === 'origin') { setOrigin(''); router.get('/freight', { destination: destination || undefined, search: searchQuery || undefined }, { preserveState: true }); }
        if (type === 'destination') { setDestination(''); router.get('/freight', { origin: origin || undefined, search: searchQuery || undefined }, { preserveState: true }); }
        if (type === 'cargo') setSelectedCargo('all');
        if (type === 'search') setSearchQuery('');
    };
    const clearAll = () => {
        setOrigin(''); setDestination(''); setSelectedCargo('all'); setSearchQuery('');
        router.get('/freight', {}, { preserveState: true });
    };

    const doSearch = () => {
        router.get('/freight', { origin: origin || undefined, destination: destination || undefined, search: searchQuery || undefined }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Discover Freight — NumanFreight Ethiopian Marketplace" />
            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main>
                    {/* Hero + Filter — CategoryFilter1 style */}
                    <section className="py-8">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-8">
                                <h1 className="text-3xl font-bold tracking-tight text-balance">Discover Freight Across Ethiopia</h1>
                                <p className="text-muted-foreground mt-2 max-w-2xl">
                                    Browse {freight.total ?? freight.data.length} active freight posts — 934 major towns from Addis Ababa to Jijiga, Hawassa to Mekelle. Find verified loads for your fleet.
                                </p>
                            </div>

                            <div className="mb-6 flex flex-col gap-4">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="relative max-w-md flex-1">
                                        <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                        <Input placeholder="Search cargo, description..." value={searchQuery} onChange={(e) => setSearchQuery(e.target.value)} className="pl-10 h-9" onKeyDown={(e) => e.key === 'Enter' && doSearch()} />
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger className="h-9 px-4 py-2 w-full cursor-pointer sm:w-auto inline-flex items-center justify-center gap-2 rounded-md border bg-background text-sm font-medium">
                                            <SlidersHorizontal className="size-4" />
                                            Sort: {sortOptions.find(s => s.id === selectedSort)?.label}
                                            <ChevronDown className="size-4" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="w-56">
                                            {sortOptions.map((opt) => (
                                                <DropdownMenuItem key={opt.id} onClick={() => setSelectedSort(opt.id)} className={selectedSort === opt.id ? 'bg-accent' : ''}>
                                                    {opt.label}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <div className="flex flex-wrap gap-3">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <span className="text-xs font-medium text-muted-foreground">Origin:</span>
                                        <LocationSearchInput onSelectLocation={(loc) => setOrigin(loc ? String(loc.id) : '')} placeholder="Addis Ababa…" className="w-48" />
                                    </div>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <span className="text-xs font-medium text-muted-foreground">Destination:</span>
                                        <LocationSearchInput onSelectLocation={(loc) => setDestination(loc ? String(loc.id) : '')} placeholder="Hawassa…" className="w-48" />
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger className="h-8 px-3 text-xs cursor-pointer inline-flex items-center gap-2 rounded-md border bg-background font-medium">
                                            Cargo: {selectedCargo === 'all' ? 'All' : selectedCargo}
                                            <ChevronDown className="size-3" />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent className="w-56">
                                            {['all', 'General', 'Cereals', 'Construction', 'Refrigerated'].map((c) => (
                                                <DropdownMenuItem key={c} onClick={() => setSelectedCargo(c)} className={selectedCargo === c ? 'bg-accent' : ''}>
                                                    {c === 'all' ? 'All Cargo' : c}
                                                </DropdownMenuItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                    <Button onClick={doSearch} size="sm" className="h-8 px-3 text-xs"><Search className="mr-1 size-3" /> Apply</Button>
                                    <Button variant="ghost" size="sm" onClick={clearAll} className="h-8 px-3 text-xs">Clear all</Button>
                                </div>

                                {activeFilters.length > 0 && (
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-muted-foreground text-sm font-medium">Active filters:</span>
                                        {activeFilters.map((f) => (
                                            <Badge key={f.type} variant="secondary" className="px-2.5 py-0.5 font-semibold">
                                                {f.label}
                                                <Button variant="ghost" size="sm" className="h-auto cursor-pointer !p-1 text-inherit ml-1" onClick={() => clearFilter(f.type)}>
                                                    <X className="size-3" />
                                                </Button>
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </div>

                            <div className="bg-muted/50 rounded-lg border p-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-4">
                                        <span className="text-sm font-medium">Showing {freight.total ?? freight.data.length} results</span>
                                        {searchQuery && <span className="text-muted-foreground text-sm">for "{searchQuery}"</span>}
                                    </div>
                                    <div className="text-muted-foreground text-xs">Sorted by {sortOptions.find(s => s.id === selectedSort)?.label}</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Freight Grid — ProductCard1 style */}
                    <section className="pb-12">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            {freight.data.length === 0 ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                            <Package className="size-6 text-muted-foreground" />
                                        </div>
                                        <h3 className="font-semibold">No active freight found</h3>
                                        <p className="max-w-md text-sm text-muted-foreground">Try adjusting your filters or check back later. Active posts appear after admin approval.</p>
                                        <Button variant="outline" asChild>
                                            <Link href={auth?.user ? '/my-freight' : '/register'}>{auth?.user ? 'Post Freight' : 'Join to Post'}</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ) : (
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {freight.data.map((f) => {
                                        const origin = (f as unknown as Record<string, unknown>).originLocation as Freight['originLocation'] ?? (f as unknown as Record<string, unknown>).origin_location as Freight['originLocation'];
                                        const destination = (f as unknown as Record<string, unknown>).destinationLocation as Freight['destinationLocation'] ?? (f as unknown as Record<string, unknown>).destination_location as Freight['destinationLocation'];
                                        const cargoType = (f as unknown as Record<string, unknown>).cargoType as Freight['cargoType'] ?? (f as unknown as Record<string, unknown>).cargo_type as Freight['cargoType'];
                                        const truckType = (f as unknown as Record<string, unknown>).truckType as Freight['truckType'] ?? (f as unknown as Record<string, unknown>).truck_type as Freight['truckType'];
                                        const routeSnapshot = (f as unknown as Record<string, unknown>).routeSnapshot as Freight['routeSnapshot'] ?? (f as unknown as Record<string, unknown>).route_snapshot as Freight['routeSnapshot'];
                                        const isOwn = auth?.user?.id && f.user?.id === auth.user.id;
                                        const interest = userInterestMap?.[f.id];
                                        return (
                                        <Card key={f.ulid} className="overflow-hidden border bg-card">
                                            <div className="p-4 pb-3">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2 text-[15px] font-bold tracking-tight">
                                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10"><MapPin className="size-3.5 text-primary" /></span>
                                                            <span className="truncate">{origin?.name ?? '—'}</span>
                                                            <ArrowRight className="size-3.5 shrink-0 text-muted-foreground/50" />
                                                            <span className="truncate">{destination?.name ?? '—'}</span>
                                                        </div>
                                                    </div>
                                                    <Badge variant={interest ? (interest.status === 'rejected' || interest.status === 'withdrawn' ? 'destructive' : 'secondary') : isOwn ? 'outline' : 'default'} className="capitalize text-[11px] shrink-0 rounded-full px-2.5">
                                                        {interest ? interest.status : isOwn ? 'Your Post' : 'Active'}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="px-4 pb-3 space-y-2.5">
                                                <div className="overflow-hidden rounded-lg border divide-y">
                                                    <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><MapPin className="size-3.5" /> Origin</span>
                                                        <span className="text-sm font-medium">{origin?.name ?? '—'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><MapPin className="size-3.5" /> Destination</span>
                                                        <span className="text-sm font-medium">{destination?.name ?? '—'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><Package className="size-3.5" /> Item</span>
                                                        <span className="text-sm font-medium">{(f as unknown as { item_name?: string }).item_name ?? cargoType?.name ?? 'General Cargo'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><Calendar className="size-3.5" /> Pickup</span>
                                                        <span className="text-sm font-medium">{f.pickup_date ? String(f.pickup_date).slice(0,10) : 'Flexible'}{(f as unknown as { pickup_time?: string }).pickup_time ? ` ${String((f as unknown as { pickup_time?: string }).pickup_time).slice(0,5)}` : ''}</span>
                                                    </div>
                                                </div>
                                                <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">{f.cargo_description ?? 'No description'}</p>
                                                <div className="divide-y overflow-hidden rounded-lg border text-xs">
                                                    <div className="flex items-center justify-between px-3 py-2">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><Weight className="size-3.5" /> Weight</span>
                                                        <span className="text-sm font-semibold">{(f as unknown as { actual_weight?: string }).actual_weight ? `${(f as unknown as { actual_weight?: string }).actual_weight} ${(f as unknown as { weight_unit?: string }).weight_unit ?? 'ton'}` : '—'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between px-3 py-2">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><Banknote className="size-3.5" /> Tariff</span>
                                                        <span className="text-sm font-semibold">{(f as unknown as { tariff_amount?: string }).tariff_amount ? `${(f as unknown as { tariff_amount?: string }).tariff_amount} ${(f as unknown as { tariff_currency?: string }).tariff_currency ?? 'ETB'}` : '—'}</span>
                                                    </div>
                                                    <div className="flex items-center justify-between px-3 py-2">
                                                        <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground"><Truck className="size-3.5" /> Trucks</span>
                                                        <span className="text-sm font-semibold">{truckType?.name ?? 'Any'} {f.truck_count ? `×${f.truck_count}` : ''}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex gap-2 p-4 pt-0">
                                                <Button asChild className="flex-1 h-8 text-xs"><Link href={`/freight/${f.ulid}`}>View Details</Link></Button>
                                                {isOwn ? (
                                                    <Badge variant="secondary" className="flex-1 justify-center py-1.5 text-xs">Your Post</Badge>
                                                ) : interest ? (
                                                    <Button variant="outline" className="flex-1 h-8 text-xs capitalize" onClick={() => interest.status !== 'rejected' && interest.status !== 'withdrawn' && router.post(`/interests/${interest.id}/withdraw`, {}, { preserveScroll: true })} disabled={interest.status === 'rejected' || interest.status === 'withdrawn'}>
                                                        {interest.status === 'rejected' || interest.status === 'withdrawn' ? interest.status : 'Leave'}
                                                    </Button>
                                                ) : (
                                                    <Button variant="secondary" className="flex-1 h-8 text-xs" onClick={() => {
                                                        if (!auth?.user) { router.visit('/login'); return; }
                                                        router.post(`/my-freight/${f.ulid}/interests`, { message: '', contact_name: auth.user.name ?? '', contact_phone: '', contact_email: auth.user.email ?? '' }, { preserveScroll: true });
                                                    }}>Express Interest</Button>
                                                )}
                                            </div>
                                        </Card>
                                        );
                                    })}
                                </div>
                            )}
                            {freight.links && freight.links.length > 3 && (
                                <div className="mt-8 flex flex-wrap gap-1 justify-center">
                                    {freight.links.map((link, i) => (
                                        <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}>
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        </Button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>
                </main>
                <LandingFooter />
            </div>
        </>
    );
}
