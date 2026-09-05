import { Head, Link, router, usePage } from '@inertiajs/react';
import { LandingFooter, LandingHeader } from '@/components/landing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    marketplaceEntitlement?: { can_post: boolean; can_interest: boolean; remaining_posts: number; plan: { slug: string; name: string } } | null;
}

const sortOptions = [
    { id: 'latest', label: 'Latest' },
    { id: 'distance_asc', label: 'Distance: Near to Far' },
    { id: 'distance_desc', label: 'Distance: Far to Near' },
    { id: 'pickup_asc', label: 'Pickup: Earliest' },
];

export default function DiscoverIndex({ freight, filters, canRegister = true, userInterestMap = {}, marketplaceEntitlement }: Props) {
    const { auth } = usePage<SharedData>().props;
    const safeFilters = (filters && !Array.isArray(filters) ? filters : {}) as Record<string, string>;
    const getStringFilter = (key: string, fallback = ''): string => {
        const val = safeFilters[key];
        return typeof val === 'string' ? val : fallback;
    };
    const safeFreight = (freight && typeof freight === 'object' && !Array.isArray(freight) ? freight : { data: [], links: [], total: 0, from: null, to: null }) as Props['freight'];
    const safeInterestMap = (userInterestMap && typeof userInterestMap === 'object' && !Array.isArray(userInterestMap) ? userInterestMap : {}) as Record<number, { id: number; status: string }>;
    const [searchQuery, setSearchQuery] = useState(getStringFilter('search', ''));
    const [origin, setOrigin] = useState(getStringFilter('origin', ''));
    const [destination, setDestination] = useState(getStringFilter('destination', ''));
    const [selectedCargo, setSelectedCargo] = useState(getStringFilter('cargo', 'all') || 'all');
    const [selectedSort, setSelectedSort] = useState(getStringFilter('sort', 'latest') || 'latest');

    const activeFilters: Array<{ type: string; label: string }> = [];
    if (origin) activeFilters.push({ type: 'origin', label: `Origin #${origin}` });
    if (destination) activeFilters.push({ type: 'destination', label: `Destination #${destination}` });
    if (selectedCargo !== 'all') activeFilters.push({ type: 'cargo', label: selectedCargo });
    if (searchQuery) activeFilters.push({ type: 'search', label: `"${searchQuery}"` });

    const clearFilter = (type: string) => {
        if (type === 'origin') {
            setOrigin('');
            router.get('/freight', { destination: destination || undefined, search: searchQuery || undefined, cargo: selectedCargo !== 'all' ? selectedCargo : undefined, sort: selectedSort !== 'latest' ? selectedSort : undefined }, { preserveState: true });
        }
        if (type === 'destination') {
            setDestination('');
            router.get('/freight', { origin: origin || undefined, search: searchQuery || undefined, cargo: selectedCargo !== 'all' ? selectedCargo : undefined, sort: selectedSort !== 'latest' ? selectedSort : undefined }, { preserveState: true });
        }
        if (type === 'cargo') {
            setSelectedCargo('all');
            router.get('/freight', { origin: origin || undefined, destination: destination || undefined, search: searchQuery || undefined, sort: selectedSort !== 'latest' ? selectedSort : undefined }, { preserveState: true });
        }
        if (type === 'search') {
            setSearchQuery('');
            router.get('/freight', { origin: origin || undefined, destination: destination || undefined, cargo: selectedCargo !== 'all' ? selectedCargo : undefined, sort: selectedSort !== 'latest' ? selectedSort : undefined }, { preserveState: true });
        }
    };
    const clearAll = () => {
        setOrigin('');
        setDestination('');
        setSelectedCargo('all');
        setSearchQuery('');
        router.get('/freight', {}, { preserveState: true });
    };

    const doSearch = () => {
        router.get(
            '/freight',
            {
                origin: origin || undefined,
                destination: destination || undefined,
                search: searchQuery || undefined,
                cargo: selectedCargo !== 'all' ? selectedCargo : undefined,
                sort: selectedSort !== 'latest' ? selectedSort : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Discover Freight — NumanFreight Ethiopian Marketplace" />
            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main id="main-content">
                    <section aria-labelledby="freight-heading" className="py-8">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-6">
                                <h1 id="freight-heading" className="text-3xl font-bold tracking-tight text-balance">
                                    Discover Freight Across Ethiopia
                                </h1>
                                <p className="mt-2 max-w-2xl text-muted-foreground">
                                    Browse {safeFreight.total ?? safeFreight.data.length} active freight posts — 934 major towns from Addis Ababa to Jijiga, Hawassa to Mekelle. Find
                                    verified loads for your fleet.
                                </p>
                            </div>

                            <Card className="gap-0 overflow-hidden border py-0 shadow-sm">
                                <CardContent className="space-y-4 p-4 sm:p-5">
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            doSearch();
                                        }}
                                        aria-label="Filter freight posts"
                                        className="space-y-4"
                                    >
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                            <div className="w-full max-w-md space-y-1.5">
                                                <Label htmlFor="freight-search" className="text-xs font-medium">
                                                    Search
                                                </Label>
                                                <div className="relative">
                                                    <Search aria-hidden="true" className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                                    <Input
                                                        id="freight-search"
                                                        type="search"
                                                        placeholder="Search cargo, description..."
                                                        value={searchQuery}
                                                        onChange={(e) => setSearchQuery(e.target.value)}
                                                        className="h-9 pl-10"
                                                        aria-label="Search cargo and description"
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-medium" id="sort-label">
                                                    Sort by
                                                </Label>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="outline" className="h-9 w-full justify-between sm:w-auto" aria-labelledby="sort-label">
                                                            <span className="inline-flex items-center gap-2">
                                                                <SlidersHorizontal className="size-4" aria-hidden="true" />
                                                                {sortOptions.find((s) => s.id === selectedSort)?.label}
                                                            </span>
                                                            <ChevronDown className="size-4 opacity-50" aria-hidden="true" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" className="w-56">
                                                        {sortOptions.map((opt) => (
                                                            <DropdownMenuItem
                                                                key={opt.id}
                                                                onClick={() => setSelectedSort(opt.id)}
                                                                className={selectedSort === opt.id ? 'bg-accent' : ''}
                                                                aria-current={selectedSort === opt.id ? 'true' : undefined}
                                                            >
                                                                {opt.label}
                                                            </DropdownMenuItem>
                                                        ))}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>

                                        <fieldset className="space-y-3 border-0 p-0">
                                            <legend className="sr-only">Location and cargo filters</legend>
                                            <div className="flex flex-wrap items-end gap-3">
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="origin-filter" className="text-xs font-medium">
                                                        Origin
                                                    </Label>
                                                    <LocationSearchInput
                                                        id="origin-filter"
                                                        ariaLabel="Origin location"
                                                        onSelectLocation={(loc) => setOrigin(loc ? String(loc.id) : '')}
                                                        placeholder="Addis Ababa…"
                                                        className="w-48"
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="destination-filter" className="text-xs font-medium">
                                                        Destination
                                                    </Label>
                                                    <LocationSearchInput
                                                        id="destination-filter"
                                                        ariaLabel="Destination location"
                                                        onSelectLocation={(loc) => setDestination(loc ? String(loc.id) : '')}
                                                        placeholder="Hawassa…"
                                                        className="w-48"
                                                    />
                                                </div>
                                                <div className="space-y-1.5">
                                                    <Label className="text-xs font-medium" id="cargo-label">
                                                        Cargo
                                                    </Label>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="outline" size="sm" className="h-8 gap-1.5 text-xs" aria-labelledby="cargo-label">
                                                                {selectedCargo === 'all' ? 'All Cargo' : selectedCargo}
                                                                <ChevronDown className="size-3 opacity-50" aria-hidden="true" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent className="w-56">
                                                            {['all', 'General', 'Cereals', 'Construction', 'Refrigerated'].map((c) => (
                                                                <DropdownMenuItem
                                                                    key={c}
                                                                    onClick={() => setSelectedCargo(c)}
                                                                    className={selectedCargo === c ? 'bg-accent' : ''}
                                                                    aria-current={selectedCargo === c ? 'true' : undefined}
                                                                >
                                                                    {c === 'all' ? 'All Cargo' : c}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                                <Button type="submit" size="sm" className="h-8 px-3 text-xs">
                                                    <Search className="size-3.5" aria-hidden="true" /> Apply filters
                                                </Button>
                                                <Button type="button" variant="ghost" size="sm" onClick={clearAll} className="h-8 px-3 text-xs">
                                                    Clear all
                                                </Button>
                                            </div>
                                        </fieldset>
                                    </form>

                                    {activeFilters.length > 0 && (
                                        <div className="flex flex-wrap items-center gap-2" role="region" aria-label="Active filters">
                                            <span className="text-sm font-medium">Active filters:</span>
                                            {activeFilters.map((f) => (
                                                <span
                                                    key={f.type}
                                                    className="inline-flex items-center gap-1 rounded-full border bg-secondary px-2.5 py-1 text-xs font-medium"
                                                >
                                                    {f.label}
                                                    <button
                                                        type="button"
                                                        aria-label={`Remove ${f.label} filter`}
                                                        onClick={() => clearFilter(f.type)}
                                                        className="ml-1 inline-flex size-4 items-center justify-center rounded-full p-0.5 hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                    >
                                                        <X className="size-3" aria-hidden="true" />
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {marketplaceEntitlement && !marketplaceEntitlement.can_post && (
                                <Card className="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                                    <CardContent className="flex flex-wrap items-center justify-between gap-3 p-4">
                                        <div>
                                            <p className="text-sm font-semibold text-amber-900 dark:text-amber-100">Post limit reached on {marketplaceEntitlement.plan.name} plan</p>
                                            <p className="text-xs text-amber-800 dark:text-amber-200">Upgrade to post more freight. ETB via Chapa.</p>
                                        </div>
                                        <Button size="sm" asChild>
                                            <Link href="/billing/plans">View ETB Plans</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex items-center justify-between gap-4 px-1 py-2 text-sm" aria-live="polite" aria-atomic="true">
                                <p className="font-medium">
                                    Showing {safeFreight.from ?? 1}–{safeFreight.to ?? safeFreight.data.length} of {safeFreight.total ?? safeFreight.data.length} results
                                </p>
                                <p className="text-xs text-muted-foreground">Sorted by {sortOptions.find((s) => s.id === selectedSort)?.label}</p>
                            </div>
                        </div>
                    </section>

                    <section aria-labelledby="results-heading" className="pb-12">
                        <h2 id="results-heading" className="sr-only">
                            Freight results
                        </h2>
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            {safeFreight.data.length === 0 ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                                        <div className="flex size-12 items-center justify-center rounded-full bg-muted" aria-hidden="true">
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
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" role="list">
                                    {safeFreight.data.map((f) => {
                                        const origin =
                                            ((f as unknown as Record<string, unknown>).originLocation as Freight['originLocation']) ??
                                            ((f as unknown as Record<string, unknown>).origin_location as Freight['originLocation']);
                                        const destination =
                                            ((f as unknown as Record<string, unknown>).destinationLocation as Freight['destinationLocation']) ??
                                            ((f as unknown as Record<string, unknown>).destination_location as Freight['destinationLocation']);
                                        const cargoType =
                                            ((f as unknown as Record<string, unknown>).cargoType as Freight['cargoType']) ??
                                            ((f as unknown as Record<string, unknown>).cargo_type as Freight['cargoType']);
                                        const truckType =
                                            ((f as unknown as Record<string, unknown>).truckType as Freight['truckType']) ??
                                            ((f as unknown as Record<string, unknown>).truck_type as Freight['truckType']);
                                        const isOwn = auth?.user?.id && f.user?.id === auth.user.id;
                                        const interest = safeInterestMap?.[f.id];
                                        const titleId = `freight-${f.ulid}-title`;
                                        return (
                                            <Card key={f.ulid} role="listitem" aria-labelledby={titleId} className="flex flex-col overflow-hidden border bg-card">
                                                <div className="p-4 pb-3">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <h3 id={titleId} className="flex min-w-0 items-center gap-2 text-[15px] font-bold tracking-tight">
                                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10" aria-hidden="true">
                                                                <MapPin className="size-3.5 text-primary" />
                                                            </span>
                                                            <span className="truncate">{origin?.name ?? '—'}</span>
                                                            <ArrowRight className="size-3.5 shrink-0 text-muted-foreground/50" aria-hidden="true" />
                                                            <span className="truncate">{destination?.name ?? '—'}</span>
                                                        </h3>
                                                        <Badge
                                                            variant={
                                                                interest
                                                                    ? interest.status === 'rejected' || interest.status === 'withdrawn'
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                                    : isOwn
                                                                      ? 'outline'
                                                                      : 'default'
                                                            }
                                                            className="shrink-0 rounded-full px-2.5 text-[11px] capitalize"
                                                            aria-label={`Status: ${interest ? interest.status : isOwn ? 'Your Post' : 'Active'}`}
                                                        >
                                                            {interest ? interest.status : isOwn ? 'Your Post' : 'Active'}
                                                        </Badge>
                                                    </div>
                                                </div>
                                                <div className="flex flex-1 flex-col gap-2.5 px-4 pb-3">
                                                    <div className="overflow-hidden rounded-lg border divide-y">
                                                        <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                                                <Package className="size-3.5" aria-hidden="true" /> Item
                                                            </span>
                                                            <span className="text-sm font-medium">{(f as unknown as { item_name?: string }).item_name ?? cargoType?.name ?? 'General Cargo'}</span>
                                                        </div>
                                                        <div className="flex items-center justify-between px-3 py-2 text-xs">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                                                <Calendar className="size-3.5" aria-hidden="true" /> Pickup
                                                            </span>
                                                            <span className="text-sm font-medium">
                                                                {f.pickup_date ? String(f.pickup_date).slice(0, 10) : 'Flexible'}
                                                                {(f as unknown as { pickup_time?: string }).pickup_time ? ` ${String((f as unknown as { pickup_time?: string }).pickup_time).slice(0, 5)}` : ''}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="divide-y overflow-hidden rounded-lg border text-xs">
                                                        <div className="flex items-center justify-between px-3 py-2">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                                                <Weight className="size-3.5" aria-hidden="true" /> Weight
                                                            </span>
                                                            <span className="text-sm font-semibold">
                                                                {(f as unknown as { actual_weight?: string }).actual_weight
                                                                    ? `${(f as unknown as { actual_weight?: string }).actual_weight} ${(f as unknown as { weight_unit?: string }).weight_unit ?? 'ton'}`
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between px-3 py-2">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                                                <Banknote className="size-3.5" aria-hidden="true" /> Tariff
                                                            </span>
                                                            <span className="text-sm font-semibold">
                                                                {(f as unknown as { tariff_amount?: string }).tariff_amount
                                                                    ? `${(f as unknown as { tariff_amount?: string }).tariff_amount} ${(f as unknown as { tariff_currency?: string }).tariff_currency ?? 'ETB'}`
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                        <div className="flex items-center justify-between px-3 py-2">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                                                                <Truck className="size-3.5" aria-hidden="true" /> Trucks
                                                            </span>
                                                            <span className="text-sm font-semibold">
                                                                {truckType?.name ?? 'Any'} {f.truck_count ? `×${f.truck_count}` : ''}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex gap-2 p-4 pt-0">
                                                    <Button asChild className="flex-1 h-8 text-xs">
                                                        <Link href={`/freight/${f.ulid}`} aria-label={`View details for ${origin?.name ?? 'freight'} to ${destination?.name ?? ''}`}>
                                                            View Details
                                                        </Link>
                                                    </Button>
                                                    {isOwn ? (
                                                        <Badge variant="secondary" className="flex flex-1 items-center justify-center rounded-md py-1.5 text-xs">
                                                            Your Post
                                                        </Badge>
                                                    ) : interest ? (
                                                        <Button
                                                            variant="outline"
                                                            className="flex-1 h-8 text-xs capitalize"
                                                            onClick={() =>
                                                                interest.status !== 'rejected' &&
                                                                interest.status !== 'withdrawn' &&
                                                                router.post(`/interests/${interest.id}/withdraw`, {}, { preserveScroll: true })
                                                            }
                                                            disabled={interest.status === 'rejected' || interest.status === 'withdrawn'}
                                                            aria-label={interest.status === 'rejected' || interest.status === 'withdrawn' ? `Interest ${interest.status}` : 'Withdraw interest'}
                                                        >
                                                            {interest.status === 'rejected' || interest.status === 'withdrawn' ? interest.status : 'Leave'}
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            variant="secondary"
                                                            className="flex-1 h-8 text-xs"
                                                            onClick={() => {
                                                                if (!auth?.user) {
                                                                    router.visit('/login');
                                                                    return;
                                                                }
                                                                router.post(
                                                                    `/my-freight/${f.ulid}/interests`,
                                                                    { message: '', contact_name: auth.user.name ?? '', contact_phone: '', contact_email: auth.user.email ?? '' },
                                                                    { preserveScroll: true },
                                                                );
                                                            }}
                                                            aria-label={`Express interest in ${origin?.name ?? 'freight'} to ${destination?.name ?? ''}`}
                                                        >
                                                            Express Interest
                                                        </Button>
                                                    )}
                                                </div>
                                            </Card>
                                        );
                                    })}
                                </div>
                            )}
                            {safeFreight.links && safeFreight.links.length > 3 && (
                                <nav aria-label="Freight pagination" className="mt-8 flex flex-wrap justify-center gap-1">
                                    {safeFreight.links.map((link, i) => {
                                        const isDisabled = !link.url;
                                        const labelText = link.label.replace(/&laquo;|&raquo;/g, '').trim() || link.label;
                                        return (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                disabled={isDisabled}
                                                aria-current={link.active ? 'page' : undefined}
                                                aria-label={labelText}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            >
                                                <span dangerouslySetInnerHTML={{ __html: link.label }} aria-hidden="true" />
                                            </Button>
                                        );
                                    })}
                                </nav>
                            )}
                        </div>
                    </section>
                </main>
                <LandingFooter />
            </div>
        </>
    );
}
