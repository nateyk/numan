import ContactChannels from '@/components/profile/contact-channels';
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
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, Eye, Lock, LogIn, ShieldCheck, User } from 'lucide-react';
import { useState } from 'react';

/**
 * Exactly what PostOwnerSummary sends: a display name and a verified flag.
 *
 * Not a User model. There is no email and no phone here, and there must not be —
 * the point of the resource is that contact data cannot reach the page until it has
 * been revealed.
 */
export type PostOwner = {
    id: number;
    display_name: string | null;
    is_verified: boolean;
} | null;

/** Only present once the pair is already in the reveal ledger. */
export type RevealedContacts = {
    name?: string | null;
    phone: string | null;
    email: string | null;
    whatsapp?: string | null;
    phone_numbers?: string[];
    whatsapp_numbers?: string[];
} | null;

export type RevealBlocked = {
    limit: number;
    used: number;
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

/**
 * Turn a stored number into a wa.me URL.
 *
 * Ethiopian numbers arrive in three shapes: +251…, 0…, and a bare 9… or 7….
 * wa.me wants digits only, without a leading zero. International numbers are
 * left alone apart from stripping their punctuation.
 */

function VerificationBadge({ owner }: { owner: NonNullable<PostOwner> }) {
    // Verified is the only state worth saying. "Identity not verified" on every
    // unverified card read as a warning attached to a person the visitor was
    // not allowed to see anyway.
    if (!owner.is_verified) {
        return null;
    }

    return (
        <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
            <ShieldCheck className="size-3 text-green-600" /> Verified consignor
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
 * A guest gets a sign-in link, not a "contact hidden" placeholder. The page is
 * public, the contact is not, and the way forward is an account.
 */
function ContactBlock({
    revealedContacts,
    canReveal,
    isOwnPost,
    requiresPhone,
    onRevealed,
    processing,
}: {
    revealedContacts: RevealedContacts;
    canReveal: boolean;
    isOwnPost: boolean;
    requiresPhone: boolean;
    onRevealed: () => void;
    processing: boolean;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const page = usePage<SharedData>();
    const isGuest = !page.props.auth?.user;

    if (revealedContacts) {
        return (
            <div className="rounded-md border bg-muted/30 p-3">
                <ContactChannels contact={revealedContacts} />
            </div>
        );
    }

    if (isOwnPost) {
        return null;
    }

    // The reveal hands over a phone, so the viewer needs one too. The server
    // keeps a missing phone as a redirect-with-flash rather than a hard block,
    // and this is where that flash lands: an inline step with the field that
    // fixes it — not a toast fired at a page with nowhere to put the number.
    if (canReveal && requiresPhone) {
        return (
            <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                <p className="font-medium">
                    Add your phone number to reveal this contact.
                </p>
                <p className="text-xs text-muted-foreground">
                    We ask for a number before you see someone else’s.
                </p>
                <Button size="sm" variant="outline" className="w-full" asChild>
                    <Link href="/public-profile">Add phone number</Link>
                </Button>
            </div>
        );
    }

    if (canReveal) {
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
                                This uses one of your contact reveals and shows
                                the consignor’s phone, WhatsApp and email.
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
                    Uses one of your contact reveals. Reopening this listing
                    later does not spend another.
                </p>
            </div>
        );
    }

    if (!isGuest) {
        return null;
    }

    return (
        <Button variant="outline" className="w-full" asChild>
            <Link
                href={`/login?redirect=${encodeURIComponent(
                    page.url.split('?')[0],
                )}`}
            >
                <LogIn className="mr-2 size-4" /> Sign in to view contact
            </Link>
        </Button>
    );
}

export default function ConsignorPosterCard({
    owner,
    ulid,
    canReveal,
    revealedContacts,
    revealBlocked,
    requiresPhone = false,
    onRevealed,
    processing,
    isOwnPost,
    interestsCount,
    interestsEnabled = false,
}: {
    owner: PostOwner;
    ulid: string;
    canReveal: boolean;
    revealedContacts: RevealedContacts;
    revealBlocked: RevealBlocked;
    /** Session flash from the reveal endpoint — the viewer has no phone of their own. */
    requiresPhone?: boolean;
    onRevealed: () => void;
    processing: boolean;
    isOwnPost: boolean;
    interestsCount: number | null;
    interestsEnabled?: boolean;
}) {
    // A guest or stranger gets a locked shell: no name, no initials, no
    // verification state. The server omits the owner for them, and this branch
    // keeps the card from dressing the absence up as a person called
    // "Consignor".
    const identityVisible = isOwnPost || owner !== null;

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    {identityVisible ? (
                        <User className="size-4" />
                    ) : (
                        <Lock className="size-4" />
                    )}{' '}
                    Consignor Poster
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {identityVisible ? (
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
                            {owner && <VerificationBadge owner={owner} />}
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-3">
                        <Avatar className="size-12">
                            <AvatarFallback
                                aria-hidden="true"
                                className="blur-[2px] select-none"
                            >
                                ?
                            </AvatarFallback>
                        </Avatar>
                        <div
                            className="min-w-0 flex-1 space-y-2"
                            aria-hidden="true"
                        >
                            <div className="h-3.5 w-28 rounded bg-foreground/20 blur-[4px]" />
                            <div className="h-3 w-40 rounded bg-foreground/10 blur-[4px]" />
                        </div>
                        <Lock
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <span className="sr-only">
                            Consignor identity is locked.
                        </span>
                    </div>
                )}

                {!identityVisible && (
                    <p className="text-xs text-muted-foreground">
                        Who posted this load stays hidden until you reveal their
                        contact.
                    </p>
                )}

                <ContactBlock
                    revealedContacts={revealedContacts}
                    canReveal={canReveal}
                    isOwnPost={isOwnPost}
                    requiresPhone={requiresPhone}
                    onRevealed={onRevealed}
                    processing={processing}
                />

                {/* Only where a reveal is possible: a contact already on screen
                    needs no quota warning, and a guest cannot hold a quota. */}
                {canReveal && revealBlocked && !revealedContacts && (
                    <p className="flex items-start gap-2 text-xs text-destructive">
                        <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                        <span>
                            You have used all {revealBlocked.limit} free contact
                            reveals.{' '}
                            <a
                                href="/billing/plans"
                                className="underline underline-offset-4"
                            >
                                See plans
                            </a>{' '}
                            for unlimited reveals.
                        </span>
                    </p>
                )}

                {isOwnPost && interestsEnabled && (
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
