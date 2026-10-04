import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface Payment {
    id: number;
    tx_ref: string;
    status: string;
    method: string | null;
    reference: string | null;
    plan_name: string | null;
    billing_interval: string | null;
    amount_etb: string;
    currency: string;
    submitted_at: string | null;
    expires_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
}

interface Props {
    payments: Payment[];
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const STATUS_CLASS: Record<string, string> = {
    pending_confirmation: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};

export default function ManualPayIndex({ payments, total, links }: Props) {
    return (
        <div className="flex flex-col gap-4 p-4 md:p-6">
            <Head title="Manual payment requests" />

            <div className="flex items-center justify-between">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Manual payment requests</h1>
                    <p className="text-sm text-muted-foreground">
                        {total} request{total === 1 ? '' : 's'} for this workspace.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <Link href="/billing/plans">
                        <ArrowLeft className="size-4" /> Back to plans
                    </Link>
                </Button>
            </div>

            {payments.length === 0 ? (
                <Card>
                    <CardContent className="py-10 text-center text-sm text-muted-foreground">
                        No manual payment requests yet.
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-3">
                    {payments.map((payment) => (
                        <Link
                            key={payment.id}
                            href={`/marketplace/manual/${payment.id}`}
                            className="rounded-md border p-4 transition-colors hover:bg-accent/40"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div className="space-y-1">
                                    <div className="font-medium">
                                        {payment.plan_name ?? '—'} &middot; {payment.billing_interval ?? '—'}
                                    </div>
                                    <div className="font-mono text-xs text-muted-foreground">
                                        {payment.tx_ref}
                                        {payment.reference ? ` · bank ref ${payment.reference}` : ''}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="tabular-nums font-semibold">
                                        {payment.amount_etb} {payment.currency}
                                    </span>
                                    <Badge className={STATUS_CLASS[payment.status] ?? ''}>
                                        {payment.status}
                                    </Badge>
                                </div>
                            </div>
                            {payment.rejection_reason && (
                                <p className="mt-2 text-xs text-destructive">
                                    {payment.rejection_reason}
                                </p>
                            )}
                        </Link>
                    ))}
                </div>
            )}

            {links.length > 3 && (
                <div className="flex flex-wrap gap-1">
                    {links.map((link, i) => (
                        <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            asChild={Boolean(link.url)}
                        >
                            {link.url ? (
                                <Link href={link.url} preserveScroll>
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Link>
                            ) : (
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            )}
                        </Button>
                    ))}
                </div>
            )}
        </div>
    );
}
