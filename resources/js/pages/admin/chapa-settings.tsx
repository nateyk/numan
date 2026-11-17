import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { Head, useForm } from '@inertiajs/react';

type KeySource = 'dashboard' | 'environment' | 'missing';

interface Props {
    settings: {
        mode: 'test' | 'live';
        public_key_source: KeySource;
        secret_key_source: KeySource;
        webhook_secret_source: KeySource;
        last_updated: string | null;
    };
    webhook_url: string;
}

const sourceLabel: Record<KeySource, string> = {
    dashboard: 'Saved securely',
    environment: 'From server environment',
    missing: 'Not configured',
};

export default function AdminChapaSettings({
    settings,
    webhook_url,
}: Props) {
    const form = useForm({
        mode: settings.mode,
        public_key: '',
        secret_key: '',
        webhook_secret: '',
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        form.put('/admin/chapa-settings', {
            preserveScroll: true,
            onSuccess: () => {
                // Never retain submitted credentials after saving.
                form.reset('public_key', 'secret_key', 'webhook_secret');
                form.clearErrors();
            },
        });
    };

    return (
        <AdminLayout>
            <Head title="Chapa Settings" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Chapa Settings
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Configure Numan Freight's existing Chapa v1 checkout.
                        Saved credentials are encrypted and never displayed
                        again. Leave a key blank to keep its current value.
                    </p>
                </div>

                <form
                    onSubmit={save}
                    className="flex w-full max-w-3xl flex-col gap-5"
                >
                    <Card className="gap-0 py-0 shadow-none">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="text-base">
                                Payment credentials
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5 p-5">
                            <div className="space-y-2">
                                <Label htmlFor="chapa-mode">Payment mode</Label>
                                <select
                                    id="chapa-mode"
                                    value={form.data.mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'mode',
                                            event.target.value as 'test' | 'live',
                                        )
                                    }
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    aria-invalid={Boolean(form.errors.mode)}
                                >
                                    <option value="test">Test mode</option>
                                    <option value="live">Live mode</option>
                                </select>
                                <p className="text-xs text-muted-foreground">
                                    Switching modes requires the public key,
                                    secret key and webhook secret for the new
                                    mode. Live mode creates real payments.
                                </p>
                                {form.errors.mode && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.mode}
                                    </p>
                                )}
                            </div>

                            {(
                                [
                                    {
                                        name: 'public_key',
                                        label: 'Public key',
                                        source: settings.public_key_source,
                                        hint: 'From the Chapa dashboard API keys section.',
                                    },
                                    {
                                        name: 'secret_key',
                                        label: 'Secret key',
                                        source: settings.secret_key_source,
                                        hint: 'Used server-side to initialize and verify payments. Never share it.',
                                    },
                                    {
                                        name: 'webhook_secret',
                                        label: 'Webhook secret',
                                        source: settings.webhook_secret_source,
                                        hint: 'Use the same secret hash configured for the webhook in Chapa.',
                                    },
                                ] as const
                            ).map((field) => (
                                <div key={field.name} className="space-y-2">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <Label htmlFor={field.name}>
                                            {field.label}
                                        </Label>
                                        <Badge
                                            variant="outline"
                                            className="font-normal"
                                        >
                                            {sourceLabel[field.source]}
                                        </Badge>
                                    </div>
                                    <Input
                                        id={field.name}
                                        type="password"
                                        autoComplete="new-password"
                                        spellCheck={false}
                                        value={form.data[field.name]}
                                        onChange={(event) =>
                                            form.setData(
                                                field.name,
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Leave blank to keep current key"
                                        aria-invalid={Boolean(
                                            form.errors[field.name],
                                        )}
                                        className="h-10"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {field.hint}
                                    </p>
                                    {form.errors[field.name] && (
                                        <p className="text-xs text-destructive">
                                            {form.errors[field.name]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0 shadow-none">
                        <CardHeader className="border-b px-5 py-4">
                            <CardTitle className="text-base">
                                Webhook endpoint
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 p-5">
                            <div className="space-y-2">
                                <Label htmlFor="chapa-webhook-url">
                                    Webhook URL
                                </Label>
                                <Input
                                    id="chapa-webhook-url"
                                    value={webhook_url}
                                    readOnly
                                    onFocus={(event) =>
                                        event.currentTarget.select()
                                    }
                                    className="h-10 font-mono text-xs"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Add this HTTPS URL in your Chapa dashboard's
                                    Webhooks section. Set the webhook secret
                                    there to the same value saved above.
                                    Incoming signatures are checked before
                                    payment verification.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-between gap-3">
                        <p className="text-xs text-muted-foreground">
                            {settings.last_updated
                                ? 'Dashboard credentials were last updated.'
                                : 'Server environment values remain available until overridden.'}
                        </p>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save settings'}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
