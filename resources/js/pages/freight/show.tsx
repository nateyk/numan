import ConsignorPosterCard, {
    type PostOwner,
    type RevealBlocked,
    type RevealedContacts,
} from '@/components/freight/consignor-poster-card';
import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
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
    post: FreightDetailsPost & { owner: PostOwner };
    hasInterest: boolean;
    interestStatus: string | null;
    /** Needed to build the reveal URL. Absent means no interest, so no button. */
    interestId: number | null;
    canReveal: boolean;
    revealedContacts: RevealedContacts;
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

/** One headline fact, the way a rate sheet would typeset it. */
function Stat({
    label,
    value,
    sub,
    peak = false,
}: {
    label: string;
    value: React.ReactNode;
    sub?: string;
    peak?: boolean;
}) {
    return (
        <div className="rounded-lg border bg-muted/30 px-4 py-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={
                    peak
                        ? 'mt-0.5 text-2xl font-bold tracking-tight'
                        : 'mt-0.5 text-base font-semibold'
                }
            >
                {value}
            </p>
            {sub && (
                <p className="text-xs text-muted-foreground">{sub}</p>
            )}
        </div>
    );
}

export default function FreightShow({
    post,
    hasInterest,
    interestStatus,
    interestId,
    canReveal,
    revealedContacts,
    interestsCount,
    isHiddenListing = false,
    canRestoreListing = false,
    canRegister = true,
}: Props) {
    const {
        auth,
        reveal_blocked: revealBlocked,
        requires_phone: requiresPhone,
    } = usePage<SharedData>().props;
    const [interestOpen, setInterestOpen] = useState(false);
    const [withdrawOpen, setWithdrawOpen] = useState(false);

    const isOwnPost = auth?.user?.id === post.owner?.id;

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
         * and returns failures under this key — a missing transporter profile, for
         * one — and reading an undeclared key off a typed form is an error the
         * compiler is right to raise.
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
    const { post: postWithdraw, processing: withdrawProcessing } =
        useForm({});

    const handleReveal = () => {
        if (interestId === null) return;

        postReveal(`/interests/${interestId}/reveal`, {
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

    const blocked = (revealBlocked ?? null) as RevealBlocked;

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

    const routeSnapshot = post.route_snapshot;
    const laneFact = routeSnapshot
        ? [
              formatDistance(routeSnapshot.distance_meters),
              formatDuration(routeSnapshot.duration_seconds),
          ]
              .filter(Boolean)
              .join(' · ')
        : 'Not calculated';

    // "T00:00:00" so the ISO date lands on the same day in every timezone.
    const pickupValue = post.pickup_date
        ? new Date(`${post.pickup_date}T00:00:00`).toLocaleDateString(
              undefined,
              { day: 'numeric', month: 'short', year: 'numeric' },
          )
        : 'Flexible';

    const pickupSub =
        [
            post.pickup_time ? post.pickup_time.slice(0, 5) : '',
            post.pickup_date_flexibility_days
                ? `±${post.pickup_date_flexibility_days} days`
                : '',
        ]
            .filter(Boolean)
            .join(' · ') || undefined;

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
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            post.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                        className="capitalize"
                                    >
                                        {post.status}
                                    </Badge>
                                    {post.cargo_type?.name && (
                                        <Badge variant="outline">
                                            {post.cargo_type.name}
                                        </Badge>
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        ULID {post.ulid.slice(0, 8)}
                                        {post.created_at
                                            ? ` • ${new Date(post.created_at).toLocaleDateString()}`
                                            : ''}
                                    </span>
                                </div>

                                <h1 className="flex items-center gap-3 text-3xl font-bold tracking-tight">
                                    <MapPin className="size-7 text-primary" />
                                    {post.origin?.name ?? 'Origin'}{' '}
                                    <ArrowRight className="size-5 text-muted-foreground" />
                                    {post.destination?.name ?? 'Destination'}
                                </h1>

                                <p className="text-muted-foreground">
                                    {post.origin?.region_name ?? ''}
                                    {post.origin?.region_name &&
                                    post.destination?.region_name
                                        ? ' → '
                                        : ''}
                                    {post.destination?.region_name ?? ''}
                                    {post.truck_type?.name
                                        ? ` • ${post.truck_type.name}`
                                        : ''}
                                    {post.truck_count
                                        ? ` ×${post.truck_count}`
                                        : ''}
                                    {post.cargo_type?.name
                                        ? ` • ${post.cargo_type.name}`
                                        : ''}
                                </p>
                            </div>

                            {/* The three facts a carrier decides on — the money,
                                the lane, the window — promoted out of the rows
                                below. The tariff used to be typeset the same as
                                a coordinate pair. */}
                            <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <Stat
                                    peak
                                    label="Tariff"
                                    value={
                                        post.tariff_amount
                                            ? `${post.tariff_currency ?? 'ETB'} ${post.tariff_amount}`
                                            : 'Not stated'
                                    }
                                />
                                <Stat label="Route" value={laneFact} />
                                <Stat
                                    label="Pickup"
                                    value={pickupValue}
                                    sub={pickupSub}
                                />
                            </div>

                            <div className="grid items-start gap-6 lg:grid-cols-12">
                                {/* Wide: Freight Details on the left, Consignor Poster
                                    with the action buttons on the right. Compact: they
                                    stack in this order, so the details — what a
                                    transporter actually evaluates — come first and
                                    the call to action follows underneath. */}
                                <div className="lg:col-span-7">
                                    <FreightDetailsCard post={post} />
                                </div>

                                <div className="space-y-4 lg:col-span-5">
                                    <ConsignorPosterCard
                                        owner={post.owner}
                                        ulid={post.ulid}
                                        canReveal={canReveal}
                                        revealedContacts={revealedContacts}
                                        revealBlocked={blocked}
                                        interestId={interestId}
                                        requiresPhone={requiresPhone}
                                        onRevealed={handleReveal}
                                        processing={revealProcessing}
                                        isOwnPost={isOwnPost}
                                        interestsCount={interestsCount}
                                    />

                                    {/* On its own column so the primary call to action is
                                        not buried under a dozen detail rows. */}
                                    {isOwnPost ? (
                                        <>
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
                                        </>
                                    ) : hasInterest ? (
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
                                                        setWithdrawOpen(true)
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
                                                    Express Interest Again
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
                                            onClick={handleExpressInterest}
                                            className="w-full"
                                            size="lg"
                                        >
                                            Express Interest
                                        </Button>
                                    )}

                                    {/* One dialog serves both triggers — the
                                        first expression and a re-expression
                                        after a rejection or withdrawal — since
                                        they POST to the same endpoint. */}
                                    <Dialog
                                        open={interestOpen}
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
                                                        are shared only when
                                                        you choose to reveal
                                                        them.
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
                                                                    'transporter profile',
                                                                ) && (
                                                                <div className="mt-2">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            router.visit(
                                                                                '/my-transporter-profile',
                                                                            )
                                                                        }
                                                                    >
                                                                        Create
                                                                        Transporter
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
                                        open={withdrawOpen}
                                        onOpenChange={setWithdrawOpen}
                                    >
                                        <DialogContent className="sm:max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Withdraw your interest?
                                                </DialogTitle>
                                                <DialogDescription>
                                                    The consignor will see
                                                    your interest as
                                                    withdrawn. You can express
                                                    interest again later, but
                                                    it starts fresh.
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
