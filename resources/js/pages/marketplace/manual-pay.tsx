import { Head, Link, useForm } from '@inertiajs/react';
import { store as storeManualPayment } from '@/routes/marketplace/manual';
import { AlertTriangle, Building2, Copy, Phone, Receipt } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Method {
    label: string;
    fields: Record<string, string>;
}

interface Props {
    token: string;
    plan: string;
    billingInterval: string;
    amountEtb: string;
    currency: string;
    /** What the payer puts in the transfer note. Ours, not theirs. */
    txRef: string;
    expiresInHours: number;
    methods: Record<string, Method>;
    instructions: string | null;
}

function CopyField({ label, value }: { label: string; value: string }) {
    const [copied, setCopied] = useState(false);

    return (
        <div className="space-y-1">
            <Label className="text-xs text-muted-foreground">{label}</Label>
            <div className="flex items-center gap-2">
                <code className="flex-1 select-all rounded-md border bg-muted/40 px-3 py-2 font-mono text-sm">
                    {value}
                </code>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        navigator.clipboard?.writeText(value);
                        setCopied(true);
                        setTimeout(() => setCopied(false), 2000);
                    }}
                >
                    <Copy className="size-3.5" />
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
        </div>
    );
}

export default function ManualPay({
    token,
    plan,
    billingInterval,
    amountEtb,
    currency,
    txRef,
    expiresInHours,
    methods,
    instructions,
}: Props) {
    const methodKeys = Object.keys(methods);

    // manual_payment and token come from the server rather than the form, so
    // they are read off the error bag by hand rather than typed.
    const { data, setData, post, processing, errors } = useForm({
        token,
        method: methodKeys[0] ?? '',
        // Two references, and the difference matters. Ours is above; this one is
        // the transaction id their banking app gave them, and it is what stops
        // the same transfer being claimed twice.
        reference: '',
        payer_phone: '',
        proof: null as File | null,
    });

    const serverErrors = errors as unknown as Record<string, string | undefined>;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const form = new FormData();
        form.append('token', data.token);
        form.append('method', data.method);
        form.append('reference', data.reference);
        form.append('payer_phone', data.payer_phone);
        if (data.proof) form.append('proof', data.proof);

        post(storeManualPayment.url(), form as unknown as Record<string, string>);
    };

    const selected = methods[data.method];

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Pay manually" />

            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Pay manually</h1>
                <p className="text-sm text-muted-foreground">
                    Transfer the exact amount below, put the reference in the note, then submit your
                    proof. This page is valid for {expiresInHours} hours.
                </p>
            </div>

            {methodKeys.length === 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="size-5 text-amber-600" />
                            Manual payment is unavailable
                        </CardTitle>
                        <CardDescription>
                            No payment method is currently configured. Please use the card option.
                        </CardDescription>
                    </CardHeader>
                </Card>
            ) : (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Receipt className="size-5" />
                                What to transfer
                            </CardTitle>
                            <CardDescription>
                                Transfer exactly this amount. A different amount cannot be confirmed
                                automatically and may delay your request.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-baseline justify-between rounded-md border bg-muted/40 p-4">
                                <span className="text-sm text-muted-foreground">
                                    {plan} &middot; {billingInterval}
                                </span>
                                <span className="text-2xl font-bold tabular-nums">
                                    {amountEtb} {currency}
                                </span>
                            </div>

                            <CopyField label="Reference to put in the transfer note" value={txRef} />

                            <p className="text-xs text-muted-foreground">
                                This reference is how we match your transfer to your request. Without
                                it we cannot find your payment.
                            </p>
                        </CardContent>
                    </Card>

                    {instructions && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Before you transfer</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-line text-sm">{instructions}</p>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="size-5" />
                                Where to send it
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex flex-wrap gap-2">
                                {methodKeys.map((key) => (
                                    <Button
                                        key={key}
                                        type="button"
                                        size="sm"
                                        variant={data.method === key ? 'default' : 'outline'}
                                        onClick={() => setData('method', key)}
                                    >
                                        {methods[key].label}
                                    </Button>
                                ))}
                            </div>

                            {selected && (
                                <div className="space-y-3 rounded-md border bg-muted/20 p-4">
                                    {Object.entries(selected.fields).map(([key, value]) => (
                                        <CopyField key={key} label={humanise(key)} value={value} />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <form onSubmit={submit} className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Phone className="size-5" />
                                    Submit your proof
                                </CardTitle>
                                <CardDescription>
                                    We confirm against the amount in the banking app, not against the
                                    screenshot.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <Label htmlFor="reference">Transaction reference from your transfer</Label>
                                    <Input
                                        id="reference"
                                        value={data.reference}
                                        onChange={(e) => setData('reference', e.target.value)}
                                        placeholder="e.g. FT24091812345"
                                        autoComplete="off"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The transaction ID your {selected?.label ?? 'bank'} app gave you.
                                        Not the reference above.
                                    </p>
                                    {errors.reference && (
                                        <p className="text-xs text-destructive">{errors.reference}</p>
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="payer_phone">Phone number used for the transfer</Label>
                                    <Input
                                        id="payer_phone"
                                        value={data.payer_phone}
                                        onChange={(e) => setData('payer_phone', e.target.value)}
                                        placeholder="+251911234567 or 0911234567"
                                        inputMode="tel"
                                        autoComplete="tel"
                                    />
                                    {errors.payer_phone && (
                                        <p className="text-xs text-destructive">{errors.payer_phone}</p>
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="proof">Screenshot (optional)</Label>
                                    <Input
                                        id="proof"
                                        type="file"
                                        accept="image/jpeg,image/png,application/pdf"
                                        onChange={(e) => setData('proof', e.target.files?.[0] ?? null)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        JPG, PNG or PDF, up to 5 MB. Stored privately and visible only to
                                        you and an administrator.
                                    </p>
                                    {errors.proof && <p className="text-xs text-destructive">{errors.proof}</p>}
                                </div>

                                {errors.token && (
                                    <div className="rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/40">
                                        <p className="text-sm">{errors.token}</p>
                                        <Button asChild size="sm" variant="outline" className="mt-2">
                                            <Link href="/billing/plans">Back to plans</Link>
                                        </Button>
                                    </div>
                                )}

                                {serverErrors.manual_payment && (
                                    <p className="text-sm text-destructive">
                                        {serverErrors.manual_payment}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <div className="flex justify-end gap-2">
                            <Button asChild variant="outline">
                                <Link href="/billing/plans">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Submitting…' : 'Submit payment proof'}
                            </Button>
                        </div>
                    </form>
                </>
            )}

            {serverErrors.method && (
                <p className="flex items-center gap-1 text-sm text-destructive">
                    <AlertTriangle className="size-4" /> {serverErrors.method}
                </p>
            )}

            <p className="text-center text-xs text-muted-foreground">
                A pending request grants no access.{' '}
                <Badge variant="outline" className="text-[10px]">
                    nothing is activated until an administrator confirms
                </Badge>
            </p>
        </div>
    );
}

/**
 * Turn a setting key into a label.
 *
 * The keys are stable and machine-readable on purpose, so this is presentation
 * only -- never something the validation depends on.
 */
function humanise(key: string): string {
    return key
        .replace('manual_payment_', '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}
