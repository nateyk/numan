import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Eye, RotateCcw, XCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Row {
    id: number;
    tx_ref: string;
    status: string;
    method: string | null;
    reference: string | null;
    amount_etb: string;
    currency: string;
    plan_name: string | null;
    plan_slug: string | null;
    billing_interval: string | null;
    workspace: { id: number; name: string } | null;
    user: { id: number; name: string } | null;
    payer_phone: string | null;
    has_proof: boolean;
    proof_url: string | null;
    submitted_at: string | null;
    expires_at: string | null;
    reviewed_at: string | null;
    reviewer: string | null;
    rejection_reason: string | null;
    plan_archived: boolean;
    can_confirm: boolean;
    can_reject: boolean;
    can_reopen: boolean;
}

interface Props {
    pending: Row[];
    recent: Row[];
    counts: { pending: number; confirmed: number; rejected: number; expired: number };
}

export default function MarketplaceManualPayments({ pending, recent, counts }: Props) {
    const [confirming, setConfirming] = useState<Row | null>(null);
    const [rejecting, setRejecting] = useState<Row | null>(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | null>(null);

    // Which row has a request in flight, if any. Drives the disabled state.
    const [submitting, setSubmitting] = useState<number | null>(null);

    // The same guard for the instant before React re-renders. State alone is not
    // enough: two clicks inside one tick both see submitting === null, because
    // the re-render has not happened yet. A ref flips synchronously.
    const inFlight = useRef(false);

    const act = (url: string, payload: Record<string, string> = {}) => {
        if (inFlight.current) return;
        inFlight.current = true;

        const rowId = Number(url.split('/').pop());
        setSubmitting(Number.isNaN(rowId) ? -1 : rowId);

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        const token = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        if (token) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = '_token';
            field.value = token.content;
            form.appendChild(field);
        }

        for (const [key, value] of Object.entries(payload)) {
            const field = document.createElement('input');
            field.type = 'hidden';
            field.name = key;
            field.value = value;
            form.appendChild(field);
        }

        document.body.appendChild(form);
        form.submit();
    };

    const submitReject = () => {
        if (reason.trim().length < 5) {
            setReasonError('Give a reason. The payer sees it.');
            return;
        }

        if (!rejecting) return;

        act(`/admin/marketplace-manual-payments/${rejecting.id}/reject`, {
            manual_rejection_reason: reason,
        });
    };

    // One decision per page load. Until the POST navigates, every action button
    // is closed. The server is idempotent anyway -- this stops an admin finding
    // out by submitting twice, not by seeing a duplicate grant.
    const busy = submitting !== null;

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <Head title="Manual payments" />

            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Manual payments</h1>
                <p className="text-sm text-muted-foreground">
                    Confirm against the amount in the banking app. A screenshot is whatever the
                    payer chose to upload.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-4">
                <Count label="Awaiting" value={counts.pending} emphasis />
                <Count label="Confirmed" value={counts.confirmed} />
                <Count label="Rejected" value={counts.rejected} />
                <Count label="Expired" value={counts.expired} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Awaiting confirmation</CardTitle>
                    <CardDescription>
                        {pending.length === 0
                            ? 'Nothing waiting.'
                            : 'Oldest decisions are the ones that hurt.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {pending.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">Queue is empty.</p>
                    ) : (
                        pending.map((row) => (
                            <div key={row.id} className="rounded-md border p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 font-medium">
                                            {row.amount_etb} {row.currency}
                                            <span className="text-sm font-normal text-muted-foreground">
                                                {row.plan_name} · {row.billing_interval}
                                            </span>
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {row.tx_ref}
                                            {row.reference ? ` · bank ref ${row.reference}` : ''}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.workspace?.name} · {row.user?.name}
                                            {row.payer_phone ? ` · ${row.payer_phone}` : ''}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {row.plan_archived && (
                                            <Badge variant="outline" className="text-amber-700 dark:text-amber-400">
                                                <AlertTriangle className="mr-1 size-3" />
                                                Plan archived
                                            </Badge>
                                        )}
                                        {row.has_proof && row.proof_url && (
                                            <Button asChild size="sm" variant="outline">
                                                <a href={row.proof_url} target="_blank" rel="noreferrer">
                                                    <Eye className="size-3.5" /> Proof
                                                </a>
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            disabled={!row.can_confirm || busy}
                                            onClick={() => setConfirming(row)}
                                        >
                                            <CheckCircle2 className="size-3.5" /> Confirm
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={!row.can_reject || busy}
                                            onClick={() => {
                                                setRejecting(row);
                                                setReason('');
                                                setReasonError(null);
                                            }}
                                        >
                                            <XCircle className="size-3.5" /> Reject
                                        </Button>
                                    </div>
                                </div>

                                {!row.can_confirm && row.user && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        You cannot confirm your own payment request.
                                    </p>
                                )}

                                {row.plan_archived && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        The plan was archived after this was submitted. The original
                                        price and duration will be honoured.
                                    </p>
                                )}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Recently decided</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {recent.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">
                            Nothing decided yet.
                        </p>
                    ) : (
                        recent.map((row) => (
                            <div
                                key={row.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"
                            >
                                <div>
                                    <span className="font-medium">
                                        {row.amount_etb} {row.currency}
                                    </span>{' '}
                                    <span className="text-muted-foreground">
                                        {row.plan_name} · {row.workspace?.name}
                                    </span>
                                    <div className="font-mono text-xs text-muted-foreground">
                                        {row.tx_ref}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            row.status === 'confirmed'
                                                ? 'default'
                                                : row.status === 'expired'
                                                  ? 'secondary'
                                                  : 'destructive'
                                        }
                                    >
                                        {row.status}
                                    </Badge>
                                    {row.can_reopen && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={busy}
                                            onClick={() =>
                                                act(`/admin/marketplace-manual-payments/${row.id}/reopen`)
                                            }
                                        >
                                            <RotateCcw className="size-3.5" /> Reopen
                                        </Button>
                                    )}
                                </div>
                                {row.rejection_reason && (
                                    <p className="w-full text-xs text-muted-foreground">
                                        {row.rejection_reason}
                                    </p>
                                )}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            <Dialog open={confirming !== null} onOpenChange={(o) => !o && setConfirming(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm this payment</DialogTitle>
                        <DialogDescription>
                            Verify the amount in the bank or Telebirr app, not the screenshot.
                        </DialogDescription>
                    </DialogHeader>

                    {confirming && (
                        <div className="space-y-2 text-sm">
                            <Line label="Amount to verify" value={`${confirming.amount_etb} ${confirming.currency}`} emphasis />
                            <Line label="Method" value={confirming.method ?? '—'} />
                            <Line label="Bank reference" value={confirming.reference ?? '—'} />
                            <Line label="Reference in transfer note" value={confirming.tx_ref} />
                            <Line label="Payer phone" value={confirming.payer_phone ?? '—'} />
                            <Line label="Workspace" value={confirming.workspace?.name ?? '—'} />
                            <Line label="Submitted" value={formatDate(confirming.submitted_at)} />

                            {confirming.plan_archived && (
                                <p className="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-2 text-xs dark:border-amber-800 dark:bg-amber-950/40">
                                    <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                    The plan was archived after submission. The original price and
                                    duration will be honoured.
                                </p>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" disabled={busy} onClick={() => setConfirming(null)}>
                            Cancel
                        </Button>
                        <Button
                            disabled={busy}
                            onClick={() => {
                                if (!confirming) return;
                                act(`/admin/marketplace-manual-payments/${confirming.id}/confirm`);
                            }}
                        >
                            <CheckCircle2 className="size-4" />
                            {submitting === confirming?.id ? 'Confirming\u2026' : 'Confirm and activate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={rejecting !== null} onOpenChange={(o) => !o && setRejecting(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject this payment</DialogTitle>
                        <DialogDescription>The payer sees this reason.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason</Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => {
                                setReason(e.target.value);
                                setReasonError(null);
                            }}
                            placeholder="e.g. No matching transfer found for reference FT123456789."
                            rows={3}
                        />
                        {reasonError && <p className="text-xs text-destructive">{reasonError}</p>}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" disabled={busy} onClick={() => setRejecting(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" disabled={busy} onClick={submitReject}>
                            {submitting === rejecting?.id ? 'Rejecting\u2026' : 'Reject and notify'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function Count({ label, value, emphasis }: { label: string; value: number; emphasis?: boolean }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className={emphasis ? 'text-3xl text-amber-600' : 'text-3xl'}>{value}</CardTitle>
            </CardHeader>
        </Card>
    );
}

function Line({ label, value, emphasis }: { label: string; value: string; emphasis?: boolean }) {
    return (
        <div className="flex justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className={emphasis ? 'text-right font-mono text-lg font-bold' : 'text-right font-medium'}>
                {value}
            </span>
        </div>
    );
}

function formatDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
