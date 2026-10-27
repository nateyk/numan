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
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useToast } from '@/components/ui/toast';
import AppLayout from '@/layouts/app-layout';
import { freightRoute, routeLabel } from '@/lib/freight-route';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Calendar,
    CheckCircle2,
    ExternalLink,
    Eye,
    MapPin,
    Menu,
    MoreHorizontal,
    Package,
    Pencil,
    Search,
    Trash2,
    Truck,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface Freight {
    ulid: string;
    status: string;
    /**
     * A bare Eloquent model serialises these relations snake_cased. Read
     * through freightRoute() rather than picked apart, so the page works
     * whichever producer sent the row.
     */
    origin?: { name: string; region_name: string } | null;
    destination?: { name: string; region_name: string } | null;
    origin_location?: { name: string; region_name: string } | null;
    destination_location?: { name: string; region_name: string } | null;
    cargo_description: string | null;
    item_name: string | null;
    cargoType?: { name: string } | null;
    cargo_type?: { name: string } | null;
    truckType?: { name: string } | null;
    truck_type?: { name: string } | null;
    pickup_date: string | null;
    completion_score: number;
    interests_count?: number | null;
    hidden_at?: string | null;
}

interface Props {
    posts: {
        data: Freight[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: { status: string; search?: string | null };
    counts: Record<string, number>;
    hiddenCount?: number;
    canPost?: boolean;
    remainingLivePosts?: number;
    livePostLimit?: number;
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

export default function FreightIndex({
    posts,
    filters,
    counts,
    hiddenCount = 0,
    canPost = true,
    remainingLivePosts = -1,
    livePostLimit = -1,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { errors, flash, marketplace, profile_setup } = usePage<SharedData>()
        .props as unknown as {
        errors: Record<string, string>;
        flash?: { success?: string | null };
        marketplace?: { interests_enabled?: boolean };
        profile_setup?: { required?: boolean } | null;
    };
    const setupRequired = profile_setup?.required ?? false;
    const interestsEnabled = marketplace?.interests_enabled ?? false;
    // Open the limit dialog right away if the server just bounced a create
    // (e.g. the quota filled between page load and clicking Post).
    const [limitDialogOpen, setLimitDialogOpen] = useState(
        () => !canPost && Boolean(errors.freight),
    );
    const [deleteTarget, setDeleteTarget] = useState<{
        ulid: string;
        label: string;
    } | null>(null);
    const [deleting, setDeleting] = useState(false);
    const { addToast } = useToast();

    const confirmDelete = () => {
        if (!deleteTarget || deleting) return;
        setDeleting(true);
        router.delete(`/my-freight/${deleteTarget.ulid}`, {
            onSuccess: () => addToast('Freight post deleted.', 'success'),
            onError: () =>
                addToast(
                    'Could not delete this post. Please try again.',
                    'error',
                ),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const allowPost = () => {
        if (!canPost) {
            setLimitDialogOpen(true);
            return false;
        }
        return true;
    };
    const filterBy = (status: string) =>
        router.get(
            '/my-freight',
            { status, search: search || undefined },
            { preserveState: true },
        );
    const doSearch = () =>
        router.get(
            '/my-freight',
            { status: filters.status, search: search || undefined },
            { preserveState: true },
        );

    return (
        <AppLayout>
            <Head title="My Freight — Manage Posts" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash?.success && (
                    <div
                        role="status"
                        className="flex items-start gap-2 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-sm dark:border-green-800 dark:bg-green-950/40"
                    >
                        <CheckCircle2
                            className="mt-0.5 size-4 shrink-0 text-green-600 dark:text-green-400"
                            aria-hidden="true"
                        />
                        <p className="font-medium">{flash.success}</p>
                    </div>
                )}
                {hiddenCount > 0 && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                        <p className="font-medium">
                            {hiddenCount} post
                            {hiddenCount === 1 ? ' is' : 's are'} hidden because
                            your plan ended. They are still here — subscribe to
                            restore {hiddenCount === 1 ? 'it' : 'them'}{' '}
                            publicly.
                        </p>
                        <Button asChild size="sm" className="shrink-0">
                            <Link href="/billing/plans">Subscribe</Link>
                        </Button>
                    </div>
                )}
                {setupRequired && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-sky-300 bg-sky-50 px-4 py-3 text-sm dark:border-sky-800 dark:bg-sky-950/40">
                        <p className="flex items-center gap-2 font-medium">
                            <AlertTriangle className="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                            Posting needs a public profile so carriers know who
                            is asking.
                        </p>
                        <Button asChild size="sm" className="shrink-0">
                            <Link href="/public-profile">Finish profile</Link>
                        </Button>
                    </div>
                )}
                {!canPost && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                        <p className="flex items-center gap-2 font-medium">
                            <AlertTriangle className="size-4 shrink-0" />
                            Free plan limit reached — your live posts are at the
                            maximum ({livePostLimit}). Close a post or upgrade
                            to post more.
                        </p>
                        <Button asChild size="sm" className="shrink-0">
                            <Link href="/billing/plans">View plans</Link>
                        </Button>
                    </div>
                )}
                <div className="flex flex-col gap-1">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold tracking-tight">
                            My Freight
                        </h1>
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="md:hidden"
                                >
                                    <Menu className="size-4" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72">
                                <SheetHeader>
                                    <SheetTitle>My Freight Menu</SheetTitle>
                                </SheetHeader>
                                <div className="mt-6 space-y-4">
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">
                                            Filter by Status
                                        </p>
                                        <div className="grid gap-1">
                                            {STATUSES.map((s) => (
                                                <Button
                                                    key={s.value}
                                                    variant={
                                                        filters.status ===
                                                        s.value
                                                            ? 'default'
                                                            : 'ghost'
                                                    }
                                                    size="sm"
                                                    onClick={() =>
                                                        filterBy(s.value)
                                                    }
                                                    className="justify-between"
                                                >
                                                    {s.label}{' '}
                                                    <Badge variant="secondary">
                                                        {counts[s.value] ?? 0}
                                                    </Badge>
                                                </Button>
                                            ))}
                                        </div>
                                    </div>
                                    <PostFreightButton
                                        onBeforePost={allowPost}
                                        className="w-full"
                                    />
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
                    <p className="text-muted-foreground">
                        Manage your freight posts — track by status, search, and
                        act via dropdown.
                    </p>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search cargo, towns, ULID…"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && doSearch()}
                            className="pl-9"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Select
                            value={filters.status}
                            onValueChange={(v) => filterBy(v)}
                        >
                            <SelectTrigger className="hidden w-40 sm:flex">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label} ({counts[s.value] ?? 0})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <PostFreightButton
                            onBeforePost={allowPost}
                            className="hidden sm:flex"
                        />
                        <PostFreightButton
                            onBeforePost={allowPost}
                            size="icon"
                            className="sm:hidden"
                            label="Post Freight"
                        />
                    </div>
                </div>

                <div className="hidden flex-wrap gap-2 sm:flex">
                    {STATUSES.map((s) => (
                        <Button
                            key={s.value}
                            variant={
                                filters.status === s.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() => filterBy(s.value)}
                            className="h-7 px-2.5 text-xs"
                        >
                            {s.label}{' '}
                            <Badge
                                variant="secondary"
                                className="ml-1 px-1.5 py-0 text-[10px]"
                            >
                                {counts[s.value] ?? 0}
                            </Badge>
                        </Button>
                    ))}
                </div>
                <div className="-mx-4 px-4 sm:hidden">
                    <div className="flex snap-x snap-mandatory scrollbar-none gap-2 overflow-x-auto pb-2">
                        {STATUSES.map((s) => (
                            <Button
                                key={s.value}
                                variant={
                                    filters.status === s.value
                                        ? 'default'
                                        : 'outline'
                                }
                                size="sm"
                                onClick={() => filterBy(s.value)}
                                className="h-7 shrink-0 snap-start px-3 text-xs"
                            >
                                {s.label}{' '}
                                <span className="ml-1 text-[10px]">
                                    ({counts[s.value] ?? 0})
                                </span>
                            </Button>
                        ))}
                    </div>
                </div>

                {/* Desktop Table — hidden on mobile */}
                <Card className="hidden md:block">
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Route</TableHead>
                                        <TableHead>Cargo</TableHead>
                                        <TableHead>Truck</TableHead>
                                        <TableHead>Pickup</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {posts.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-24 text-center"
                                            >
                                                <div className="flex flex-col items-center gap-2 py-6">
                                                    <Package className="size-8 text-muted-foreground" />
                                                    <p className="text-sm font-medium">
                                                        No freight in this
                                                        filter
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        You have no{' '}
                                                        {filters.status !==
                                                        'all'
                                                            ? filters.status
                                                            : ''}{' '}
                                                        posts yet.
                                                    </p>
                                                    <PostFreightButton
                                                        onBeforePost={allowPost}
                                                        size="sm"
                                                        className="mt-2"
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        posts.data.map((p) => {
                                            const {
                                                origin,
                                                destination,
                                                cargoType,
                                                truckType,
                                            } = freightRoute(p);
                                            const isDraft =
                                                p.status === 'draft';
                                            // Mirrors FreightPostPolicy::delete for owners: drafts
                                            // and finished posts can go; live posts
                                            // (submitted / under review / active)
                                            // carry other users' interests.
                                            const canDelete = [
                                                'draft',
                                                'rejected',
                                                'closed',
                                                'expired',
                                            ].includes(p.status);
                                            return (
                                                <TableRow key={p.ulid}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2 font-medium">
                                                            <MapPin className="size-4 text-primary" />
                                                            <span>
                                                                {origin?.name ??
                                                                    '—'}
                                                            </span>
                                                            <ArrowRight className="size-3 text-muted-foreground" />
                                                            <span>
                                                                {destination?.name ??
                                                                    '—'}
                                                            </span>
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {origin?.region_name ??
                                                                ''}{' '}
                                                            →{' '}
                                                            {destination?.region_name ??
                                                                ''}{' '}
                                                            •{' '}
                                                            {p.ulid.slice(0, 8)}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <Package className="size-4 text-muted-foreground" />
                                                            <span className="font-medium">
                                                                {cargoType?.name ??
                                                                    'General'}
                                                            </span>
                                                        </div>
                                                        <div className="max-w-[160px] truncate text-xs text-muted-foreground">
                                                            {p.item_name ?? '—'}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Truck className="size-4 text-muted-foreground" />{' '}
                                                            {truckType?.name ??
                                                                'Any'}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Calendar className="size-4 text-muted-foreground" />{' '}
                                                            {p.pickup_date
                                                                ? String(
                                                                      p.pickup_date,
                                                                  ).slice(0, 10)
                                                                : '—'}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-wrap items-center gap-1">
                                                            <Badge
                                                                variant="secondary"
                                                                className={`${statusColor(p.status)} capitalize`}
                                                            >
                                                                {p.status.replace(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                            </Badge>
                                                            {p.hidden_at && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-amber-400 text-[10px] font-bold text-amber-700 dark:border-amber-700 dark:text-amber-400"
                                                                >
                                                                    Hidden —
                                                                    subscribe to
                                                                    restore
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex items-center justify-end gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={
                                                                        isDraft
                                                                            ? `/my-freight/${p.ulid}/route`
                                                                            : `/freight/${p.ulid}`
                                                                    }
                                                                >
                                                                    <Eye className="size-4" />
                                                                </Link>
                                                            </Button>
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger
                                                                    asChild
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7"
                                                                    >
                                                                        <MoreHorizontal className="size-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent
                                                                    align="end"
                                                                    className="w-48"
                                                                >
                                                                    <DropdownMenuItem
                                                                        asChild
                                                                    >
                                                                        <Link
                                                                            href={
                                                                                isDraft
                                                                                    ? `/my-freight/${p.ulid}/route`
                                                                                    : `/freight/${p.ulid}`
                                                                            }
                                                                            className="cursor-pointer"
                                                                        >
                                                                            <Eye className="mr-2 size-4" />{' '}
                                                                            View
                                                                            Details
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                    {isDraft && (
                                                                        <DropdownMenuItem
                                                                            asChild
                                                                        >
                                                                            <Link
                                                                                href={`/my-freight/${p.ulid}/route`}
                                                                                className="cursor-pointer"
                                                                            >
                                                                                <Pencil className="mr-2 size-4" />{' '}
                                                                                Edit
                                                                                Route
                                                                            </Link>
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    {isDraft && (
                                                                        <DropdownMenuItem
                                                                            asChild
                                                                        >
                                                                            <Link
                                                                                href={`/my-freight/${p.ulid}/cargo`}
                                                                                className="cursor-pointer"
                                                                            >
                                                                                <Package className="mr-2 size-4" />{' '}
                                                                                Edit
                                                                                Cargo
                                                                            </Link>
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    {isDraft && (
                                                                        <DropdownMenuItem
                                                                            asChild
                                                                        >
                                                                            <Link
                                                                                href={`/my-freight/${p.ulid}/review`}
                                                                                className="cursor-pointer"
                                                                            >
                                                                                <ExternalLink className="mr-2 size-4" />{' '}
                                                                                Review
                                                                                &
                                                                                Submit
                                                                            </Link>
                                                                        </DropdownMenuItem>
                                                                    )}
                                                                    {interestsEnabled &&
                                                                        !isDraft && (
                                                                            <DropdownMenuItem
                                                                                asChild
                                                                            >
                                                                                <Link
                                                                                    href={`/my-freight/${p.ulid}/interests`}
                                                                                    className="cursor-pointer"
                                                                                >
                                                                                    <Users className="mr-2 size-4" />{' '}
                                                                                    Interests
                                                                                    (
                                                                                    {p.interests_count ??
                                                                                        ''}

                                                                                    )
                                                                                </Link>
                                                                            </DropdownMenuItem>
                                                                        )}
                                                                    <DropdownMenuSeparator />
                                                                    {canDelete && (
                                                                        <DropdownMenuItem
                                                                            className="cursor-pointer text-destructive"
                                                                            onSelect={(
                                                                                e,
                                                                            ) => {
                                                                                e.preventDefault();
                                                                                setDeleteTarget(
                                                                                    {
                                                                                        ulid: p.ulid,
                                                                                        label: routeLabel(
                                                                                            p,
                                                                                        ),
                                                                                    },
                                                                                );
                                                                            }}
                                                                        >
                                                                            <Trash2 className="mr-2 size-4" />{' '}
                                                                            Delete
                                                                        </DropdownMenuItem>
                                                                    )}
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
                        </div>
                    </CardContent>
                </Card>

                {/* Mobile Cards — visible on mobile only */}
                <div className="grid gap-3 md:hidden">
                    {posts.data.length === 0 ? (
                        <Card className="border-dashed">
                            <CardContent className="flex flex-col items-center gap-3 py-8 text-center">
                                <Package className="size-8 text-muted-foreground" />
                                <p className="text-sm font-medium">
                                    No freight in this filter
                                </p>
                                <PostFreightButton
                                    onBeforePost={allowPost}
                                    size="sm"
                                />
                            </CardContent>
                        </Card>
                    ) : (
                        posts.data.map((p) => {
                            const { origin, destination, cargoType } =
                                freightRoute(p);
                            const isDraft = p.status === 'draft';
                            // Same rule as the desktop table and the policy.
                            const canDelete = [
                                'draft',
                                'rejected',
                                'closed',
                                'expired',
                            ].includes(p.status);
                            return (
                                <Card key={p.ulid} className="p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex items-center gap-2 font-medium">
                                            <MapPin className="size-4 text-primary" />
                                            <span>{origin?.name ?? '—'}</span>
                                            <ArrowRight className="size-3 text-muted-foreground" />
                                            <span>
                                                {destination?.name ?? '—'}
                                            </span>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            className={`${statusColor(p.status)} text-xs capitalize`}
                                        >
                                            {p.status.replace('_', ' ')}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                        <Badge variant="secondary">
                                            <Package className="mr-1 size-3" />
                                            {cargoType?.name ?? 'General'}
                                        </Badge>
                                        <Badge variant="secondary">
                                            <Calendar className="mr-1 size-3" />
                                            {p.pickup_date
                                                ? String(p.pickup_date).slice(
                                                      0,
                                                      10,
                                                  )
                                                : '—'}
                                        </Badge>
                                        {/* The desktop table carries this badge
                                            too. Without it here, the banner says
                                            two posts are hidden and the cards
                                            below give no way to tell which. */}
                                        {p.hidden_at && (
                                            <Badge
                                                variant="outline"
                                                className="border-amber-400 text-[10px] font-bold text-amber-700 dark:border-amber-700 dark:text-amber-400"
                                            >
                                                Hidden — subscribe to restore
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="mt-3 flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            className="h-8 flex-1"
                                        >
                                            <Link
                                                href={
                                                    isDraft
                                                        ? `/my-freight/${p.ulid}/route`
                                                        : `/freight/${p.ulid}`
                                                }
                                            >
                                                View
                                            </Link>
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8"
                                                >
                                                    <MoreHorizontal className="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent
                                                align="end"
                                                className="w-48"
                                            >
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={
                                                            isDraft
                                                                ? `/my-freight/${p.ulid}/route`
                                                                : `/freight/${p.ulid}`
                                                        }
                                                    >
                                                        <Eye className="mr-2 size-4" />{' '}
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                {isDraft && (
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/my-freight/${p.ulid}/cargo`}
                                                        >
                                                            <Pencil className="mr-2 size-4" />{' '}
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                {canDelete && (
                                                    <DropdownMenuItem
                                                        className="text-destructive"
                                                        onSelect={(e) => {
                                                            e.preventDefault();
                                                            setDeleteTarget({
                                                                ulid: p.ulid,
                                                                label: `${origin?.name ?? 'Origin'} → ${destination?.name ?? 'Destination'}`,
                                                            });
                                                        }}
                                                    >
                                                        <Trash2 className="mr-2 size-4" />{' '}
                                                        Delete
                                                    </DropdownMenuItem>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </Card>
                            );
                        })
                    )}
                </div>

                <div className="text-sm text-muted-foreground">
                    {posts.total
                        ? `${posts.total} posts`
                        : `${posts.data.length} posts`}{' '}
                    {posts.from && posts.to
                        ? `• ${posts.from}–${posts.to}`
                        : ''}{' '}
                    •{' '}
                    {filters.status !== 'all'
                        ? `Filtered by ${filters.status}`
                        : 'All statuses'}
                </div>

                {posts.links && posts.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {posts.links.map((link, i) => (
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
                                className="h-7 px-2.5 text-xs"
                            >
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            </Button>
                        ))}
                    </div>
                )}
                <Dialog
                    open={limitDialogOpen}
                    onOpenChange={setLimitDialogOpen}
                >
                    {' '}
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <AlertTriangle className="size-5 text-amber-600" />
                                Post limit reached
                            </DialogTitle>
                            <DialogDescription>
                                {errors.freight ??
                                    'Your Free plan allows a limited number of live freight posts.'}{' '}
                                You are using{' '}
                                {livePostLimit - remainingLivePosts} of{' '}
                                {livePostLimit} live{' '}
                                {livePostLimit === 1 ? 'post' : 'posts'}. Close
                                a delivered post, wait for one to expire, or
                                upgrade your plan to post more.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setLimitDialogOpen(false)}
                            >
                                Got it
                            </Button>
                            <Button asChild>
                                <Link href="/billing/plans">View plans</Link>
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Dialog
                    open={deleteTarget !== null}
                    onOpenChange={(open) => {
                        if (!open) setDeleteTarget(null);
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Trash2 className="size-5 text-destructive" />
                                Delete freight post?
                            </DialogTitle>
                            <DialogDescription>
                                {deleteTarget?.label ?? 'This post'} will be
                                permanently removed. This cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="outline"
                                disabled={deleting}
                                onClick={() => setDeleteTarget(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={deleting}
                                onClick={confirmDelete}
                            >
                                {deleting ? 'Deleting…' : 'Delete'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
