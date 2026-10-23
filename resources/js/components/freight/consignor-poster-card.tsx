import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Link } from '@inertiajs/react';
import { AlertCircle, Eye, ShieldCheck, User } from 'lucide-react';
import { useState } from 'react';

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
    // Empty rather than a dash or a question mark. A placeholder glyph inside an
    // avatar circle reads as a broken image, which is worse than an empty circle —
    // and this shows for any consignor whose profile has no display_name set.
    if (!name) return '';

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

    const level = owner.verification_level
        ? ` · ${owner.verification_level}`
        : '';

    return (
        <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
            <ShieldCheck className="size-3 text-green-600" /> Verified consignor
            {level}
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
    requiresPhone,
    onRevealed,
    processing,
}: {
    revealedContacts: RevealedContacts;
    canReveal: boolean;
    interestId: number | null;
    requiresPhone: boolean;
    onRevealed: () => void;
    processing: boolean;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    if (revealedContacts) {
        return (
            <div className="space-y-1 rounded-md border bg-muted/30 p-3 text-sm">
                {revealedContacts.phone && (
                    <p>
                        <span className="text-muted-foreground">Phone</span>{' '}
                        <a
                            className="font-medium underline-offset-4 hover:underline"
                            href={`tel:${revealedContacts.phone}`}
                        >
                            {revealedContacts.phone}
                        </a>
                    </p>
                )}
                {revealedContacts.email && (
                    <p className="break-all">
                        <span className="text-muted-foreground">Email</span>{' '}
                        {revealedContacts.email}
                    </p>
                )}
                {!revealedContacts.phone && !revealedContacts.email && (
                    <p className="text-muted-foreground">
                        No phone or email on this profile, so transporters
                        cannot reach you.
                    </p>
                )}
            </div>
        );
    }

    // interestId is required, not optional: without it there is no URL to POST to,
    // so a button here would be decoration.
    if (canReveal && interestId !== null) {
        // The reveal hands over a phone, so the viewer needs one too. The server
        // keeps a missing phone as a redirect-with-flash rather than a hard
        // block, and this is where that flash lands: an inline step with the
        // field that fixes it — not a toast fired at a page with nowhere to
        // put the number.
        if (requiresPhone) {
            return (
                <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                    <p className="font-medium">
                        Add your phone number to reveal this contact.
                    </p>
                    <p className="text-xs text-muted-foreground">
                        A consignor who calls you back needs a number that
                        reaches you.
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        className="w-full"
                        asChild
                    >
                        <Link href="/settings/marketplace-details">
                            Add phone number
                        </Link>
                    </Button>
                </div>
            );
        }

        // The signature transaction, weighted like one: a primary action that
        // confirms before spending a scarce reveal.
        return (
            <div className="space-y-2">
                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <Button
                        className="w-full"
                        disabled={processing}
                        onClick={() => setConfirmOpen(true)}
                    >
                        <Eye className="mr-2 size-4" />
                        {processing ? 'Revealing…' : 'Reveal contact'}
                    </Button>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                Reveal this consignor’s contact?
                            </DialogTitle>
                            <DialogDescription>
                                This uses one of your contact reveals and
                                shares your phone number with them so they
                                can call you back.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setConfirmOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                disabled={processing}
                                onClick={() => {
                                    setConfirmOpen(false);
                                    onRevealed();
                                }}
                            >
                                {processing ? 'Revealing…' : 'Reveal contact'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <p className="text-xs text-muted-foreground">
                    Uses one of your contact reveals. Your phone number is
                    shared with this consignor when you do.
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
    requiresPhone = false,
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
    /** Session flash from the reveal endpoint — the viewer has no phone of their own. */
    requiresPhone?: boolean;
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
                        <AvatarFallback>
                            {initialsFor(owner?.display_name ?? null)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate leading-none font-semibold">
                            {owner?.display_name ?? 'Consignor'}
                        </p>
                        <VerificationBadge owner={owner} />
                    </div>
                </div>

                <ContactBlock
                    revealedContacts={revealedContacts}
                    canReveal={canReveal}
                    interestId={interestId}
                    requiresPhone={requiresPhone}
                    onRevealed={onRevealed}
                    processing={processing}
                />

                {/* Only where a reveal button could exist: interestId is the
                    condition under which ContactBlock can render at all, and a
                    contact already on screen needs no quota warning. Without
                    these gates the card nagged anonymous visitors about a
                    quota they cannot hold. */}
                {revealBlocked && interestId !== null && !revealedContacts && (
                    <p className="flex items-start gap-2 text-xs text-destructive">
                        <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            You have used all {revealBlocked.limit} contact
                            reveals in the last {revealBlocked.window_days}{' '}
                            days.{' '}
                            <a
                                href="/billing/plans"
                                className="underline underline-offset-4"
                            >
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
                                {interestsCount ?? 0} interested transporter
                                {interestsCount === 1 ? '' : 's'}
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
