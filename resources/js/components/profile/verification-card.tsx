import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useForm } from '@inertiajs/react';
import { BadgeCheck, ShieldCheck, ShieldQuestion } from 'lucide-react';

interface Props {
    status: string | null;
    note: string | null;
    identityReady: boolean;
}

export const VERIFICATION_STATUS: Record<
    string,
    { label: string; description: string; className: string }
> = {
    unverified: {
        label: 'Unverified',
        description:
            'Request verification once your identity details are complete. The same badge covers posting freight and carrying it.',
        className:
            'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-300',
    },
    pending: {
        label: 'Pending review',
        description:
            'A reviewer is checking your identity. You can keep using the marketplace while you wait.',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    verified: {
        label: 'Verified',
        description:
            'Your identity has been reviewed. The badge appears on your public profile and next to your name.',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    },
    rejected: {
        label: 'Rejected',
        description:
            'Your verification was not approved. Read the note below, fix the details, and request it again.',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

/**
 * The single verification badge, covering both capabilities.
 *
 * Verification confirms an identity; it is not a guarantee of service, an
 * insurance policy, or a promise that a payment will be honoured. The copy
 * says so, because a badge that reads like a warranty is a liability.
 */
export default function VerificationCard({
    status,
    note,
    identityReady,
}: Props) {
    const form = useForm({});

    const current =
        VERIFICATION_STATUS[status ?? 'unverified'] ??
        VERIFICATION_STATUS.unverified;

    const canRequest = status !== 'pending' && status !== 'verified';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.post('/public-profile/verification', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ShieldCheck className="size-4" />
                    Verification
                </CardTitle>
                <CardDescription>
                    One badge for your whole public profile, whether you post
                    freight, carry it, or both.
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge className={current.className}>
                        {status === 'verified' ? (
                            <BadgeCheck className="mr-1 size-3.5" />
                        ) : (
                            <ShieldQuestion className="mr-1 size-3.5" />
                        )}
                        {current.label}
                    </Badge>
                    <p className="text-sm text-muted-foreground">
                        {current.description}
                    </p>
                </div>

                {status === 'rejected' && note && (
                    <div className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                        <span className="font-medium">
                            Reviewer&apos;s note:
                        </span>{' '}
                        {note}
                    </div>
                )}

                {!identityReady && canRequest && (
                    <p className="text-sm text-muted-foreground">
                        Add your display name, phone number, and business legal
                        details, then save before requesting verification.
                    </p>
                )}

                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-center gap-3"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={
                            form.processing || !canRequest || !identityReady
                        }
                    >
                        {status === 'rejected'
                            ? 'Request verification again'
                            : 'Request verification'}
                    </Button>
                    <p className="text-xs text-muted-foreground">
                        Verification is not a guarantee of service, insurance,
                        or payment.
                    </p>
                </form>

                <InputError
                    message={
                        (form.errors as Record<string, string | undefined>)
                            .verification
                    }
                />
            </CardContent>
        </Card>
    );
}
