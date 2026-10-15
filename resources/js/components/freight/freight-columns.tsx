import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { freightRoute, routeLabel } from '@/lib/freight-route';
import { Link } from '@inertiajs/react';
import { createColumnHelper } from '@tanstack/react-table';
import { Eye } from 'lucide-react';
import { type DataTableFeatures } from './data-table-features';

export type FreightPost = {
    ulid: string;
    status: string;
    item_name: string | null;
    cargo_description: string | null;
    pickup_date: string | null;
    actual_weight: string | null;
    weight_unit: string | null;
    tariff_amount: string | null;
    tariff_currency: string | null;
    user?: { name: string; email: string };
    /**
     * A bare Eloquent model serialises these relations snake_cased; a
     * FreightPostResource sends origin / destination. Read through
     * freightRoute() rather than picked apart here.
     */
    origin?: { name: string; region_name: string };
    destination?: { name: string; region_name: string };
    origin_location?: { name: string; region_name: string };
    destination_location?: { name: string; region_name: string };
};

const columnHelper = createColumnHelper<DataTableFeatures, FreightPost>();

// Every colour carries a dark variant. Without them the badge is a pale wash on
// a dark background and the status is unreadable in dark mode.
const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    submitted: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    under_review:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
    closed: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    expired:
        'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

/**
 * Admin freight moderation queue.
 *
 * No column offers sorting. TanStack's client-side sorting would reorder the
 * current page only, while the arrows imply the whole queue is being sorted, and
 * AdminFreightController::index() does not read a sort parameter so there is
 * nothing behind them. Wiring server-side sorting properly is its own piece of
 * work; until then there are no arrows rather than arrows that lie.
 *
 * The approve and reject affordances that used to sit here were three links to
 * the same detail URL. Two of them were green and red, which read as actions,
 * and all three did nothing except navigate. Approving belongs on the detail page
 * where the evidence can actually be looked at.
 */
export const freightColumns = columnHelper.columns([
    columnHelper.accessor((row) => routeLabel(row), {
        id: 'route',
        header: 'Route',
        cell: ({ row }) => {
            const { origin, destination } = freightRoute(row.original);
            const originName = origin?.name ?? '—';
            const destinationName = destination?.name ?? '—';
            const originRegion = origin?.region_name ?? '';
            const destRegion = destination?.region_name ?? '';

            return (
                <div>
                    <div className="font-medium">
                        {originName} → {destinationName}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {originRegion} → {destRegion}
                    </div>
                </div>
            );
        },
    }),
    columnHelper.accessor('item_name', {
        header: 'Cargo',
        cell: ({ row }) =>
            row.original.item_name ?? row.original.cargo_description ?? '—',
    }),
    columnHelper.accessor(
        (row) =>
            row.actual_weight
                ? `${row.actual_weight} ${row.weight_unit ?? 'ton'}`
                : '—',
        {
            id: 'weight',
            header: 'Weight',
            cell: ({ row }) => {
                const weight = row.original.actual_weight;
                const unit = row.original.weight_unit ?? 'ton';
                return weight ? `${weight} ${unit}` : '—';
            },
        },
    ),
    columnHelper.accessor(
        (row) =>
            row.tariff_amount
                ? `${row.tariff_amount} ${row.tariff_currency ?? 'ETB'}`
                : '—',
        {
            id: 'tariff',
            header: 'Tariff',
            cell: ({ row }) => {
                const amount = row.original.tariff_amount;
                const currency = row.original.tariff_currency ?? 'ETB';
                return amount ? `${amount} ${currency}` : '—';
            },
        },
    ),
    columnHelper.accessor('pickup_date', {
        header: 'Pickup',
        cell: ({ row }) =>
            row.original.pickup_date
                ? String(row.original.pickup_date).slice(0, 10)
                : '—',
    }),
    columnHelper.accessor((row) => row.user?.name ?? '—', {
        id: 'user',
        header: 'User',
        cell: ({ row }) => {
            const user = row.original.user;
            return (
                <div>
                    <div>{user?.name ?? '—'}</div>
                    <div className="text-xs text-muted-foreground">
                        {user?.email ?? ''}
                    </div>
                </div>
            );
        },
    }),
    columnHelper.accessor('status', {
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status;
            return (
                <Badge className={statusColors[status] ?? ''}>{status}</Badge>
            );
        },
    }),
    columnHelper.display({
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => (
            <Button variant="ghost" size="sm" asChild>
                <Link
                    href={`/admin/freight/${row.original.ulid}`}
                    aria-label={`Review freight post ${row.original.ulid}`}
                >
                    <Eye className="h-4 w-4" />
                </Link>
            </Button>
        ),
    }),
]);
