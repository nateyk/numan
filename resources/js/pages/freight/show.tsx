import ConsignorPosterCard, {
    type PostOwner,
    type RevealBlocked,
    type RevealedContacts,
} from '@/components/freight/consignor-poster-card';
import FreightDetailsCard, {
    type FreightDetailsPost,
} from '@/components/freight/freight-details-card';
import { LandingFooter, LandingHeader } from '@/components/landing';
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
    // data, and nothing here decides whether it may.
    const { post: postReveal, processing: revealProcessing } = useForm({});

    const handleReveal = () => {
        if (interestId === null) return;

        postReveal(`/interests/${interestId}/reveal`, {
            preserveScroll: true,
            onSuccess: () => {
                // The reveal needs a phone of the viewer's own, or the consignor
                // would be handed somebody they cannot call back. The shared
                // layout renders the inline field for it.
                if (requiresPhone) {
                    toast.error(
                        'Add your phone number before revealing a contact.',
                    );

                    return;
                }

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

                if (msg) {
                    toast.error(msg);

                    if (
                        String(msg)
                            .toLowerCase()
                            .includes('transporter profile')
                    ) {
                        setTimeout(
                            () => router.visit('/my-transporter-profile'),
                            1500,
                        );
                    }
                }
            },
        });
    };

    const blocked = (revealBlocked ?? null) as RevealBlocked;

    return (
        <>
            <Head
                title={`${post.origin?.name ?? 'Freight'} → ${post.destination?.name ?? ''} — NumanFreight`}
            />

            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />

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

                            <div className="grid items-start gap-6 lg:grid-cols-12">
                                {/* Compact: one column, Consignor first. Wide: details
                                    on the left, poster on the right. */}
                                <div className="space-y-4 lg:col-span-7">
                                    <ConsignorPosterCard
                                        owner={post.owner}
                                        ulid={post.ulid}
                                        canReveal={canReveal}
                                        revealedContacts={revealedContacts}
                                        revealBlocked={blocked}
                                        interestId={interestId}
                                        onRevealed={handleReveal}
                                        processing={revealProcessing}
                                        isOwnPost={isOwnPost}
                                        interestsCount={interestsCount}
                                    />

                                    <FreightDetailsCard post={post} />
                                </div>

                                <div className="space-y-4 lg:col-span-5">
                                    {/* The action block lives on its own on wide, so the
                                        primary call to action is not buried below a
                                        dozen detail rows. */}
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
                                            <Badge
                                                variant="secondary"
                                                className="justify-center py-2"
                                            >
                                                Interest{' '}
                                                {interestStatus ?? 'submitted'}{' '}
                                                — awaiting consignor
                                            </Badge>
                                            <Button
                                                variant="outline"
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
                                        <Dialog
                                            open={interestOpen}
                                            onOpenChange={setInterestOpen}
                                        >
                                            <Button
                                                onClick={handleExpressInterest}
                                                className="w-full"
                                                size="lg"
                                            >
                                                Express Interest
                                            </Button>
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
                                                        Provide your contact
                                                        details for the
                                                        consignor to contact
                                                        you.
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
                                        </Dialog>
                                    )}

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

                <LandingFooter />
            </div>
        </>
    );
}
