import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import AppLayout from '@/layouts/app-layout';
import {
    freightRoute,
    nestedFreightPost,
    postUlid,
    type FreightRow,
} from '@/lib/freight-route';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    MapPin,
    MoreHorizontal,
    Package,
    X,
} from 'lucide-react';

interface Interest {
    id: number;
    status: string;
    message: string | null;
    created_at?: string | null;
    /**
     * The post is read through nestedFreightPost() and freightRoute(), so the
     * several shapes the resources use for it (freightPost / freight_post,
     * origin as an object or as a bare name) are handled in one place rather
     * than guessed at per page.
     */
    freightPost?: FreightRow;
    freight_post?: FreightRow;
}

interface Props {
    // Explicit resource array. Contact details are never included; they come
    // from the reveal action only.
    interests: Interest[];
    total?: number;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    canReveal?: Record<string, boolean>;
    revealedContacts?: Record<
        string,
        {
            name: string;
            phone: string | null;
            email: string | null;
            whatsapp: string | null;
        }
    >;
    reveal_blocked?: { limit: number; used: number } | null;
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

export default function MyInterests({
    interests,
    links,
    canReveal,
    revealedContacts,
    reveal_blocked,
    sort,
    counts,
}: Props) {
    const safeCounts = (
        counts && typeof counts === 'object' && !Array.isArray(counts)
            ? counts
            : {}
    ) as Record<string, number>;
    const safeSort = typeof sort === 'string' && sort ? sort : 'latest';
    const interestData = Array.isArray(interests) ? interests : [];

    return (
        <AppLayout>
            <Head title="My Interests — Manage" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        My Interests
                    </h1>
                    <p className="text-muted-foreground">
                        Track your expressed interests — submitted, shortlisted,
                        and selected freight.
                    </p>
                </div>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    {reveal_blocked && (
                        <div className="flex flex-col gap-2 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                            <p className="text-sm">
                                You have used all {reveal_blocked.limit} free
                                contact reveals. Your free allowance is
                                one-time; subscribing makes reveals unlimited.
                            </p>
                            <div>
                                <Button size="sm" asChild>
                                    <Link href="/billing/plans">
                                        Subscribe for unlimited reveals
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Badge variant="secondary" className="px-2.5 py-1">
                            All: {safeCounts.all ?? interestData.length}
                        </Badge>
                        <Badge
                            variant="outline"
                            className={statusColor('submitted')}
                        >
                            Submitted: {safeCounts.submitted ?? 0}
                        </Badge>
                        <Badge
                            variant="outline"
                            className={statusColor('shortlisted')}
                        >
                            Shortlisted: {safeCounts.shortlisted ?? 0}
                        </Badge>
                        <Badge
                            variant="outline"
                            className={statusColor('selected')}
                        >
                            Selected: {safeCounts.selected ?? 0}
                        </Badge>
                    </div>
                    <Select
                        value={safeSort}
                        onValueChange={(v) =>
                            router.get(
                                '/my-interests',
                                { sort: v },
                                { preserveState: true },
                            )
                        }
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Sort" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="latest">Latest</SelectItem>
                            <SelectItem value="oldest">Oldest</SelectItem>
                            <SelectItem value="status">Status</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {interestData.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Package className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold">No interests yet</h3>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Browse freight at{' '}
                                <Link
                                    href="/freight"
                                    className="text-primary hover:underline"
                                >
                                    Discover Freight
                                </Link>{' '}
                                and express interest. Your contact will be
                                shared only when shortlisted.
                            </p>
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
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {interestData.map((i) => {
                                            const fp = nestedFreightPost(i);
                                            const ulid = postUlid(fp);
                                            const {
                                                origin,
                                                destination,
                                                cargoType,
                                            } = freightRoute(fp);
                                            if (!ulid) {
                                                return (
                                                    <TableRow
                                                        key={i.id}
                                                        className="opacity-60"
                                                    >
                                                        <TableCell colSpan={4}>
                                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                                <Package className="size-4" />{' '}
                                                                Freight
                                                                unavailable
                                                                (removed) —
                                                                Interest #{i.id}{' '}
                                                                • {i.status}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            }
                                            return (
                                                <TableRow key={i.id}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2 font-medium">
                                                            <MapPin className="size-4 text-primary" />{' '}
                                                            {origin?.name ??
                                                                '—'}{' '}
                                                            <ArrowRight className="size-3 text-muted-foreground" />{' '}
                                                            {destination?.name ??
                                                                '—'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {ulid.slice(0, 8)} •{' '}
                                                            {cargoType?.name ??
                                                                'General'}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="text-sm">
                                                            {i.message ?? '—'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {i.created_at
                                                                ? new Date(
                                                                      i.created_at,
                                                                  ).toLocaleDateString()
                                                                : ''}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="secondary"
                                                            className={`${statusColor(i.status)} capitalize`}
                                                        >
                                                            {i.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex flex-col items-end gap-1">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={`/freight/${ulid}`}
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
                                                                        className="w-40"
                                                                    >
                                                                        <DropdownMenuItem
                                                                            asChild
                                                                        >
                                                                            <Link
                                                                                href={`/freight/${ulid}`}
                                                                                className="cursor-pointer"
                                                                            >
                                                                                <Eye className="mr-2 size-4" />{' '}
                                                                                View
                                                                                Freight
                                                                            </Link>
                                                                        </DropdownMenuItem>
                                                                        <DropdownMenuItem
                                                                            onClick={() =>
                                                                                router.post(
                                                                                    `/interests/${i.id}/withdraw`,
                                                                                )
                                                                            }
                                                                            disabled={
                                                                                i.status ===
                                                                                    'withdrawn' ||
                                                                                i.status ===
                                                                                    'selected'
                                                                            }
                                                                            className="cursor-pointer"
                                                                        >
                                                                            <X className="mr-2 size-4" />{' '}
                                                                            Withdraw
                                                                        </DropdownMenuItem>
                                                                    </DropdownMenuContent>
                                                                </DropdownMenu>
                                                            </div>
                                                            {revealedContacts?.[
                                                                String(i.id)
                                                            ] ? (
                                                                <div className="text-right text-xs text-muted-foreground">
                                                                    <div>
                                                                        {revealedContacts[
                                                                            String(
                                                                                i.id,
                                                                            )
                                                                        ]
                                                                            ?.phone ??
                                                                            'No phone'}
                                                                    </div>
                                                                    <div>
                                                                        {revealedContacts[
                                                                            String(
                                                                                i.id,
                                                                            )
                                                                        ]
                                                                            ?.email ??
                                                                            ''}
                                                                    </div>
                                                                </div>
                                                            ) : canReveal?.[
                                                                  String(i.id)
                                                              ] ? (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="h-7 text-xs"
                                                                    onClick={() =>
                                                                        router.post(
                                                                            `/interests/${i.id}/reveal`,
                                                                            {},
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Reveal
                                                                    contact
                                                                </Button>
                                                            ) : null}
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
                            {interestData.map((i) => {
                                const fp = nestedFreightPost(i);
                                const ulid = postUlid(fp);
                                const { origin, destination } =
                                    freightRoute(fp);
                                if (!ulid) {
                                    return (
                                        <Card
                                            key={i.id}
                                            className="p-4 opacity-60"
                                        >
                                            <div className="text-sm text-muted-foreground">
                                                Freight unavailable — Interest #
                                                {i.id} • {i.status}
                                            </div>
                                        </Card>
                                    );
                                }
                                return (
                                    <Card key={i.id} className="p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2 font-medium">
                                                <MapPin className="size-4 text-primary" />{' '}
                                                {origin?.name ?? '—'}{' '}
                                                <ArrowRight className="size-3 text-muted-foreground" />{' '}
                                                {destination?.name ?? '—'}
                                            </div>
                                            <Badge
                                                variant="secondary"
                                                className={`${statusColor(i.status)} text-xs capitalize`}
                                            >
                                                {i.status}
                                            </Badge>
                                        </div>
                                        <div className="mt-2 truncate text-sm text-muted-foreground">
                                            {i.message ?? '—'}
                                        </div>
                                        <div className="mt-3 flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-8 flex-1"
                                                asChild
                                            >
                                                <Link href={`/freight/${ulid}`}>
                                                    View
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-8"
                                                onClick={() =>
                                                    router.post(
                                                        `/interests/${i.id}/withdraw`,
                                                    )
                                                }
                                                disabled={
                                                    i.status === 'withdrawn'
                                                }
                                            >
                                                Withdraw
                                            </Button>
                                        </div>
                                    </Card>
                                );
                            })}
                        </div>

                        {links && links.length > 3 && (
                            <div className="flex flex-wrap gap-1">
                                {links.map((link, idx) => (
                                    <Button
                                        key={idx}
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
                    </>
                )}
            </div>
        </AppLayout>
    );
}
