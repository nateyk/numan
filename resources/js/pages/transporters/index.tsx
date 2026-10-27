import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/react';
import {
    BadgeCheck,
    FileCheck2,
    MapPin,
    Package,
    Search,
    Truck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface PublicProfile {
    id: number;
    slug: string;
    display_name: string;
    account_type: string | null;
    status: string | null;
    verification_status: string | null;
    is_verified: boolean;
    posts_freight: boolean;
    provides_transport: boolean;
    business_description: string | null;
    primary_location: {
        id: number;
        name: string;
        region_name: string | null;
    } | null;
    member_since: string | null;
    truck_types: { id: number; name: string; slug: string }[];
    routes: { id: number; origin: string | null; destination: string | null }[];
    has_documents: boolean;
    documents_reviewed: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Filters {
    q: string | null;
    truck_type: string | null;
    location: string | null;
}

interface Props {
    // Explicit resource fields only: no phone, whatsapp, email, legal_name or
    // document names ever reach this page.
    profiles: {
        data: PublicProfile[];
    };
    total: number;
    links: PaginationLink[];
    truckTypes: { id: number; name: string; slug: string }[];
    filters: Filters;
}

function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&');
}

export default function TransportersIndex({
    profiles,
    total,
    links,
    truckTypes,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.q ?? '');
    const [truckTypeFilter, setTruckTypeFilter] = useState(
        filters.truck_type ?? '',
    );
    const [locationFilter, setLocationFilter] = useState(
        filters.location ?? '',
    );

    const handleFilterChange = () => {
        router.get(
            '/transporters',
            {
                q: search || undefined,
                truck_type: truckTypeFilter || undefined,
                location: locationFilter || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setTruckTypeFilter('');
        setLocationFilter('');
        router.get('/transporters', {}, { preserveState: true, replace: true });
    };

    const hasFilters = Boolean(search || truckTypeFilter || locationFilter);

    return (
        <>
            <Head title="Carriers — Numan Freight" />

            <div className="min-h-screen bg-background text-foreground">
                <DiscoveryHeader />

                <main>
                    <div className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                        <div className="flex flex-col gap-2">
                            <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                                <Truck className="size-6" />
                                Carriers
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Approved transport providers on Numan Freight.
                                {total > 0 &&
                                    ` ${total} ${total === 1 ? 'carrier' : 'carriers'} available.`}
                            </p>
                        </div>

                        <div className="mt-6 flex flex-col gap-4 rounded-lg border bg-card p-4 md:flex-row md:items-end">
                            <div className="flex-1">
                                <label
                                    htmlFor="carrier-search"
                                    className="text-sm font-medium"
                                >
                                    Search
                                </label>
                                <div className="relative mt-1">
                                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        id="carrier-search"
                                        placeholder="Search carriers…"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                handleFilterChange();
                                            }
                                        }}
                                        className="pl-8"
                                    />
                                </div>
                            </div>

                            <div className="w-full md:w-48">
                                <label className="text-sm font-medium">
                                    Truck type
                                </label>
                                <Select
                                    value={truckTypeFilter}
                                    onValueChange={(value) =>
                                        setTruckTypeFilter(value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {truckTypes.map((type) => (
                                            <SelectItem
                                                key={type.id}
                                                value={type.slug}
                                            >
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-full md:w-48">
                                <label
                                    htmlFor="carrier-location"
                                    className="text-sm font-medium"
                                >
                                    Location
                                </label>
                                <Input
                                    id="carrier-location"
                                    placeholder="City or region"
                                    value={locationFilter}
                                    onChange={(e) =>
                                        setLocationFilter(e.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            handleFilterChange();
                                        }
                                    }}
                                    className="mt-1"
                                />
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    onClick={handleFilterChange}
                                    size="sm"
                                >
                                    <Search className="mr-1.5 size-4" />
                                    Filter
                                </Button>
                                {hasFilters && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        <XCircle className="mr-1.5 size-4" />
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </div>

                        {profiles.data.length === 0 ? (
                            <div className="mt-8 flex flex-col items-center justify-center rounded-lg border bg-card p-12 text-center text-muted-foreground">
                                <Package className="mb-4 size-12" />
                                <p className="text-lg font-medium">
                                    No carriers found
                                </p>
                                <p className="mt-1 text-sm">
                                    Try adjusting your filters or search terms.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    {profiles.data.map((profile) => (
                                        <Card
                                            key={profile.id}
                                            className="flex flex-col transition-shadow hover:shadow-md"
                                        >
                                            <CardContent className="flex flex-1 flex-col p-5">
                                                <div className="flex items-start justify-between gap-3">
                                                    <h2 className="min-w-0 text-lg font-semibold">
                                                        {profile.display_name}
                                                    </h2>
                                                    {profile.is_verified && (
                                                        <Badge className="shrink-0 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                            <BadgeCheck className="mr-1 size-3.5" />
                                                            Verified
                                                        </Badge>
                                                    )}
                                                </div>

                                                {profile.business_description && (
                                                    <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">
                                                        {
                                                            profile.business_description
                                                        }
                                                    </p>
                                                )}

                                                {profile.primary_location && (
                                                    <div className="mt-3 flex items-center gap-1.5 text-sm text-muted-foreground">
                                                        <MapPin className="size-4 shrink-0" />
                                                        <span>
                                                            {
                                                                profile
                                                                    .primary_location
                                                                    .name
                                                            }
                                                            {profile
                                                                .primary_location
                                                                .region_name && (
                                                                <>
                                                                    {', '}
                                                                    {
                                                                        profile
                                                                            .primary_location
                                                                            .region_name
                                                                    }
                                                                </>
                                                            )}
                                                        </span>
                                                    </div>
                                                )}

                                                {profile.truck_types.length >
                                                    0 && (
                                                    <div className="mt-3 flex flex-wrap gap-1.5">
                                                        {profile.truck_types.map(
                                                            (type) => (
                                                                <Badge
                                                                    key={
                                                                        type.id
                                                                    }
                                                                    variant="outline"
                                                                    className="text-[10px]"
                                                                >
                                                                    <Truck className="mr-1 size-3" />
                                                                    {type.name}
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                )}

                                                {profile.routes.length > 0 && (
                                                    <p className="mt-3 line-clamp-1 text-xs text-muted-foreground">
                                                        {profile.routes
                                                            .slice(0, 2)
                                                            .map(
                                                                (route) =>
                                                                    `${route.origin ?? '?'} → ${route.destination ?? '?'}`,
                                                            )
                                                            .join(' · ')}
                                                    </p>
                                                )}

                                                <div className="mt-auto flex items-center justify-between pt-4">
                                                    {profile.documents_reviewed ? (
                                                        <span className="flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400">
                                                            <FileCheck2 className="size-3.5" />
                                                            Documents reviewed
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            {profile.has_documents
                                                                ? 'Documents on file'
                                                                : 'No documents'}
                                                        </span>
                                                    )}

                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/p/${profile.slug}`}
                                                        >
                                                            View profile
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>

                                {links.length > 3 && (
                                    <div className="mt-6 flex flex-wrap items-center justify-center gap-1">
                                        {links.map((link, index) => (
                                            <Button
                                                key={index}
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                disabled={!link.url}
                                                onClick={() =>
                                                    link.url &&
                                                    router.get(
                                                        link.url,
                                                        {},
                                                        {
                                                            preserveState: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {decodePaginationLabel(
                                                    link.label,
                                                )}
                                            </Button>
                                        ))}
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </main>

                <DiscoveryFooter />
            </div>
        </>
    );
}
