import { AlertCircle, Eye, ShieldCheck, User } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

/**
 * Exactly what PostOwnerSummary sends: a display name and a verification level.
 *
 * Not a User model. There is no email and no phone here, and there must not be —
 * the point of the resource is that contact data cannot reach the page until it has
 * been revealed.
 */
export type PostOwner = {
    id: number;
    display_name: string | null;
    verification_level: string | null;
    is_verified: boolean;
} | null;

/** Only present once the pair is already in the reveal ledger. */
export type RevealedContacts = {
    name?: string | null;
    phone: string | null;
    email: string | null;
} | null;

export type RevealBlocked = {
    limit: number;
    used: number;
    window_days: number;
} | null;

function initialsFor(name: string | null): string {
    if (!name) return '—';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function VerificationBadge({ owner }: { owner: PostOwner }) {
    // Was hardcoded to "Verified consignor" on every post, unverified ones
    // included. Now it says what is actually true and stays quiet when it is not.
    if (!owner?.is_verified) {
        return (
            <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                <User className="size-3" /> Consignor · identity not verified
            </p>
        );
    }

    const level = owner.verification_level ? ` · ${owner.verification_level}` : '';

    return (
        <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
            <ShieldCheck className="size-3 text-green-600" /> Verified consignor{level}
        </p>
    );
}

/**
 * The consignor's contact: shown, gated, or absent.
 *
 * The middle state is the one that matters. Nothing here decides whether a reveal
 * is allowed — that is the metered endpoint's job — this only offers the button when
 * the server says the viewer is eligible.
 *
 * When the viewer is not eligible the block renders nothing at all rather than a
 * "contact hidden" placeholder. A placeholder teaches people that a number lives
 * behind it, and on this page the number usually does not.
 */
function ContactBlock({
    revealedContacts,
    canReveal,
    interestId,
    onRevealed,
    processing,
}: {
    revealedContacts: RevealedContacts;
    canReveal: boolean;
    interestId: number | null;
    onRevealed: () => void;
    processing: boolean;
}) {
    if (revealedContacts) {
        return (
            <div className="space-y-1 rounded-md border bg-muted/30 p-3 text-sm">
                {revealedContacts.phone && (
                    <p>
                        <span className="text-muted-foreground">Phone</span>{' '}
                        <a className="font-medium underline-offset-4 hover:underline" href={`tel:${revealedContacts.phone}`}>
                            {revealedContacts.phone}
                        </a>
                    </p>
                )}
                {revealedContacts.email && (
                    <p className="break-all">
                        <span className="text-muted-foreground">Email</span> {revealedContacts.email}
                    </p>
                )}
                {!revealedContacts.phone && !revealedContacts.email && (
                    <p className="text-muted-foreground">
                        No phone or email on this profile, so transporters cannot reach you.
                    </p>
                )}
            </div>
        );
    }

    // interestId is required, not optional: without it there is no URL to POST to,
    // so a button here would be decoration.
    if (canReveal && interestId !== null) {
        return (
            <div className="space-y-2">
                <Button variant="outline" size="sm" className="w-full" disabled={processing} onClick={onRevealed}>
                    <Eye className="mr-2 size-4" />
                    {processing ? 'Revealing…' : 'Reveal contact'}
                </Button>
                <p className="text-xs text-muted-foreground">
                    Uses one of your contact reveals. Your phone number is shared with this consignor when you do.
                </p>
            </div>
        );
    }

    return null;
}

export default function ConsignorPosterCard({
    owner,
    ulid,
    canReveal,
    revealedContacts,
    revealBlocked,
    interestId,
    onRevealed,
    processing,
    isOwnPost,
    interestsCount,
}: {
    owner: PostOwner;
    ulid: string;
    canReveal: boolean;
    revealedContacts: RevealedContacts;
    revealBlocked: RevealBlocked;
    interestId: number | null;
    onRevealed: () => void;
    processing: boolean;
    isOwnPost: boolean;
    interestsCount: number | null;
}) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <User className="size-4" /> Consignor Poster
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                <div className="flex items-center gap-3">
                    <Avatar className="size-12">
                        {/* No avatar_url: the resource does not send one. The page
                            used to request it and render a broken image beside a
                            blank name. */}
                        <AvatarFallback>{initialsFor(owner?.display_name ?? null)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate font-semibold leading-none">{owner?.display_name ?? 'Consignor'}</p>
                        <VerificationBadge owner={owner} />
                    </div>
                </div>

                <ContactBlock
                    revealedContacts={revealedContacts}
                    canReveal={canReveal}
                    interestId={interestId}
                    onRevealed={onRevealed}
                    processing={processing}
                />

                {revealBlocked && (
                    <p className="flex items-start gap-2 text-xs text-destructive">
                        <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            You have used all {revealBlocked.limit} contact reveals in the last {revealBlocked.window_days}{' '}
                            days.{' '}
                            <a href="/billing/plans" className="underline underline-offset-4">
                                See plans
                            </a>
                        </span>
                    </p>
                )}

                {isOwnPost && (
                    <>
                        <Separator />
                        <div className="flex items-center gap-2 rounded-md bg-muted/30 p-3">
                            <p className="flex-1 text-sm font-medium">
                                {interestsCount ?? 0} interested transporter{interestsCount === 1 ? '' : 's'}
                            </p>
                            <Button size="sm" asChild>
                                <a href={`/my-freight/${ulid}/interests`}>
                                    <Eye className="mr-1 size-3" /> View
                                </a>
                            </Button>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}