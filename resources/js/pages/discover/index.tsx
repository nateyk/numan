import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
import LocationSearchInput from '@/components/maps/location-search-input';
import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { freightRoute } from '@/lib/freight-route';
import type { SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    Calendar,
    ChevronDown,
    MapPin,
    Package,
    Search,
    SlidersHorizontal,
    Truck,
    Weight,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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
    /**
     * FreightPostResource sends origin / destination / cargo_type /
     * truck_type. A bare Eloquent model serialises the same relations as
     * origin_location / destination_location instead, so both are declared
     * and read through freightRoute() rather than picked apart here.
     */
    origin?: { id: number; name: string; region_name: string };
    destination?: { id: number; name: string; region_name: string };
    origin_location?: { id: number; name: string; region_name: string };
    destination_location?: { id: number; name: string; region_name: string };
    cargo_type?: { name: string };
    truck_type?: { name: string };
    routeSnapshot?: {
        distance_meters: number;
        duration_seconds: number;
    } | null;
    route_snapshot?: {
        distance_meters: number;
        duration_seconds: number;
    } | null;
    /**
     * The only owner-related fact a browse card receives. The identity itself
     * is never sent here; the detail page unlocks it for the owner or after a
     * reveal.
     */
    is_own_post: boolean;
}

interface Props {
    freight: {
        data: Freight[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: Record<string, string>;
    canRegister?: boolean;
    userInterestMap?: Record<number, { id: number; status: string }>;
    marketplaceEntitlement?: {
        can_post: boolean;
        can_interest: boolean;
        remaining_posts: number;
        plan: { slug: string; name: string };
    } | null;
}

const sortOptions = [
    { id: 'latest', label: 'Latest' },
    { id: 'distance_asc', label: 'Distance: Near to Far' },
    { id: 'distance_desc', label: 'Distance: Far to Near' },
    { id: 'pickup_asc', label: 'Pickup: Earliest' },
];

export default function DiscoverIndex({
    freight,
    filters,
    canRegister = true,
    userInterestMap = {},
    marketplaceEntitlement,
}: Props) {
    const { auth, marketplace } = usePage<SharedData>().props;
    const interestsEnabled = marketplace?.interests_enabled ?? false;
    const safeFilters = (
        filters && !Array.isArray(filters) ? filters : {}
    ) as Record<string, string>;
    const getStringFilter = (key: string, fallback = ''): string => {
        const val = safeFilters[key];
        return typeof val === 'string' ? val : fallback;
    };
    // A payload that is not the expected object is a load failure, not an
    // empty market: the old shape-guard flattened both into "No active freight
    // found", telling a carrier the market was empty when the list had simply
    // failed to load.
    const freightLoadFailed =
        !freight || typeof freight !== 'object' || Array.isArray(freight);
    const safeFreight = (
        freightLoadFailed
            ? { data: [], links: [], total: 0, from: null, to: null }
            : freight
    ) as Props['freight'];
    const safeInterestMap = (
        userInterestMap &&
        typeof userInterestMap === 'object' &&
        !Array.isArray(userInterestMap)
            ? userInterestMap
            : {}
    ) as Record<number, { id: number; status: string }>;
    const [searchQuery, setSearchQuery] = useState(
        getStringFilter('search', ''),
    );
    const [origin, setOrigin] = useState(getStringFilter('origin', ''));
    const [destination, setDestination] = useState(
        getStringFilter('destination', ''),
    );
    const [originName, setOriginName] = useState(
        getStringFilter('origin_name', ''),
    );
    const [destinationName, setDestinationName] = useState(
        getStringFilter('destination_name', ''),
    );
    const [selectedCargo, setSelectedCargo] = useState(
        getStringFilter('cargo', 'all') || 'all',
    );
    const [selectedSort, setSelectedSort] = useState(
        getStringFilter('sort', 'latest') || 'latest',
    );
    // True while a filter visit is in flight, so the results header can say
    // "Updating results…" instead of announcing a sort order that has not
    // landed yet.
    const [updatingResults, setUpdatingResults] = useState(false);
    const [showMoreFilters, setShowMoreFilters] = useState(false);

    // Express Interest is a commitment the shipper sees, so it asks first: one
    // tap used to fire the POST from mid-scroll with no confirmation and no
    // processing state, and a double tap fired it twice.
    const [pendingInterest, setPendingInterest] = useState<Freight | null>(
        null,
    );
    const { post: postInterest, processing: interestProcessing } = useForm({
        message: '',
    });
    const pendingRoute = pendingInterest ? freightRoute(pendingInterest) : null;

    const confirmExpressInterest = () => {
        if (!interestsEnabled || !pendingInterest) return;

        postInterest(`/my-freight/${pendingInterest.ulid}/interests`, {
            preserveScroll: true,
            onFinish: () => setPendingInterest(null),
            onSuccess: () => toast.success('Interest expressed successfully.'),
            onError: (errors) => {
                const msg =
                    (errors as Record<string, string>).interest ||
                    (Object.values(errors)[0] as string);
                if (msg) {
                    toast.error(msg);
                    if (String(msg).toLowerCase().includes('public profile')) {
                        setTimeout(() => router.visit('/public-profile'), 1500);
                    } else if (
                        String(msg).toLowerCase().includes('phone number')
                    ) {
                        setTimeout(() => router.visit('/public-profile'), 1500);
                    } else if (
                        String(msg).toLowerCase().includes('not active')
                    ) {
                        toast.info(
                            'This freight is not yet active for interest.',
                        );
                    }
                } else {
                    toast.error(
                        'Failed to express interest. Please try again.',
                    );
                }
            },
        });
    };

    // The empty state tells three stories apart: filters that match nothing, a
    // market with no posts, and a payload that never arrived.
    const hasActiveFilters =
        origin !== '' ||
        destination !== '' ||
        searchQuery !== '' ||
        selectedCargo !== 'all' ||
        selectedSort !== 'latest';
    const resultTotal = safeFreight.total ?? safeFreight.data.length;

    const originLabel = originName || (origin ? `Location #${origin}` : '');
    const destinationLabel =
        destinationName || (destination ? `Location #${destination}` : '');
    // An OpenStreetMap pick has no directory row, so the server cannot match
    // it and the result is always empty. Name it plainly instead of letting
    // the generic "no freight" card take the blame.
    const unknownOrigin = origin !== '' && !/^\d+$/.test(origin);
    const unknownDestination = destination !== '' && !/^\d+$/.test(destination);

    const activeFilters: Array<{ type: string; label: string }> = [];
    if (origin)
        activeFilters.push({ type: 'origin', label: `Origin: ${originLabel}` });
    if (destination)
        activeFilters.push({
            type: 'destination',
            label: `Destination: ${destinationLabel}`,
        });
    if (selectedCargo !== 'all')
        activeFilters.push({ type: 'cargo', label: selectedCargo });
    if (searchQuery)
        activeFilters.push({ type: 'search', label: `"${searchQuery}"` });

    const clearFilter = (type: string) => {
        if (type === 'origin') {
            setOrigin('');
            setOriginName('');
            router.get(
                '/freight',
                {
                    destination: destination || undefined,
                    search: searchQuery || undefined,
                    cargo: selectedCargo !== 'all' ? selectedCargo : undefined,
                    sort: selectedSort !== 'latest' ? selectedSort : undefined,
                },
                { preserveState: true },
            );
        }
        if (type === 'destination') {
            setDestination('');
            setDestinationName('');
            router.get(
                '/freight',
                {
                    origin: origin || undefined,
                    search: searchQuery || undefined,
                    cargo: selectedCargo !== 'all' ? selectedCargo : undefined,
                    sort: selectedSort !== 'latest' ? selectedSort : undefined,
                },
                { preserveState: true },
            );
        }
        if (type === 'cargo') {
            setSelectedCargo('all');
            router.get(
                '/freight',
                {
                    origin: origin || undefined,
                    destination: destination || undefined,
                    search: searchQuery || undefined,
                    sort: selectedSort !== 'latest' ? selectedSort : undefined,
                },
                { preserveState: true },
            );
        }
        if (type === 'search') {
            setSearchQuery('');
            router.get(
                '/freight',
                {
                    origin: origin || undefined,
                    destination: destination || undefined,
                    cargo: selectedCargo !== 'all' ? selectedCargo : undefined,
                    sort: selectedSort !== 'latest' ? selectedSort : undefined,
                },
                { preserveState: true },
            );
        }
    };
    const clearAll = () => {
        setOrigin('');
        setDestination('');
        setOriginName('');
        setDestinationName('');
        setSelectedCargo('all');
        setSearchQuery('');
        router.get('/freight', {}, { preserveState: true });
    };

    // The dropdowns are decisions, not drafts: picking a sort or cargo applies
    // it immediately, the way removing a chip already does. Apply stays for the
    // text inputs, which are drafts. Overrides carry the picked value because
    // the state update has not landed when the visit fires.
    const doSearch = (overrides?: { sort?: string; cargo?: string }) => {
        const sort = overrides?.sort ?? selectedSort;
        const cargo = overrides?.cargo ?? selectedCargo;
        router.get(
            '/freight',
            {
                origin: origin || undefined,
                destination: destination || undefined,
                search: searchQuery || undefined,
                cargo: cargo !== 'all' ? cargo : undefined,
                sort: sort !== 'latest' ? sort : undefined,
            },
            {
                preserveState: true,
                replace: true,
                onStart: () => setUpdatingResults(true),
                onFinish: () => setUpdatingResults(false),
            },
        );
    };

    return (
        <>
            <Head title="Discover Freight — Numan Freight Ethiopian Marketplace" />
            <div className="min-h-screen bg-background text-foreground">
                <DiscoveryHeader canRegister={canRegister} />
                <main id="main-content">
                    <section aria-labelledby="freight-heading" className="pt-6 pb-3 sm:pt-8 sm:pb-4">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-4">
                                <h1
                                    id="freight-heading"
                                    className="text-2xl font-semibold tracking-tight text-balance sm:text-3xl"
                                >
                                    Find freight for your next trip
                                </h1>
                                <p className="mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                                    Browse {resultTotal} active load
                                    {resultTotal === 1 ? '' : 's'} and find
                                    routes that work for your fleet.
                                </p>
                            </div>

                            {/* Not overflow-hidden: the location combobox
                                must be able to escape this card when its
                                results open. */}
                            <Card className="gap-0 border py-0 shadow-none">
                                <CardContent className="space-y-4 p-4 sm:p-5 lg:p-6">
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            doSearch();
                                        }}
                                        aria-label="Filter freight posts"
                                        className="space-y-4"
                                    >
                                        <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-end">
                                            <div className="min-w-0 space-y-1.5">
                                                <Label
                                                    htmlFor="freight-search"
                                                    className="text-xs font-medium"
                                                >
                                                    Search
                                                </Label>
                                                <div className="relative">
                                                    <Search
                                                        aria-hidden="true"
                                                        className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                                    />
                                                    <Input
                                                        id="freight-search"
                                                        type="search"
                                                        placeholder="Search cargo, description..."
                                                        value={searchQuery}
                                                        onChange={(e) =>
                                                            setSearchQuery(
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-11 pl-10"
                                                        aria-label="Search cargo and description"
                                                    />
                                                </div>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label
                                                    className="text-xs font-medium"
                                                    id="sort-label"
                                                >
                                                    Sort by
                                                </Label>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            className="h-11 w-full justify-between"
                                                            aria-labelledby="sort-label"
                                                        >
                                                            <span className="inline-flex items-center gap-2">
                                                                <SlidersHorizontal
                                                                    className="size-4"
                                                                    aria-hidden="true"
                                                                />
                                                                {
                                                                    sortOptions.find(
                                                                        (s) =>
                                                                            s.id ===
                                                                            selectedSort,
                                                                    )?.label
                                                                }
                                                            </span>
                                                            <ChevronDown
                                                                className="size-4 opacity-50"
                                                                aria-hidden="true"
                                                            />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-56"
                                                    >
                                                        {sortOptions.map(
                                                            (opt) => (
                                                                <DropdownMenuItem
                                                                    key={opt.id}
                                                                    onClick={() => {
                                                                        setSelectedSort(
                                                                            opt.id,
                                                                        );
                                                                        doSearch(
                                                                            {
                                                                                sort: opt.id,
                                                                            },
                                                                        );
                                                                    }}
                                                                    className={
                                                                        selectedSort ===
                                                                        opt.id
                                                                            ? 'bg-accent'
                                                                            : ''
                                                                    }
                                                                    aria-current={
                                                                        selectedSort ===
                                                                        opt.id
                                                                            ? 'true'
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {opt.label}
                                                                </DropdownMenuItem>
                                                            ),
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>

                                        <div className="sm:contents">
                                            <button
                                                type="button"
                                                aria-expanded={showMoreFilters}
                                                aria-controls="freight-advanced-filters"
                                                onClick={() => setShowMoreFilters((current) => !current)}
                                                className="flex min-h-11 w-full items-center justify-between rounded-md border bg-muted/40 px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:hidden"
                                            >
                                                <span className="inline-flex items-center gap-2">
                                                    <SlidersHorizontal className="size-4" aria-hidden="true" />
                                                    Route & cargo filters
                                                </span>
                                                <ChevronDown className={showMoreFilters ? 'size-4 rotate-180 text-muted-foreground transition-transform' : 'size-4 text-muted-foreground transition-transform'} aria-hidden="true" />
                                            </button>
                                            <fieldset
                                                id="freight-advanced-filters"
                                                className={showMoreFilters ? 'mt-3 block border-0 p-0 sm:mt-0 sm:block' : 'mt-3 hidden border-0 p-0 sm:mt-0 sm:block'}
                                            >
                                            <legend className="sr-only">
                                                Location and cargo filters
                                            </legend>
                                            <div className="grid grid-cols-1 gap-x-4 gap-y-3 border-t border-border/70 pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                                <div className="min-w-0 space-y-1.5">
                                                    <Label
                                                        htmlFor="origin-filter"
                                                        className="text-xs font-medium"
                                                    >
                                                        Origin
                                                    </Label>
                                                    <LocationSearchInput
                                                        id="origin-filter"
                                                        ariaLabel="Origin location"
                                                        initialName={
                                                            originName ||
                                                            undefined
                                                        }
                                                        onSelectLocation={(
                                                            loc,
                                                        ) => {
                                                            setOrigin(
                                                                loc
                                                                    ? String(
                                                                          loc.id,
                                                                      )
                                                                    : '',
                                                            );
                                                            setOriginName(
                                                                loc
                                                                    ? `${loc.name}, ${loc.region_name}`
                                                                    : '',
                                                            );
                                                        }}
                                                        placeholder="Addis Ababa…"
                                                        className="w-full [&_input]:h-11"
                                                        localOnly
                                                    />
                                                </div>
                                                <div className="min-w-0 space-y-1.5">
                                                    <Label
                                                        htmlFor="destination-filter"
                                                        className="text-xs font-medium"
                                                    >
                                                        Destination
                                                    </Label>
                                                    <LocationSearchInput
                                                        id="destination-filter"
                                                        ariaLabel="Destination location"
                                                        initialName={
                                                            destinationName ||
                                                            undefined
                                                        }
                                                        onSelectLocation={(
                                                            loc,
                                                        ) => {
                                                            setDestination(
                                                                loc
                                                                    ? String(
                                                                          loc.id,
                                                                      )
                                                                    : '',
                                                            );
                                                            setDestinationName(
                                                                loc
                                                                    ? `${loc.name}, ${loc.region_name}`
                                                                    : '',
                                                            );
                                                        }}
                                                        placeholder="Hawassa…"
                                                        className="w-full [&_input]:h-11"
                                                        localOnly
                                                    />
                                                </div>
                                                <div className="min-w-0 space-y-1.5">
                                                    <Label
                                                        className="text-xs font-medium"
                                                        id="cargo-label"
                                                    >
                                                        Cargo
                                                    </Label>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                className="h-11 w-full justify-between gap-2 px-3 text-sm"
                                                                aria-labelledby="cargo-label"
                                                            >
                                                                {selectedCargo ===
                                                                'all'
                                                                    ? 'All Cargo'
                                                                    : selectedCargo}
                                                                <ChevronDown
                                                                    className="size-3 opacity-50"
                                                                    aria-hidden="true"
                                                                />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent className="w-56">
                                                            {[
                                                                'all',
                                                                'General',
                                                                'Cereals',
                                                                'Construction',
                                                                'Refrigerated',
                                                            ].map((c) => (
                                                                <DropdownMenuItem
                                                                    key={c}
                                                                    onClick={() => {
                                                                        setSelectedCargo(
                                                                            c,
                                                                        );
                                                                        doSearch(
                                                                            {
                                                                                cargo: c,
                                                                            },
                                                                        );
                                                                    }}
                                                                    className={
                                                                        selectedCargo ===
                                                                        c
                                                                            ? 'bg-accent'
                                                                            : ''
                                                                    }
                                                                    aria-current={
                                                                        selectedCargo ===
                                                                        c
                                                                            ? 'true'
                                                                            : undefined
                                                                    }
                                                                >
                                                                    {c === 'all'
                                                                        ? 'All Cargo'
                                                                        : c}
                                                                </DropdownMenuItem>
                                                            ))}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                                <div
                                                    role="group"
                                                    aria-label="Filter actions"
                                                    className="flex min-w-0 items-center gap-2 self-end"
                                                >
                                                    <Button
                                                        type="submit"
                                                        className="h-11 min-w-0 flex-1 px-3 text-sm"
                                                        disabled={updatingResults}
                                                    >
                                                        <Search
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {updatingResults
                                                            ? 'Applying…'
                                                            : 'Apply filters'}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        onClick={clearAll}
                                                        className="h-11 shrink-0 px-2 text-sm text-muted-foreground hover:text-foreground"
                                                    >
                                                        Clear all
                                                    </Button>
                                                </div>
                                            </div>
                                            </fieldset>
                                        </div>
                                    </form>

                                    {activeFilters.length > 0 && (
                                        <div
                                            className="flex flex-wrap items-center gap-2"
                                            role="region"
                                            aria-label="Active filters"
                                        >
                                            <span className="text-sm font-medium">
                                                Active filters:
                                            </span>
                                            {activeFilters.map((f) => (
                                                <span
                                                    key={f.type}
                                                    className="inline-flex items-center gap-1 rounded-full border bg-secondary px-2.5 py-1 text-xs font-medium"
                                                >
                                                    {f.label}
                                                    <button
                                                        type="button"
                                                        aria-label={`Remove ${f.label} filter`}
                                                        onClick={() =>
                                                            clearFilter(f.type)
                                                        }
                                                        className="ml-1 inline-flex size-4 items-center justify-center rounded-full p-0.5 hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1"
                                                    >
                                                        <X
                                                            className="size-3"
                                                            aria-hidden="true"
                                                        />
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {marketplaceEntitlement &&
                                !marketplaceEntitlement.can_post && (
                                    <aside
                                        aria-label="Freight posting access"
                                        className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-muted/40 px-3 py-3 sm:px-4"
                                    >
                                        <div className="flex min-w-0 items-start gap-2.5">
                                            <AlertTriangle
                                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Posting limit reached on{' '}
                                                    {marketplaceEntitlement.plan.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    You can still browse loads. Upgrade to post more freight, billed in ETB via Chapa.
                                                </p>
                                            </div>
                                        </div>
                                        <Button size="sm" variant="outline" className="min-h-9" asChild>
                                            <Link href="/billing/plans">View plans</Link>
                                        </Button>
                                    </aside>
                                )}

                            {(unknownOrigin || unknownDestination) && (
                                <div className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                                    <p className="font-medium">
                                        {unknownOrigin && unknownDestination
                                            ? `“${originLabel}” and “${destinationLabel}” aren’t in the location directory yet, so no posts can match them.`
                                            : unknownOrigin
                                              ? `“${originLabel}” isn’t in the location directory yet, so no posts can match it.`
                                              : `“${destinationLabel}” isn’t in the location directory yet, so no posts can match it.`}{' '}
                                        <button
                                            type="button"
                                            onClick={clearAll}
                                            className="underline underline-offset-2"
                                        >
                                            Clear filters
                                        </button>{' '}
                                        to browse everything.
                                    </p>
                                </div>
                            )}
                            {/* Hidden at zero results: "Showing 1–0 of 0"
                                was the first line many readers saw. */}
                            {resultTotal > 0 && (
                                <div
                                    className="mt-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-1 py-2 text-sm"
                                    aria-live="polite"
                                    aria-atomic="true"
                                >
                                    <p className="font-medium">
                                        Showing {safeFreight.from ?? 1}–
                                        {safeFreight.to ??
                                            safeFreight.data.length}{' '}
                                        of {resultTotal} results
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {updatingResults ? (
                                            <>Updating results…</>
                                        ) : (
                                            <>
                                                {'Sorted by '}
                                                {
                                                    sortOptions.find(
                                                        (s) =>
                                                            s.id ===
                                                            getStringFilter(
                                                                'sort',
                                                                'latest',
                                                            ),
                                                    )?.label
                                                }
                                            </>
                                        )}
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>

                    <section
                        aria-labelledby="results-heading"
                        className="pb-12"
                    >
                        <h2 id="results-heading" className="sr-only">
                            Freight results
                        </h2>
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            {freightLoadFailed ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                                        <div
                                            className="flex size-12 items-center justify-center rounded-full bg-muted"
                                            aria-hidden="true"
                                        >
                                            <AlertTriangle className="size-6 text-muted-foreground" />
                                        </div>
                                        <h3 className="font-semibold">
                                            The freight list didn't load
                                        </h3>
                                        <p className="max-w-md text-sm text-muted-foreground">
                                            That's on us, not the market. Your
                                            filters are still in place — try
                                            again.
                                        </p>
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                router.reload({
                                                    only: ['freight'],
                                                })
                                            }
                                        >
                                            Try again
                                        </Button>
                                    </CardContent>
                                </Card>
                            ) : safeFreight.data.length === 0 ? (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                                        <div
                                            className="flex size-12 items-center justify-center rounded-full bg-muted"
                                            aria-hidden="true"
                                        >
                                            {hasActiveFilters ? (
                                                <SlidersHorizontal className="size-6 text-muted-foreground" />
                                            ) : (
                                                <Package className="size-6 text-muted-foreground" />
                                            )}
                                        </div>
                                        <h3 className="font-semibold">
                                            {hasActiveFilters
                                                ? 'No loads match these filters'
                                                : 'No active freight found'}
                                        </h3>
                                        <p className="max-w-md text-sm text-muted-foreground">
                                            {hasActiveFilters
                                                ? 'Nothing matches this combination yet — loosen a filter or two, or clear them to see everything.'
                                                : 'New loads appear after admin approval. Check back soon.'}
                                        </p>
                                        {hasActiveFilters ? (
                                            <Button
                                                variant="outline"
                                                onClick={clearAll}
                                            >
                                                Clear all filters
                                            </Button>
                                        ) : !auth?.user ? (
                                            <Button asChild>
                                                <Link
                                                    href={
                                                        canRegister
                                                            ? '/register'
                                                            : '/login'
                                                    }
                                                >
                                                    {canRegister
                                                        ? 'Join as a transporter'
                                                        : 'Sign in'}
                                                </Link>
                                            </Button>
                                        ) : (
                                            <PostFreightButton />
                                        )}
                                    </CardContent>
                                </Card>
                            ) : (
                                <div
                                    className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3"
                                    role="list"
                                >
                                    {safeFreight.data.map((f) => {
                                        // One reader for the route, whichever
                                        // key shape the payload used. Reading
                                        // originLocation here against a
                                        // FreightPostResource payload is what
                                        // put a dash on the card.
                                        const {
                                            origin,
                                            destination,
                                            cargoType,
                                            truckType,
                                        } = freightRoute(f);
                                        const isOwn = f.is_own_post;
                                        const snapshot =
                                            f.route_snapshot ?? f.routeSnapshot;
                                        const distanceText =
                                            snapshot?.distance_meters != null
                                                ? formatDistance(
                                                      snapshot.distance_meters,
                                                  )
                                                : '';
                                        const durationText =
                                            snapshot?.duration_seconds != null
                                                ? formatDuration(
                                                      snapshot.duration_seconds,
                                                  )
                                                : '';
                                        const interest = interestsEnabled
                                            ? safeInterestMap?.[f.id]
                                            : undefined;
                                        const titleId = `freight-${f.ulid}-title`;
                                        return (
                                            <Card
                                                key={f.ulid}
                                                role="listitem"
                                                aria-labelledby={titleId}
                                                className="flex flex-col gap-0 overflow-hidden border bg-card py-0 shadow-xs transition-colors hover:border-primary/25"
                                            >
                                                <div className="p-4 pb-3">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <h3
                                                            id={titleId}
                                                            className="flex min-w-0 items-center gap-2 text-base font-semibold tracking-tight"
                                                        >
                                                            <span
                                                                className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10"
                                                                aria-hidden="true"
                                                            >
                                                                <MapPin className="size-3.5 text-primary" />
                                                            </span>
                                                            <span className="truncate">
                                                                {origin?.name ??
                                                                    '—'}
                                                            </span>
                                                            <ArrowRight
                                                                className="size-3.5 shrink-0 text-muted-foreground/50"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="truncate">
                                                                {destination?.name ??
                                                                    '—'}
                                                            </span>
                                                        </h3>
                                                        <Badge
                                                            variant={
                                                                interest
                                                                    ? interest.status ===
                                                                          'rejected' ||
                                                                      interest.status ===
                                                                          'withdrawn'
                                                                        ? 'destructive'
                                                                        : 'secondary'
                                                                    : isOwn
                                                                      ? 'outline'
                                                                      : 'default'
                                                            }
                                                            className="shrink-0 rounded-full px-2.5 py-1 text-xs capitalize"
                                                            aria-label={`Status: ${interest ? interest.status : isOwn ? 'Your Post' : 'Active'}`}
                                                        >
                                                            {interest
                                                                ? interest.status
                                                                : isOwn
                                                                  ? 'Your Post'
                                                                  : 'Active'}
                                                        </Badge>
                                                    </div>
                                                    {(distanceText ||
                                                        durationText) && (
                                                        <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                                                            {(distanceText ||
                                                                durationText) && (
                                                                <span className="inline-flex items-center gap-1.5 font-medium text-foreground/80">
                                                                    {
                                                                        distanceText
                                                                    }
                                                                    {distanceText &&
                                                                        durationText && (
                                                                            <span
                                                                                className="text-muted-foreground/50"
                                                                                aria-hidden="true"
                                                                            >
                                                                                ·
                                                                            </span>
                                                                        )}
                                                                    {
                                                                        durationText
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex flex-1 flex-col gap-3 px-4 pb-4">
                                                    <div className="grid grid-cols-2 gap-3 border-t border-border/70 pt-3">
                                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                                <Package
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />{' '}
                                                                Item
                                                            </span>
                                                            <span className="break-words text-sm font-medium">
                                                                {(
                                                                    f as unknown as {
                                                                        item_name?: string;
                                                                    }
                                                                ).item_name ??
                                                                    cargoType?.name ??
                                                                    'General Cargo'}
                                                            </span>
                                                        </div>
                                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                                <Calendar
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />{' '}
                                                                Pickup
                                                            </span>
                                                            <span className="break-words text-sm font-medium">
                                                                {f.pickup_date
                                                                    ? String(
                                                                          f.pickup_date,
                                                                      ).slice(
                                                                          0,
                                                                          10,
                                                                      )
                                                                    : 'Flexible'}
                                                                {(
                                                                    f as unknown as {
                                                                        pickup_time?: string;
                                                                    }
                                                                ).pickup_time
                                                                    ? ` ${String((f as unknown as { pickup_time?: string }).pickup_time).slice(0, 5)}`
                                                                    : ''}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-3 text-xs">
                                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                                <Weight
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />{' '}
                                                                Weight
                                                            </span>
                                                            <span className="break-words text-sm font-semibold">
                                                                {(
                                                                    f as unknown as {
                                                                        actual_weight?: string;
                                                                    }
                                                                ).actual_weight
                                                                    ? `${(f as unknown as { actual_weight?: string }).actual_weight} ${(f as unknown as { weight_unit?: string }).weight_unit ?? 'ton'}`
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                                <Banknote
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />{' '}
                                                                Tariff
                                                            </span>
                                                            <span className="break-words text-sm font-semibold">
                                                                {(
                                                                    f as unknown as {
                                                                        tariff_amount?: string;
                                                                    }
                                                                ).tariff_amount
                                                                    ? `${(f as unknown as { tariff_amount?: string }).tariff_amount} ${(f as unknown as { tariff_currency?: string }).tariff_currency ?? 'ETB'}`
                                                                    : '—'}
                                                            </span>
                                                        </div>
                                                        <div className="flex min-w-0 flex-col items-start gap-1">
                                                            <span className="flex items-center gap-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                                                                <Truck
                                                                    className="size-3.5"
                                                                    aria-hidden="true"
                                                                />{' '}
                                                                Trucks
                                                            </span>
                                                            <span className="break-words text-sm font-semibold">
                                                                {truckType?.name ??
                                                                    'Any'}{' '}
                                                                {f.truck_count
                                                                    ? `×${f.truck_count}`
                                                                    : ''}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex gap-2 border-t border-border/70 px-4 py-3">
                                                    <Button
                                                        asChild
                                                        className="min-h-11 flex-1 text-sm"
                                                    >
                                                        <Link
                                                            href={`/freight/${f.ulid}`}
                                                            aria-label={`View details for ${origin?.name ?? 'freight'} to ${destination?.name ?? ''}`}
                                                        >
                                                            View Details
                                                        </Link>
                                                    </Button>
                                                    {isOwn ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="flex flex-1 items-center justify-center rounded-md py-2 text-sm"
                                                        >
                                                            Your Post
                                                        </Badge>
                                                    ) : interest ? (
                                                        <Button
                                                            variant="outline"
                                                            className="min-h-11 flex-1 text-sm capitalize"
                                                            onClick={() =>
                                                                interest.status !==
                                                                    'rejected' &&
                                                                interest.status !==
                                                                    'withdrawn' &&
                                                                router.post(
                                                                    `/interests/${interest.id}/withdraw`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            disabled={
                                                                interest.status ===
                                                                    'rejected' ||
                                                                interest.status ===
                                                                    'withdrawn'
                                                            }
                                                            aria-label={
                                                                interest.status ===
                                                                    'rejected' ||
                                                                interest.status ===
                                                                    'withdrawn'
                                                                    ? `Interest ${interest.status}`
                                                                    : 'Withdraw interest'
                                                            }
                                                        >
                                                            {interest.status ===
                                                                'rejected' ||
                                                            interest.status ===
                                                                'withdrawn'
                                                                ? interest.status
                                                                : 'Leave'}
                                                        </Button>
                                                    ) : interestsEnabled ? (
                                                        <Button
                                                            variant="secondary"
                                                            className="min-h-11 flex-1 text-sm"
                                                            onClick={() => {
                                                                if (
                                                                    !auth?.user
                                                                ) {
                                                                    router.visit(
                                                                        '/login',
                                                                    );
                                                                    return;
                                                                }
                                                                setPendingInterest(
                                                                    f,
                                                                );
                                                            }}
                                                            aria-label={`Express interest in ${origin?.name ?? 'freight'} to ${destination?.name ?? ''}`}
                                                        >
                                                            Express Interest
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </Card>
                                        );
                                    })}
                                </div>
                            )}
                            {safeFreight.links &&
                                safeFreight.links.length > 3 && (
                                    <nav
                                        aria-label="Freight pagination"
                                        className="mt-8 flex flex-wrap justify-center gap-1"
                                    >
                                        {safeFreight.links.map((link, i) => {
                                            const isDisabled = !link.url;
                                            const labelText =
                                                link.label
                                                    .replace(
                                                        /&laquo;|&raquo;/g,
                                                        '',
                                                    )
                                                    .trim() || link.label;
                                            return (
                                                <Button
                                                    key={i}
                                                    variant={
                                                        link.active
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    size="sm"
                                                    disabled={isDisabled}
                                                    aria-current={
                                                        link.active
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    aria-label={labelText}
                                                    onClick={() =>
                                                        link.url &&
                                                        router.get(
                                                            link.url,
                                                            {},
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <span
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                        aria-hidden="true"
                                                    />
                                                </Button>
                                            );
                                        })}
                                    </nav>
                                )}
                        </div>
                    </section>
                </main>

                <Dialog
                    open={interestsEnabled && pendingInterest !== null}
                    onOpenChange={(open) => {
                        if (!open && !interestProcessing) {
                            setPendingInterest(null);
                        }
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Express interest in this load?
                            </DialogTitle>
                            <DialogDescription>
                                {`${pendingRoute?.origin?.name ?? 'This load'} → ${pendingRoute?.destination?.name ?? 'its destination'} — the shipper will see your carrier profile and can select you. You can withdraw any time before they do.`}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setPendingInterest(null)}
                                disabled={interestProcessing}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={confirmExpressInterest}
                                disabled={interestProcessing}
                            >
                                {interestProcessing
                                    ? 'Sending…'
                                    : 'Express interest'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <DiscoveryFooter />
            </div>
        </>
    );
}
