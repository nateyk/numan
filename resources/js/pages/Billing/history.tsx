import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import WorkspaceLayout from '@/layouts/settings/workspace-layout';
import { type BreadcrumbItem, type Invoice } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, Download, FileText, Receipt, Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface MarketplacePayment {
    id: number;
    provider: string;
    status: string;
    plan_name: string | null;
    billing_interval: string | null;
    amount_etb: string;
    currency: string;
    date: string | null;
    reference: string | null;
    has_receipt: boolean;
}

interface BillingHistoryProps {
    invoices: Invoice[];
    /** ETB payments taken through Chapa or a manual transfer. */
    marketplacePayments?: MarketplacePayment[];
    manualPaymentIndex?: string;
}

const PROVIDER_LABEL: Record<string, string> = {
    chapa: 'Card / Chapa',
    manual: 'Manual transfer',
};

export default function BillingHistory({
    invoices,
    marketplacePayments = [],
    manualPaymentIndex,
}: BillingHistoryProps) {
    const { t } = useTranslations();
    const [searchQuery, setSearchQuery] = useState('');
    const [dateFilter, setDateFilter] = useState<
        'all' | 'last30' | 'last90' | 'lastYear'
    >('all');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('billing.title', 'Billing'), href: '/billing' },
        { title: t('billing.history', 'Payment History'), href: '/billing/history' },
    ];

    const filteredInvoices = useMemo(() => {
        return invoices.filter((invoice) => {
            if (searchQuery) {
                const query = searchQuery.toLowerCase();
                return (
                    invoice.id?.toString().toLowerCase().includes(query) ||
                    invoice.total?.toString().toLowerCase().includes(query)
                );
            }

            if (dateFilter !== 'all') {
                const days =
                    dateFilter === 'last30'
                        ? 30
                        : dateFilter === 'last90'
                          ? 90
                          : 365;
                const cutoff = new Date();
                cutoff.setDate(cutoff.getDate() - days);
                if (new Date(invoice.date) < cutoff) return false;
            }

            return true;
        });
    }, [invoices, searchQuery, dateFilter]);

    const etbTotal = marketplacePayments.reduce(
        (sum, p) => sum + Number(p.amount_etb),
        0,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('billing.history', 'Payment History')} />

            <WorkspaceLayout>
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                {t('billing.history', 'Payment History')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t(
                                    'billing.history.description',
                                    'View and download your payment history',
                                )}
                            </p>
                        </div>
                    </div>

                    {marketplacePayments.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="size-5" />
                                    Marketplace payments
                                </CardTitle>
                                <CardDescription>
                                    {marketplacePayments.length} payment
                                    {marketplacePayments.length === 1 ? '' : 's'} totalling{' '}
                                    {etbTotal.toLocaleString()} ETB.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-xs uppercase text-muted-foreground">
                                                <th className="py-2 pr-4">Date</th>
                                                <th className="py-2 pr-4">Plan</th>
                                                <th className="py-2 pr-4">Method</th>
                                                <th className="py-2 pr-4">Reference</th>
                                                <th className="py-2 pr-4 text-right">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {marketplacePayments.map((payment) => (
                                                <tr
                                                    key={payment.id}
                                                    className="border-b last:border-0"
                                                >
                                                    <td className="py-2 pr-4">
                                                        {payment.date ?? '—'}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {payment.plan_name ?? '—'}
                                                        {payment.billing_interval && (
                                                            <span className="ml-1 text-xs text-muted-foreground">
                                                                ({payment.billing_interval})
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        <Badge variant="outline">
                                                            {PROVIDER_LABEL[
                                                                payment.provider
                                                            ] ?? payment.provider}
                                                        </Badge>
                                                    </td>
                                                    <td className="py-2 pr-4 font-mono text-xs">
                                                        {payment.reference ?? '—'}
                                                    </td>
                                                    <td className="py-2 pr-4 text-right tabular-nums">
                                                        {payment.amount_etb}{' '}
                                                        {payment.currency}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {manualPaymentIndex && (
                                    <p className="mt-4 text-xs text-muted-foreground">
                                        <Link
                                            href={manualPaymentIndex}
                                            className="underline"
                                        >
                                            See pending and rejected manual payment
                                            requests
                                        </Link>
                                        .
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="size-5" />
                                {t('billing.invoices', 'Invoices')}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'billing.invoices.description',
                                    'Download your Stripe invoices',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4 flex flex-wrap gap-2">
                                <div className="relative flex-1 min-w-[200px]">
                                    <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        placeholder={t(
                                            'common.search',
                                            'Search invoices...',
                                        )}
                                        className="pl-8"
                                    />
                                    {searchQuery && (
                                        <button
                                            onClick={() => setSearchQuery('')}
                                            className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                                        >
                                            <X className="size-4" />
                                        </button>
                                    )}
                                </div>
                                <select
                                    value={dateFilter}
                                    onChange={(e) =>
                                        setDateFilter(
                                            e.target.value as typeof dateFilter,
                                        )
                                    }
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="all">All dates</option>
                                    <option value="last30">Last 30 days</option>
                                    <option value="last90">Last 90 days</option>
                                    <option value="lastYear">Last year</option>
                                </select>
                            </div>

                            {filteredInvoices.length === 0 ? (
                                <div className="py-8 text-center text-muted-foreground">
                                    <Calendar className="mx-auto mb-2 size-8" />
                                    <p>
                                        {t(
                                            'billing.noInvoices',
                                            'No invoices found',
                                        )}
                                    </p>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    {filteredInvoices.map((invoice) => (
                                        <div
                                            key={invoice.id}
                                            className="flex items-center justify-between rounded-md border p-3"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {invoice.date}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {invoice.total}
                                                </p>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={invoice.pdf_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Download className="size-4" />
                                                    PDF
                                                </a>
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </WorkspaceLayout>
        </AppLayout>
    );
}
