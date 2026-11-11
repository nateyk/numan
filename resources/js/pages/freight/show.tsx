import ConsignorPosterCard, {
    type PostOwner,
    type RevealBlocked,
    type RevealedContacts,
} from '@/components/freight/consignor-poster-card';
import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import FreightDetailsCard, {
    type FreightDetailsPost,
} from '@/components/freight/freight-details-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, MapPin, Users } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Props {
    post: FreightDetailsPost & {
        owner?: PostOwner;
        is_own_post: boolean;
    };
    hasInterest: boolean;
    interestStatus: string | null;
    /** Needed for the flag-on withdraw action, never for the reveal. */
    interestId: number | null;
    canReveal: boolean;
    revealedContacts: RevealedContacts;
    revealBlock?: RevealBlocked;
    interestsCount: number | null;
    canRegister?: boolean;
    /**
     * Set when a paid plan lapsed and the expiry sweep hid this post.
     *
     * The controller has sent both of these since the sweep landed, saying the
     * owner needs the badge here. The page read neither, so the owner saw a normal
     * post with no explanation of why nobody else can.
     */
    isHiddenListing?: boolean;
    canRestoreListing?: boolean;
}

/**
 * What each interest status means for the carrier, in the page's own words.
 *
 * There was one copy for every status — "awaiting consignor" — so a rejected
 * carrier read a promise the consignor had already declined, twice over: the
 * rejection named and then denied.
 */
const interestStatusCopy: Record<string, string> = {
    submitted: "Interest submitted — awaiting the consignor's review.",
    shortlisted:
        'The consignor shortlisted your interest — awaiting their final choice.',
    selected:
        'The consignor selected you for this load. Continue in My Interests.',
    rejected:
        'The consignor did not select your interest this time. You can express interest again.',
    withdrawn:
        'You withdrew your interest in this load. You can express interest again.',
};

export default function FreightShow({
    post,
    hasInterest,
    interestStatus,
    interestId,
    canReveal,
    revealedContacts,
    revealBlock = null,
    interestsCount,
    isHiddenListing = false,
    canRestoreListing = false,
    canRegister = true,
}: Props) {
    const {
        auth,
        marketplace,
        reveal_blocked: revealBlocked,
        requires_phone: requiresPhone,
    } = usePage<SharedData>().props;
    const interestsEnabled = marketplace?.interests_enabled ?? false;
    const [interestOpen, setInterestOpen] = useState(false);
    const [withdrawOpen, setWithdrawOpen] = useState(false);

    // Server-decided. The owner identity is omitted from the payload for
    // everyone else, so comparing auth ids here would read every locked
    // stranger as a visitor — and every own post as somebody else's.
    const isOwnPost = post.is_own_post;

    const {
        data: interestData,
        setData: setInterestData,
        post: postInterest,
        processing: interestProcessing,
        errors: interestErrors,
        reset: resetInterest,
    } = useForm<{
        message: string;
        /**
         * Never sent. Declared because the server validates the whole submission
         * and returns failures under this key — an incomplete public profile,
         * for one — and reading an undeclared key off a typed form is an error
         * the compiler is right to raise.
         */
        interest?: string;
    }>({
        message: '',
    });

    // The reveal goes through the same metered, ledgered endpoint the connections
    // page uses. It is the only thing in this file allowed to hand over contact
    // data, and nothing here decides whether it may. A missing phone is the
    // server's answer to keep (it redirects back with the flash), and the poster
    // card renders that state inline with a link to the field that fixes it.
    const { post: postReveal, processing: revealProcessing } = useForm({});

    // Withdrawal is confirmed first, the way the interests page confirms the
    // carrier-facing side of the same action.
    const { post: postWithdraw, processing: withdrawProcessing } = useForm({});

    const handleReveal = () => {
        postReveal(`/freight/${post.ulid}/reveal`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Contact revealed.');
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message) toast.error(String(message));
            },
        });
    };

    const handleExpressInterest = () => {
        if (!auth?.user) {
            router.visit('/login');

            return;
        }

        setInterestOpen(true);
    };

    const submitInterest = (e: React.FormEvent) => {
        e.preventDefault();

        postInterest(`/my-freight/${post.ulid}/interests`, {
            preserveScroll: true,
            onSuccess: () => {
                setInterestOpen(false);
                resetInterest();
                toast.success('Interest expressed successfully.');
            },
            onError: (errors) => {
                const msg =
                    (errors as Record<string, string>).interest ||
                    (Object.values(errors)[0] as string);

                // No auto-navigation on a string match. The dialog stays open
                // and offers the profile button where the error is explained —
                // a timed redirect on a fuzzy match seizes the page from
                // somebody who was only reading it.
                if (msg) toast.error(String(msg));
            },
        });
    };

    const submitWithdraw = () => {
        if (interestId === null) return;

        postWithdraw(`/interests/${interestId}/withdraw`, {
            preserveScroll: true,
            onSuccess: () => {
                setWithdrawOpen(false);
                toast.success('Interest withdrawn.');
            },
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message) toast.error(String(message));
            },
        });
    };

    const blocked = (revealBlocked ?? revealBlock ?? null) as RevealBlocked;

    // Five interest outcomes, five honest renderings. The server allows a fresh
    // expression after a rejection or withdrawal (its duplicate check skips
    // those two statuses), so this page offers it rather than dead-ending.
    const canReExpress =
        hasInterest &&
        post.status === 'active' &&
        (interestStatus === 'rejected' || interestStatus === 'withdrawn');

    // Withdrawn and rejected interests cannot be withdrawn again, and a
    // Selected interest is terminal on the server.
    const canWithdraw =
        interestId !== null &&
        (interestStatus === null ||
            interestStatus === 'submitted' ||
            interestStatus === 'shortlisted');

    const canSubmitInterest =
        !isOwnPost && !hasInterest && post.status === 'active';

    return (
        <>
            <Head
                title={`${post.origin?.name ?? 'Freight'} → ${post.destination?.name ?? ''} — Numan Freight`}
            />

            <div className="min-h-screen bg-background text-foreground">
                <DiscoveryHeader canRegister={canRegister} />

                <main>
                    <div className="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/freight">
                                <ArrowLeft className="mr-2 size-4" /> Back to
                                Freight
                            </Link>
                        </Button>

                        {/* Only the owner or a superadmin can reach this — the controller
                            404s a hidden post for everybody else, so anyone seeing the
                            banner can already act on it. Wording is deliberately
                            identical to the My Freight banner: two screens describing
                            the same fact differently is how a support ticket gets
                            written. */}
                        {isHiddenListing && (
                            <div className="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-800 dark:bg-amber-950/40">
                                <p className="font-medium">
                                    This post is hidden because your plan ended.
                                    It is still here — subscribe to make it
                                    public again.
                                </p>
                                {canRestoreListing && (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="shrink-0"
                                    >
                                        <Link href="/billing/plans">
                                            Subscribe
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </div>

                    <section className="py-6">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-6 space-y-1">
                                <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight">
                                    <MapPin className="size-7 text-primary" />
                                    {post.origin?.name ?? 'Origin'}{' '}
                                    <ArrowRight className="size-5 text-muted-foreground" />
                                    {post.destination?.name ?? 'Destination'}
                                </h1>
                            </div>

                            <div className="grid items-start gap-6 lg:grid-cols-12">
                                {/* Wide: Freight Details on the left, Consignor Poster
                                    with the action buttons on the right. Compact: they
                                    stack in this order, so the details — what a
                                    transporter actually evaluates — come first and
                                    the call to action follows underneath. */}
                                <div className="lg:col-span-8">
                                    <FreightDetailsCard post={post} />
                                </div>

                                <div className="space-y-4 lg:col-span-4">
                                    <ConsignorPosterCard
                                        owner={post.owner ?? null}
                                        ulid={post.ulid}
                                        canReveal={canReveal}
                                        revealedContacts={revealedContacts}
                                        revealBlocked={blocked}
                                        requiresPhone={requiresPhone}
                                        onRevealed={handleReveal}
                                        processing={revealProcessing}
                                        isOwnPost={isOwnPost}
                                        interestsCount={interestsCount}
                                        interestsEnabled={interestsEnabled}
                                    />

                                    {/* On its own column so the primary call to action is
                                        not buried under a dozen detail rows. */}
                                    {isOwnPost && (
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link
                                                href={`/my-freight/${post.ulid}`}
                                            >
                                                Manage Your Post
                                            </Link>
                                        </Button>
                                    )}

                                    {!isOwnPost && interestsEnabled && (
                                        <>
                                            {hasInterest ? (
                                                <>
                                                    <p
                                                        role="status"
                                                        className="rounded-md border bg-muted/30 px-4 py-3 text-sm"
                                                    >
                                                        {interestStatusCopy[
                                                            interestStatus ?? ''
                                                        ] ??
                                                            interestStatusCopy.submitted}
                                                    </p>
                                                    {canWithdraw && (
                                                        <Button
                                                            variant="outline"
                                                            className="w-full"
                                                            onClick={() =>
                                                                setWithdrawOpen(
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            Withdraw Interest
                                                        </Button>
                                                    )}
                                                    {canReExpress && (
                                                        <Button
                                                            className="w-full"
                                                            onClick={
                                                                handleExpressInterest
                                                            }
                                                        >
                                                            Express Interest
                                                            Again
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        className="w-full"
                                                        asChild
                                                    >
                                                        <Link href="/my-interests">
                                                            <Users className="mr-1 size-4" />{' '}
                                                            My Interests
                                                        </Link>
                                                    </Button>
                                                </>
                                            ) : post.status !== 'active' ? (
                                                <Badge
                                                    variant="outline"
                                                    className="justify-center py-2 capitalize"
                                                >
                                                    Freight {post.status} — not
                                                    available for interest
                                                </Badge>
                                            ) : (
                                                <Button
                                                    onClick={
                                                        handleExpressInterest
                                                    }
                                                    className="w-full"
                                                    size="lg"
                                                >
                                                    Express Interest
                                                </Button>
                                            )}
                                        </>
                                    )}

                                    {/* One dialog serves both triggers — the
                                        first expression and a re-expression
                                        after a rejection or withdrawal — since
                                        they POST to the same endpoint. */}
                                    <Dialog
                                        open={interestsEnabled && interestOpen}
                                        onOpenChange={setInterestOpen}
                                    >
                                        {(canSubmitInterest ||
                                            canReExpress) && (
                                            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                                                <DialogHeader>
                                                    <DialogTitle>
                                                        Express Interest —{' '}
                                                        {post.origin?.name ??
                                                            'Freight'}{' '}
                                                        →{' '}
                                                        {post.destination
                                                            ?.name ?? ''}
                                                    </DialogTitle>
                                                    <DialogDescription>
                                                        Send the consignor a
                                                        short message — your
                                                        profile contact details
                                                        are shared only when you
                                                        choose to reveal them.
                                                    </DialogDescription>
                                                </DialogHeader>

                                                <form
                                                    onSubmit={submitInterest}
                                                    className="space-y-4"
                                                >
                                                    {interestErrors.interest && (
                                                        <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                                            {
                                                                interestErrors.interest
                                                            }
                                                            {String(
                                                                interestErrors.interest,
                                                            )
                                                                .toLowerCase()
                                                                .includes(
                                                                    'public profile',
                                                                ) && (
                                                                <div className="mt-2">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            router.visit(
                                                                                '/public-profile',
                                                                            )
                                                                        }
                                                                    >
                                                                        Complete
                                                                        Public
                                                                        Profile
                                                                    </Button>
                                                                </div>
                                                            )}
                                                        </div>
                                                    )}

                                                    <div>
                                                        <Label>
                                                            Message (optional)
                                                        </Label>
                                                        <Textarea
                                                            value={
                                                                interestData.message
                                                            }
                                                            onChange={(e) =>
                                                                setInterestData(
                                                                    'message',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Why are you interested? e.g., Available truck, experience on this route"
                                                            rows={2}
                                                        />
                                                    </div>

                                                    <p className="text-xs text-muted-foreground">
                                                        Your name, phone and
                                                        email come from your
                                                        profile and are shared
                                                        only when you choose to
                                                        reveal them.
                                                    </p>

                                                    <DialogFooter>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setInterestOpen(
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                interestProcessing
                                                            }
                                                        >
                                                            Submit Interest
                                                        </Button>
                                                    </DialogFooter>
                                                </form>
                                            </DialogContent>
                                        )}
                                    </Dialog>

                                    <Dialog
                                        open={interestsEnabled && withdrawOpen}
                                        onOpenChange={setWithdrawOpen}
                                    >
                                        <DialogContent className="sm:max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Withdraw your interest?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    The consignor will see your
                                                    interest as withdrawn. You
                                                    can express interest again
                                                    later, but it starts fresh.
                                                </DialogDescription>
                                            </DialogHeader>
                                            <DialogFooter>
                                                <Button
                                                    variant="outline"
                                                    onClick={() =>
                                                        setWithdrawOpen(false)
                                                    }
                                                >
                                                    Keep Interest
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    disabled={
                                                        withdrawProcessing
                                                    }
                                                    onClick={submitWithdraw}
                                                >
                                                    {withdrawProcessing
                                                        ? 'Withdrawing…'
                                                        : 'Withdraw Interest'}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>

                                    <Button
                                        variant="ghost"
                                        className="w-full"
                                        asChild
                                    >
                                        <Link href="/freight">
                                            Browse More Freight
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>

                <DiscoveryFooter />
            </div>
        </>
    );
}
