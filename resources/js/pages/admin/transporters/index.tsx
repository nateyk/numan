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
import { Head, router, usePage } from '@inertiajs/react';
import {
    Building2,
    CheckCircle,
    Clock,
    Mail,
    Search,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { type SharedData } from '@/types';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface TransporterProfile {
    id: number;
    slug: string;
    display_name: string;
    legal_name: string | null;
    email: string;
    status: string;
    verification_level: string;
    completion_score: number;
    phone: string | null;
    whatsapp: string | null;
    is_company: boolean;
    created_at: string;
    updated_at: string;
}

interface Counts {
    all: number;
    pending: number;
    approved: number;
    rejected: number;
}

interface Filters {
    status: string;
    search: string;
}

interface Props {
    profiles: {
        data: TransporterProfile[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    counts: Counts;
    filters: Filters;
}

const STATUS_CONFIG: Record<
    string,
    { label: string; className: string; icon: typeof CheckCircle }
> = {
    pending: {
        label: 'Pending',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        icon: Clock,
    },
    approved: {
        label: 'Approved',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        icon: CheckCircle,
    },
    rejected: {
        label: 'Rejected',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: XCircle,
    },
    draft: {
        label: 'Draft',
        className: 'bg-muted text-muted-foreground',
        icon: Clock,
    },
};

const VERIFICATION_CONFIG: Record<
    string,
    { label: string; className: string }
> = {
    unverified: {
        label: 'Unverified',
        className: 'bg-muted text-muted-foreground',
    },
    verified: {
        label: 'Verified',
        className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
};

export default function AdminTransporters({
    profiles,
    counts,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const flash = usePage<SharedData>().props.flash;

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/transporters',
            { search, status: filters.status !== 'all' ? filters.status : undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleStatusChange = (status: string) => {
        router.get(
            '/admin/transporters',
            status !== 'all' ? { status, search } : { search },
            { preserveState: true, replace: true },
        );
    };

    const statusTabs = [
        { value: 'all', label: `All (${counts.all})` },
        { value: 'pending', label: `Pending (${counts.pending})` },
        { value: 'approved', label: `Approved (${counts.approved})` },
        { value: 'rejected', label: `Rejected (${counts.rejected})` },
    ];

    return (
        <AdminLayout>
            <Head title="Transporter Profiles" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Building2 className="h-6 w-6" />
                            Transporter Profiles
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Review and moderate transporter applications
                        </p>
                    </div>
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

                {/* Status Filter Tabs */}
                <div className="flex gap-1 border-b">
                    {statusTabs.map((tab) => (
                        <button
                            key={tab.value}
                            onClick={() => handleStatusChange(tab.value)}
                            className={`border-b-2 px-4 py-2 text-sm font-medium transition-colors ${
                                filters.status === tab.value
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Search */}
                <form onSubmit={handleSearch} className="flex items-center gap-2 max-w-md">
                    <Search className="absolute left-3 h-4 w-4 text-muted-foreground" />
                    <Input
                        type="text"
                        placeholder="Search by name, email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="pl-9"
                    />
                    <Button type="submit" size="sm">
                        Search
                    </Button>
                </form>

                {/* Profiles Table */}
                <div className="overflow-hidden rounded-md border bg-card text-card-foreground shadow">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Transporter</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Verification</TableHead>
                                <TableHead>Completion</TableHead>
                                <TableHead className="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {profiles.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="px-6 py-12 text-center text-muted-foreground"
                                    >
                                        No transporter profiles found.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                profiles.data.map((profile: TransporterProfile) => {
                                    const statusConfig =
                                        STATUS_CONFIG[profile.status] ||
                                        STATUS_CONFIG.pending;
                                    const StatusIcon = statusConfig.icon;
                                    const verificationConfig =
                                        VERIFICATION_CONFIG[profile.verification_level] ||
                                        VERIFICATION_CONFIG.unverified;

                                    return (
                                        <TableRow
                                            key={profile.id}
                                            className="hover:bg-muted/50"
                                        >
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-9 w-9 items-center justify-center rounded-full bg-muted">
                                                        <User className="h-4 w-4 text-muted-foreground" />
                                                    </div>
                                                    <div>
                                                        <div className="font-medium">
                                                            {profile.display_name}
                                                        </div>
                                                        {profile.legal_name && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {profile.legal_name}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                <div className="flex items-center gap-1.5">
                                                    <Mail className="h-3.5 w-3.5" />
                                                    {profile.email}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${statusConfig.className}`}
                                                >
                                                    <StatusIcon className="h-2.5 w-2.5" />
                                                    {statusConfig.label}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${verificationConfig.className}`}
                                                >
                                                    {verificationConfig.label}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-24 rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all"
                                                            style={{
                                                                width: `${profile.completion_score}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">
                                                        {profile.completion_score}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.get(
                                                            `/admin/transporters/${profile.slug}`,
                                                        )
                                                    }
                                                >
                                                    View
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pagination */}
                {profiles.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {profiles.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
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
                                dangerouslySetInnerHTML={{
                                    __html: link.label
                                        .replace(/&laquo;/g, '«')
                                        .replace(/&raquo;/g, '»')
                                        .replace(/&amp;/g, '&'),
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}