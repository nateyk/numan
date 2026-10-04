import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

interface Setting {
    value: string | null;
    label: string;
    description: string | null;
    is_enabled: boolean;
}

interface Props {
    settings: Record<string, Setting>;
    /** method -> the keys it needs before it can be offered. */
    methods: Record<string, string[]>;
    available: Record<string, { label: string; fields: Record<string, string> }>;
    enabled: boolean;
    expiryDays: number;
}

const METHOD_LABEL: Record<string, string> = {
    telebirr: 'Telebirr',
    cbe_birr: 'CBE Birr',
    bank_transfer: 'Bank transfer',
};

function humanise(key: string): string {
    return key
        .replace('manual_payment_', '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function MarketplacePaymentInstructions({
    settings,
    methods,
    available,
    enabled,
    expiryDays,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<Record<string, string | boolean>>(
        Object.entries(settings).reduce<Record<string, string | boolean>>((acc, [key, row]) => {
            acc[key] = row.value ?? '';
            acc[`${key}_enabled`] = row.is_enabled;
            return acc;
        }, {}),
    );

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/marketplace-payment-instructions');
    };

    return (
        <div className="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
            <Head title="Payment instructions" />

            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Payment instructions</h1>
                <p className="text-sm text-muted-foreground">
                    The account details a payer is shown before transferring money. A method is
                    offered only when every field below it is filled in and enabled.
                </p>
            </div>

            <Card
                className={
                    enabled
                        ? 'border-emerald-300 dark:border-emerald-800'
                        : 'border-amber-300 dark:border-amber-800'
                }
            >
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        {enabled ? (
                            <>
                                <CheckCircle2 className="size-5 text-emerald-600" />
                                Manual payment is live
                            </>
                        ) : (
                            <>
                                <AlertTriangle className="size-5 text-amber-600" />
                                Manual payment is hidden
                            </>
                        )}
                    </CardTitle>
                    <CardDescription>
                        {enabled
                            ? `Offered methods: ${Object.values(available)
                                  .map((m) => m.label)
                                  .join(', ')}. Requests lapse after ${expiryDays} day(s) if nobody reviews them.`
                            : 'No method is fully configured, so the "Pay manually" button does not appear on the plans page.'}
                    </CardDescription>
                </CardHeader>
            </Card>

            <form onSubmit={save} className="space-y-6">
                {Object.entries(methods).map(([method, keys]) => {
                    const isAvailable = Boolean(available[method]);

                    return (
                        <Card key={method}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {METHOD_LABEL[method] ?? method}
                                    {isAvailable ? (
                                        <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            offered
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">not offered</Badge>
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    All fields below must be filled in and enabled before this
                                    method appears to payers.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {keys.map((key) => {
                                    const row = settings[key];
                                    const value = String(data[key] ?? '');
                                    const isOn = Boolean(data[`${key}_enabled`]);

                                    return (
                                        <div
                                            key={key}
                                            className="flex flex-wrap items-end gap-3"
                                        >
                                            <div className="min-w-[220px] flex-1 space-y-1">
                                                <Label htmlFor={key}>
                                                    {row?.label ?? humanise(key)}
                                                </Label>
                                                <Input
                                                    id={key}
                                                    value={value}
                                                    onChange={(e) =>
                                                        setData(key, e.target.value)
                                                    }
                                                    placeholder={key.includes('number')
                                                        ? '1000234567894'
                                                        : key.includes('name')
                                                          ? 'XCO Agency'
                                                          : 'Commercial Bank of Ethiopia'}
                                                />
                                                {errors[key] && (
                                                    <p className="text-xs text-destructive">
                                                        {String(errors[key])}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 pb-2">
                                                <Switch
                                                    id={`${key}_enabled`}
                                                    checked={isOn}
                                                    onCheckedChange={(checked) =>
                                                        setData(`${key}_enabled`, checked)
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`${key}_enabled`}
                                                    className="text-xs text-muted-foreground"
                                                >
                                                    Enabled
                                                </Label>
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    );
                })}

                <Card>
                    <CardHeader>
                        <CardTitle>General instructions</CardTitle>
                        <CardDescription>
                            Shown above the account details, e.g. how long a transfer takes.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <Textarea
                            value={String(data.manual_payment_instructions ?? '')}
                            onChange={(e) =>
                                setData('manual_payment_instructions', e.target.value)
                            }
                            rows={4}
                            placeholder="Transfer using your banking app, then enter the reference in the note. Transfers usually settle within an hour."
                        />
                        <div className="flex items-center gap-2">
                            <Switch
                                id="manual_payment_instructions_enabled"
                                checked={Boolean(
                                    data.manual_payment_instructions_enabled,
                                )}
                                onCheckedChange={(checked) =>
                                    setData(
                                        'manual_payment_instructions_enabled',
                                        checked,
                                    )
                                }
                            />
                            <Label
                                htmlFor="manual_payment_instructions_enabled"
                                className="text-xs text-muted-foreground"
                            >
                                Enabled
                            </Label>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Saving…' : 'Save instructions'}
                    </Button>
                </div>
            </form>
        </div>
    );
}
