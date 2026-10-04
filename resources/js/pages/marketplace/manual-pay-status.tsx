import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock, FileWarning, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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
    payment: Payment;
}

const STATUS: Record<
    string,
    { label: string; icon: typeof Clock; className: string; blurb: string }
> = {
    pending_confirmation: {
        label: 'Awaiting confirmation',
        icon: Clock,
        className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        blurb: 'We have your proof. An administrator checks it against the amount in the banking app. Nothing is activated until then.',
    },
    confirmed: {
        label: 'Confirmed',
        icon: CheckCircle2,
        className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        blurb: 'Your payment is confirmed and your plan is active.',
    },
    rejected: {
        label: 'Not confirmed',
        icon: XCircle,
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        blurb: 'We could not confirm this payment.',
    },
    expired: {
        label: 'Expired',
        icon: FileWarning,
        className: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        blurb: 'This request was not confirmed in time and has been closed. Nothing was charged.',
    },
};

export default function ManualPayStatus({ payment }: Props) {
    const status = STATUS[payment.status] ?? STATUS.pending_confirmation;
    const Icon = status.icon;

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-6 p-4 md:p-6">
            <Head title="Payment request" />

            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Payment request</h1>
                <p className="text-sm text-muted-foreground">
                    Reference <span className="font-mono">{payment.tx_ref}</span>
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Icon className="size-5" />
                        {status.label}
                        <Badge className={status.className}>{payment.status}</Badge>
                    </CardTitle>
                    <CardDescription>{status.blurb}</CardDescription>
                </CardHeader>
                <CardContent className="space-y-2 text-sm">
                    <Row label="Plan" value={`${payment.plan_name ?? '—'} · ${payment.billing_interval ?? '—'}`} />
                    <Row label="Amount" value={`${payment.amount_etb} ${payment.currency}`} />
                    <Row label="Method" value={payment.method ?? '—'} />
                    <Row label="Your transfer reference" value={payment.reference ?? '—'} />
                    <Row label="Submitted" value={formatDate(payment.submitted_at)} />
                    {payment.status === 'pending_confirmation' && (
                        <Row label="Confirm by" value={formatDate(payment.expires_at)} />
                    )}
                    {payment.reviewed_at && <Row label="Reviewed" value={formatDate(payment.reviewed_at)} />}
                </CardContent>
            </Card>

            {payment.rejection_reason && (
                <Card className="border-red-300 dark:border-red-800">
                    <CardHeader>
                        <CardTitle>Why it was not confirmed</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm">{payment.rejection_reason}</p>
                    </CardContent>
                </Card>
            )}

            {payment.status === 'expired' && (
                <p className="text-sm text-muted-foreground">
                    You can start a new request whenever you are ready.
                </p>
            )}

            <div className="flex justify-end gap-2">
                <Button asChild variant="outline">
                    <Link href="/billing/plans">Back to plans</Link>
                </Button>
                {(payment.status === 'expired' || payment.status === 'rejected') && (
                    <Button asChild>
                        <Link href={`/billing/plans?recommended_plan=standard&recommended_billing_period=${payment.billing_interval ?? 'monthly'}`}>
                            Start a new request
                        </Link>
                    </Button>
                )}
            </div>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
