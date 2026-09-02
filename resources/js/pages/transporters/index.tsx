import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    MapPin,
    Package,
    Search,
    Truck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface TransporterProfile {
    id: number;
    slug: string;
    display_name: string;
    legal_name: string;
    phone: string;
    whatsapp: string;
    email: string;
    business_description: string;
    is_company: boolean;
    status: string;
    verification_level: string;
    completion_score: number;
    primary_location: {
        name: string;
        region_name: string;
    } | null;
    truck_types: { name: string; slug: string }[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Filters {
    truck_type: string;
    location: string;
    search: string;
}

interface Props {
    profiles: {
        data: TransporterProfile[];
        links: PaginationLink[];
        total: number;
        last_page: number;
    };
    truckTypes: { id: number; name: string; slug: string }[];
    filters: Filters;
}

const STATUS_CONFIG: Record<
    string,
    { label: string; className: string }
> = {
    approved: {
        label: 'Approved',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    pending: {
        label: 'Pending',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    rejected: {
        label: 'Rejected',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
    verified: {
        label: 'Verified',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    },
};

function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&');
}

export default function TransportersIndex({ profiles, truckTypes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [truckTypeFilter, setTruckTypeFilter] = useState(filters.truck_type ?? '');
    const [locationFilter, setLocationFilter] = useState(filters.location ?? '');

    const handleFilterChange = () => {
        router.get(
            '/transporters',
            {
                truck_type: truckTypeFilter || undefined,
                location: locationFilter || undefined,
                search: search || undefined,
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

    return (
        <AppLayout>
            <Head title="Transporters" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                        <Truck className="h-6 w-6" />
                        Approved Transporters
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Browse verified transporter profiles and their services
                    </p>
                </div>

                {/* Filters */}
                <div className="flex flex-col gap-4 rounded-md border bg-card p-4 md:flex-row md:items-end">
                    <div className="flex-1">
                        <label className="text-sm font-medium">Search</label>
                        <div className="relative mt-1">
                            <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search transporters…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-8"
                            />
                        </div>
                    </div>
                    <div className="w-full md:w-48">
                        <label className="text-sm font-medium">
                            Truck Type
                        </label>
                        <Select
                            value={truckTypeFilter}
                            onValueChange={(v) => setTruckTypeFilter(v)}
                        >
                            <SelectTrigger className="mt-1">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                {truckTypes.map((t) => (
                                    <SelectItem key={t.id} value={t.slug}>
                                        {t.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="w-full md:w-48">
                        <label className="text-sm font-medium">
                            Location
                        </label>
                        <Input
                            placeholder="City or region"
                            value={locationFilter}
                            onChange={(e) => setLocationFilter(e.target.value)}
                            className="mt-1"
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={handleFilterChange} size="sm">
                            <Search className="mr-1.5 h-4 w-4" />
                            Filter
                        </Button>
                        {(truckTypeFilter || locationFilter || search) && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={clearFilters}
                            >
                                <XCircle className="mr-1.5 h-4 w-4" />
                                Clear
                            </Button>
                        )}
                    </div>
                </div>

                {/* Transporters Grid */}
                {profiles.data.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center rounded-md border bg-card p-12 text-center text-muted-foreground">
                        <Package className="mb-4 h-12 w-12 text-muted-foreground" />
                        <p className="text-lg font-medium">No transporters found</p>
                        <p className="mt-1 text-sm">
                            Try adjusting your filters or search terms
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {profiles.data.map((profile) => {
                                const statusConfig =
                                    STATUS_CONFIG[profile.status] ||
                                    STATUS_CONFIG.pending;
                                return (
                                    <div
                                        key={profile.id}
                                        className="rounded-lg border bg-card p-5 transition-shadow hover:shadow-md"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0 flex-1">
                                                <h3 className="text-lg font-semibold">
                                                    {profile.display_name}
                                                </h3>
                                                <p className="mt-0.5 text-sm text-muted-foreground">
                                                    {profile.legal_name}
                                                </p>
                                            </div>
                                            <Badge
                                                className={statusConfig.className}
                                            >
                                                {statusConfig.label}
                                            </Badge>
                                        </div>

                                        {profile.business_description && (
                                            <p className="mt-3 line-clamp-2 text-sm text-muted-foreground">
                                                {profile.business_description}
                                            </p>
                                        )}

                                        {profile.primary_location && (
                                            <div className="mt-3 flex items-center gap-1.5 text-sm text-muted-foreground">
                                                <MapPin className="h-4 w-4 shrink-0" />
                                                <span>
                                                    {profile.primary_location.name}
                                                    ,{' '}
                                                    {profile.primary_location.region_name}
                                                </span>
                                            </div>
                                        )}

                                        {profile.truck_types.length > 0 && (
                                            <div className="mt-3 flex flex-wrap gap-1.5">
                                                {profile.truck_types.map(
                                                    (t) => (
                                                        <Badge
                                                            key={t.slug}
                                                            variant="outline"
                                                            className="text-[10px]"
                                                        >
                                                            <Truck className="mr-1 h-3 w-3" />
                                                            {t.name}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}

                                        <div className="mt-4 flex items-center justify-between">
                                            {profile.verification_level ===
                                                'verified' && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-[10px]"
                                                >
                                                    Verified
                                                </Badge>
                                            )}
                                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                <Package className="h-3 w-3" />
                                                {profile.completion_score}%
                                                complete
                                            </div>
                                        </div>

                                        <div className="mt-4">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                onClick={() =>
                                                    router.get(
                                                        `/transporters/${profile.slug}`,
                                                    )
                                                }
                                            >
                                                View Profile
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Pagination */}
                        {profiles.last_page > 1 && (
                            <div className="mt-6 flex items-center justify-center gap-1">
                                {profiles.links.map((link, i) => (
                                    <Button
                                        key={i}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() =>
                                            link.url &&
                                            router.get(
                                                link.url,
                                                {},
                                                { preserveState: true },
                                            )
                                        }
                                    >
                                        {decodePaginationLabel(link.label)}
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