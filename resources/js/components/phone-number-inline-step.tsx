import { AlertCircle, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The inline "add your phone number to continue" step.
 *
 * Rendered by the shared layout whenever an action bounced the user back for a
 * missing phone number. One field, saved to the profile, then they retry the
 * action they originally attempted — nothing is created on their behalf first.
 *
 * The field lives here rather than on each page so the flow works from the
 * freight list, the freight detail page, the interest list and the connection
 * page without every page owning a copy of it.
 */
export default function PhoneNumberInlineStep() {
    const { requires_phone: requiresPhone, requires_phone_reason: reason } = usePage().props as {
        requires_phone?: boolean;
        requires_phone_reason?: string | null;
    };

    const [phone, setPhone] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    // A new bounce means a new attempt, so clear whatever was typed before.
    useEffect(() => {
        if (requiresPhone) {
            setPhone('');
            setError(null);
        }
    }, [requiresPhone]);

    if (!requiresPhone) {
        return null;
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!phone.trim()) {
            setError('Enter a phone number.');
            return;
        }

        setSaving(true);
        setError(null);

        router.post(
            '/profile/phone',
            { phone },
            {
                preserveScroll: true,
                onSuccess: () => setSaving(false),
                onError: (errors) => {
                    setSaving(false);
                    setError(
                        (errors as Record<string, string>).phone ??
                            'That number was not accepted.',
                    );
                },
            },
        );
    };

    return (
        <div
            role="alert"
            className="sticky top-0 z-40 border-b border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/60"
        >
            <div className="mx-auto max-w-3xl px-4 py-3">
                <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div className="flex-1 space-y-1">
                        <Label className="flex items-center gap-1.5 text-sm font-medium">
                            <Phone className="size-4" />
                            Add your phone number to continue
                        </Label>
                        <p className="text-xs text-muted-foreground">
                            {reason
                                ? `You need a phone number before ${reason}. `
                                : ''}
                            Carriers and consignors can only reach you if you publish one. It is
                            released only when you choose to reveal it.
                        </p>
                        <Input
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            placeholder="+251911234567 or 0911234567"
                            inputMode="tel"
                            autoComplete="tel"
                            aria-label="Phone number"
                            disabled={saving}
                        />
                        {error && (
                            <p className="flex items-center gap-1 text-xs text-destructive">
                                <AlertCircle className="size-3" /> {error}
                            </p>
                        )}
                    </div>
                    <Button type="submit" disabled={saving} className="shrink-0">
                        {saving ? 'Saving…' : 'Save and continue'}
                    </Button>
                </form>
            </div>
        </div>
    );
}
