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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    Package,
    Search,
    ShieldQuestion,
    Truck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';

interface ProfileRow {
    id: number;
    slug: string;
    display_name: string;
    legal_name: string | null;
    type: 'consignor' | 'transporter' | 'both';
    account_type: string | null;
    email: string | null;
    user_name: string | null;
    posts_freight: boolean;
    provides_transport: boolean;
    status: string | null;
    verification_status: string | null;
    primary_location: string | null;
    updated_at: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    profiles: {
        data: ProfileRow[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: {
        all: number;
        consignor: number;
        transporter: number;
        both: number;
        pending_verification: number;
    };
    filters: {
        type: string;
        listing_status: string;
        verification_status: string;
        search: string;
    };
}

const TYPE_CONFIG: Record<
    ProfileRow['type'],
    { label: string; className: string; icon: typeof Package }
> = {
    consignor: {
        label: 'Consignor',
        className: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        icon: Package,
    },
    transporter: {
        label: 'Transport carrier',
        className:
            'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
        icon: Truck,
    },
    both: {
        label: 'Consignor and carrier',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        icon: Building2,
    },
};

const LISTING_CONFIG: Record<string, { label: string; className: string }> = {
    draft: {
        label: 'Draft',
        className: 'bg-muted text-muted-foreground',
    },
    pending: {
        label: 'Pending',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    approved: {
        label: 'Approved',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    rejected: {
        label: 'Rejected',
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

const VERIFICATION_CONFIG: Record<string, { label: string; className: string }> = {
    unverified: {
        label: 'Unverified',
        className: 'bg-muted text-muted-foreground',
    },
    pending: {
        label: 'Pending',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    verified: {
        label: 'Verified',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    },
    rejected: {
        label: 'Rejected',
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

export default function AdminProfilesIndex({ profiles, counts, filters }: Props) {
    const flash = usePage<SharedData>().props.flash;
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (overrides: Record<string, string | undefined> = {}) => {
        router.get(
            '/admin/profiles',
            {
                type: filters.type,
                listing_status: filters.listing_status,
                verification_status: filters.verification_status,
                search: filters.search,
                ...overrides,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleSearch = (event: React.FormEvent) => {
        event.preventDefault();
        visit({ search });
    };

    const typeTabs: { value: string; label: string }[] = [
        { value: 'all', label: `All (${counts.all})` },
        { value: 'consignor', label: `Consignors (${counts.consignor})` },
        { value: 'transporter', label: `Carriers (${counts.transporter})` },
        { value: 'both', label: `Both (${counts.both})` },
    ];

    return (
        <AdminLayout>
            <Head title="Public profiles" />

            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <UserRound className="size-6" />
                            Public profiles
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Every consignor, carrier, and business on the
                            marketplace.
                        </p>
                    </div>

                    <Badge variant="outline" className="text-sm">
                        <ShieldQuestion className="mr-1 size-3.5" />
                        {counts.pending_verification} awaiting verification
                    </Badge>
                </div>

                {flash?.success && (
                    <div className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-900/30">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30">
                        {flash.error}
                    </div>
                )}

                <div className="flex gap-1 overflow-x-auto border-b">
                    {typeTabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => visit({ type: tab.value })}
                            className={`border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors ${
                                filters.type === tab.value
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={handleSearch}
                        className="flex w-full max-w-md items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search name, legal name, or email…"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                className="pl-9"
                            />
                        </div>
                        <Button type="submit" size="sm">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={filters.listing_status || 'all'}
                        onValueChange={(value) =>
                            visit({ listing_status: value })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Listing status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Any listing</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.verification_status || 'all'}
                        onValueChange={(value) =>
                            visit({ verification_status: value })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Verification" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Any verification
                            </SelectItem>
                            <SelectItem value="unverified">
                                Unverified
                            </SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="verified">Verified</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-md border bg-card text-card-foreground shadow">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Profile</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Verification</TableHead>
                                <TableHead>Listing</TableHead>
                                <TableHead>Base</TableHead>
                                <TableHead>Updated</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {profiles.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="px-6 py-12 text-center text-muted-foreground"
                                    >
                                        No profiles match these filters.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                profiles.data.map((profile) => {
                                    const type = TYPE_CONFIG[profile.type];
                                    const TypeIcon = type.icon;
                                    const listing = profile.status
                                        ? LISTING_CONFIG[profile.status]
                                        : null;
                                    const verification =
                                        VERIFICATION_CONFIG[
                                            profile.verification_status ??
                                                'unverified'
                                        ] ?? VERIFICATION_CONFIG.unverified;

                                    return (
                                        <TableRow
                                            key={profile.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <TableCell>
                                                <Link
                                                    href={`/admin/profiles/${profile.slug}`}
                                                    className="group block"
                                                >
                                                    <span className="font-medium group-hover:underline">
                                                        {profile.display_name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {profile.legal_name ??
                                                            profile.email}
                                                    </span>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={type.className}
                                                >
                                                    <TypeIcon className="mr-1 size-3.5" />
                                                    {type.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        verification.className
                                                    }
                                                >
                                                    {profile.verification_status ===
                                                    'verified' ? (
                                                        <BadgeCheck className="mr-1 size-3.5" />
                                                    ) : (
                                                        <ShieldQuestion className="mr-1 size-3.5" />
                                                    )}
                                                    {verification.label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {listing ? (
                                                    <Badge
                                                        className={
                                                            listing.className
                                                        }
                                                    >
                                                        {listing.label}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        No carrier listing
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {profile.primary_location ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {formatDate(profile.updated_at)}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {profiles.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            Page {profiles.current_page} of{' '}
                            {profiles.last_page} · {profiles.total} profiles
                        </p>
                        <div className="flex flex-wrap gap-1">
                            {profiles.links.map((link, index) => (
                                <Button
                                    key={`${link.label}-${index}`}
                                    variant={link.active ? 'default' : 'outline'}
                                    size="sm"
                                    disabled={link.url === null}
                                    asChild={link.url !== null}
                                >
                                    {link.url !== null ? (
                                        <Link
                                            href={link.url}
                                            preserveScroll
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    ) : (
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
