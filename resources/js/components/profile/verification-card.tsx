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
import { Spinner } from '@/components/ui/spinner';
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
        <Card id="verification" className="scroll-mt-24">
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Verification
                        </CardTitle>
                        <CardDescription>
                            One badge for your whole profile.
                        </CardDescription>
                    </div>

                    <Badge className={current.className}>
                        {status === 'verified' ? (
                            <BadgeCheck aria-hidden="true" />
                        ) : (
                            <ShieldQuestion aria-hidden="true" />
                        )}
                        {current.label}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent className="space-y-3">
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {current.description}
                </p>

                {status === 'rejected' && note && (
                    <div className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                        <span className="font-medium">
                            Reviewer&apos;s note:
                        </span>{' '}
                        {note}
                    </div>
                )}

                {canRequest && (
                    <form onSubmit={submit} className="space-y-2">
                        <Button
                            type="submit"
                            size="sm"
                            className="w-full"
                            disabled={form.processing || !identityReady}
                            data-test="request-verification"
                        >
                            {form.processing && <Spinner />}
                            {status === 'rejected'
                                ? 'Request verification again'
                                : 'Request verification'}
                        </Button>

                        {!identityReady && (
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Save your display name, phone number, and
                                business legal details first.
                            </p>
                        )}
                    </form>
                )}

                <InputError
                    message={
                        (form.errors as Record<string, string | undefined>)
                            .verification
                    }
                />

                <p className="text-xs leading-relaxed text-muted-foreground">
                    Verification is not a guarantee of service, insurance, or
                    payment.
                </p>
            </CardContent>
        </Card>
    );
}
