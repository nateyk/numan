import PlanQandA from '@/components/marketplace/PlanQandA';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/components/ui/toast';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import WorkspaceLayout from '@/layouts/settings/workspace-layout';
import http from '@/lib/http';
import { type BreadcrumbItem, type WorkspaceRole } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Sparkles } from 'lucide-react';
import { useState } from 'react';

type BillingPeriod = 'weekly' | 'monthly' | 'yearly';

interface MarketplacePlan {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    price: { weekly: number; monthly: number; yearly: number };
    price_etb: { weekly: number; monthly: number; yearly: number };
    features: string[];
    limits: Record<string, number>;
    faq?: Array<{ q: string; a: string }>;
    popular?: boolean;
}

interface FreeLimits {
    free_active_posts: number;
    free_interests_per_week: number;
    free_contact_reveals: number;
}

interface PlansPageProps {
    marketplacePlans?: MarketplacePlan[];
    freeLimits?: FreeLimits;
    currentMarketplacePlan?: string;
    currentMarketplaceBillingPeriod?: BillingPeriod | null;
    pendingChapaReference?: string | null;
    marketplaceAccess?: {
        status: 'free' | 'active' | 'grace' | 'expired';
        plan: string | null;
        ends_at: string | null;
        billing_interval: BillingPeriod | null;
    };
    recommendedBillingPeriod?: BillingPeriod | null;
    fromOnboarding: boolean;
    userRole: WorkspaceRole;
    marketplaceEntitlement?: {
        can_post: boolean;
        remaining_posts: number;
        usage: Record<
            string,
            { label: string; count: number; limit: number; percentage: number }
        >;
    };
    /**
     * Manual payment sits beside Chapa rather than replacing it. Absent means
     * nothing is configured, in which case the entry point stays hidden.
     */
    manualPayment?: {
        enabled: boolean;
        methods: Record<
            string,
            { label: string; fields: Record<string, string> }
        >;
        index_url: string;
        expiry_days: number;
    };
}

const formatLimit = (value: number): string =>
    value === -1 ? 'Unlimited' : String(value);

export default function PlansPage({
    marketplacePlans = [],
    freeLimits,
    currentMarketplacePlan,
    currentMarketplaceBillingPeriod,
    pendingChapaReference,
    marketplaceAccess,
    recommendedBillingPeriod,
    fromOnboarding,
    userRole,
    marketplaceEntitlement,
    manualPayment,
}: PlansPageProps) {
    const { t } = useTranslations();
    const [billingPeriod, setBillingPeriod] = useState<BillingPeriod>(
        (recommendedBillingPeriod as BillingPeriod) ||
            (currentMarketplaceBillingPeriod as BillingPeriod) ||
            marketplaceAccess?.billing_interval ||
            'monthly',
    );
    const [processing, setProcessing] = useState<string | null>(null);
    const [pendingReference, setPendingReference] = useState<string | null>(
        pendingChapaReference ?? null,
    );
    const [pendingNotice, setPendingNotice] = useState<string | null>(
        pendingChapaReference
            ? 'A previous Chapa checkout is unresolved. Check its status before attempting another payment.'
            : null,
    );
    const [checkingPayment, setCheckingPayment] = useState(false);
    const { addToast } = useToast();
    const isOwner = userRole === 'owner';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('billing.title', 'Billing'), href: '/billing' },
        { title: t('billing.plans.title', 'Plans'), href: '/billing/plans' },
    ];

    const free: FreeLimits = freeLimits ?? {
        free_active_posts: 5,
        free_interests_per_week: 3,
        free_contact_reveals: 5,
    };

    /**
     * Yearly savings versus paying monthly twelve times. Computed, never
     * hardcoded, so it stays correct when an admin changes a price.
     */
    const yearlySavingsPercent = (): number | null => {
        const monthly = Number(marketplacePlans[0]?.price_etb.monthly ?? 0);
        const yearly = Number(marketplacePlans[0]?.price_etb.yearly ?? 0);
        const fullYearCost = monthly * 12;

        if (fullYearCost <= 0 || yearly <= 0 || yearly >= fullYearCost) {
            return null;
        }

        return Math.round(((fullYearCost - yearly) / fullYearCost) * 100);
    };

    const handleSubscribe = async (slug: string) => {
        if (!isOwner) return;

        if (pendingReference) {
            addToast(
                'Check your pending payment before starting another checkout.',
                'error',
            );
            return;
        }

        setProcessing(slug);

        try {
            const { data } = await http.post<{
                checkout_url?: string;
                success?: boolean;
                error?: string;
                message?: string;
                retryable?: boolean;
                tx_ref?: string;
            }>('/marketplace/checkout', {
                body: { plan: slug, billing_interval: billingPeriod },
            });

            if (data?.checkout_url) {
                window.location.href = data.checkout_url;
                return;
            }

            // A gateway timeout is indeterminate: keep this reference so the
            // customer can check the same payment before starting another.
            if (data?.retryable && typeof data.tx_ref === 'string') {
                setPendingReference(data.tx_ref);
                setPendingNotice(
                    data.error ??
                        'Chapa is temporarily unavailable. Check your payment before retrying.',
                );
                setProcessing(null);
                return;
            }

            // Server refused the checkout (validation, bad prices, or the
            // payment provider rejected initialization). Never crash here:
            // surface whatever message came back and stay on the page.
            // Coerced to a string because provider payloads have arrived as
            // objects before, which React cannot render.
            const serverMessage = data?.error ?? data?.message;
            addToast(
                typeof serverMessage === 'string' && serverMessage !== ''
                    ? serverMessage
                    : 'Checkout failed. Please try again.',
                'error',
            );

            setProcessing(null);
        } catch (error) {
            console.error('Checkout error:', error);
            addToast(
                'Could not reach the payment server. Check your connection and try again.',
                'error',
            );
            setProcessing(null);
        }
    };

    const checkPendingPayment = async () => {
        if (!pendingReference || checkingPayment) return;

        setCheckingPayment(true);
        try {
            const { data } = await http.post<{
                status?: string;
                message?: string;
            }>(
                `/marketplace/payments/${encodeURIComponent(pendingReference)}/verify`,
            );

            if (data?.status === 'verified') {
                window.location.href = '/billing';
                return;
            }

            setPendingNotice(
                data?.message ??
                    'Payment confirmation is still pending. Keep your reference and check later.',
            );
            if (data?.status === 'canceled') {
                setPendingReference(null);
            }
        } catch {
            setPendingNotice(
                'Unable to check Chapa right now. Keep your reference and try later.',
            );
        } finally {
            setCheckingPayment(false);
        }
    };

    const savings = yearlySavingsPercent();
    const periodSuffix =
        billingPeriod === 'weekly'
            ? 'wk'
            : billingPeriod === 'monthly'
              ? 'mo'
              : 'yr';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('billing.plans.title', 'Pricing Plans')} />

            <WorkspaceLayout
                title={t('billing.plans.title', 'Pricing Plans')}
                description={t(
                    'billing.plans.description',
                    'One plan, three durations. Same features throughout.',
                )}
                fullWidth
            >
                <div className="space-y-6">
                    {marketplaceAccess?.status === 'expired' && (
                        <div
                            role="status"
                            className="mx-auto max-w-5xl rounded-lg border px-4 py-3"
                        >
                            <p className="text-sm font-medium">
                                Your paid access has expired
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Freight discovery is paused until you renew.
                                Select a duration below to restore access after
                                Chapa confirms payment.
                            </p>
                        </div>
                    )}
                    {marketplaceAccess?.status === 'grace' && (
                        <div
                            role="status"
                            className="mx-auto max-w-5xl rounded-lg border px-4 py-3"
                        >
                            <p className="text-sm font-medium">
                                Your plan is canceled but still active
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                You can browse freight until your paid period
                                ends. Renew now to preserve the days remaining.
                            </p>
                        </div>
                    )}
                    {fromOnboarding && (
                        <Card className="border-primary/40 bg-primary/5">
                            <CardContent className="py-4">
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'billing.plans.onboarding_hint',
                                        'Choose a duration below to finish setting up your account.',
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center justify-center">
                        <div className="inline-flex items-center gap-1 rounded-lg border bg-muted p-1">
                            {(['weekly', 'monthly', 'yearly'] as const).map(
                                (period) => (
                                    <Button
                                        key={period}
                                        variant={
                                            billingPeriod === period
                                                ? 'default'
                                                : 'ghost'
                                        }
                                        size="sm"
                                        className="h-7 px-4 text-xs capitalize"
                                        onClick={() => setBillingPeriod(period)}
                                        aria-pressed={billingPeriod === period}
                                    >
                                        {period === 'weekly'
                                            ? 'Weekly'
                                            : period === 'monthly'
                                              ? 'Monthly'
                                              : 'Yearly'}
                                        {period === 'weekly' && (
                                            <span className="ml-1 text-[10px]">
                                                7d
                                            </span>
                                        )}
                                        {period === 'yearly' &&
                                            savings !== null && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-1 border-none bg-primary/10 px-1 text-[10px] font-bold text-primary"
                                                >
                                                    Save {savings}%
                                                </Badge>
                                            )}
                                    </Button>
                                ),
                            )}
                        </div>
                    </div>

                    {pendingReference && (
                        <div
                            role="status"
                            className="mx-auto flex max-w-5xl flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div className="min-w-0 space-y-1">
                                <p className="text-sm font-medium">
                                    Check this payment before paying again
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {pendingNotice}
                                </p>
                                <p className="font-mono text-xs break-all text-muted-foreground">
                                    Reference: {pendingReference}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <Button
                                    size="sm"
                                    disabled={checkingPayment}
                                    onClick={checkPendingPayment}
                                >
                                    {checkingPayment
                                        ? 'Checking…'
                                        : 'Check status'}
                                </Button>
                                <Button size="sm" variant="outline" asChild>
                                    <Link href="/billing">Billing overview</Link>
                                </Button>
                            </div>
                        </div>
                    )}

                    <div className="mx-auto grid max-w-5xl gap-8 pb-6 md:grid-cols-2">
                        {/* Free — numbers come from marketplace_settings */}
                        <Card
                            className={`relative flex flex-col ${
                                currentMarketplacePlan === 'free' &&
                                marketplaceAccess?.status !== 'expired'
                                    ? 'border-primary shadow-md ring-2 ring-primary/20'
                                    : ''
                            }`}
                        >
                            {currentMarketplacePlan === 'free' &&
                                marketplaceAccess?.status !== 'expired' && (
                                    <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                        <Badge className="gap-1 bg-green-600 px-3 py-1 font-bold shadow-lg">
                                            <Check className="h-3.5 w-3.5 stroke-[3]" />{' '}
                                            Your Plan
                                        </Badge>
                                    </div>
                                )}
                            <CardHeader className="pt-8 text-center">
                                <CardTitle className="text-2xl font-black tracking-tight">
                                    Free
                                </CardTitle>
                                <CardDescription className="font-medium text-muted-foreground/80">
                                    Free accounts can browse and post within the
                                    plan limits
                                </CardDescription>
                                <div className="mt-4">
                                    <span className="text-4xl font-black tracking-tighter">
                                        0 ETB
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="flex-1 px-6 pt-2">
                                <ul className="space-y-3">
                                    {[
                                        `${formatLimit(free.free_active_posts)} active freight post${
                                            free.free_active_posts === 1
                                                ? ''
                                                : 's'
                                        }`,
                                        `${formatLimit(free.free_contact_reveals)} contact reveal${
                                            free.free_contact_reveals === 1
                                                ? ''
                                                : 's'
                                        } (one-time)`,
                                    ].map((label) => (
                                        <li
                                            key={label}
                                            className="flex gap-2 text-sm"
                                        >
                                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                            <span>{label}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                            <CardFooter className="px-6 pb-6">
                                <Button
                                    className="h-11 w-full font-bold"
                                    variant={
                                        currentMarketplacePlan === 'free' &&
                                        marketplaceAccess?.status !== 'expired'
                                            ? 'secondary'
                                            : 'outline'
                                    }
                                    disabled
                                >
                                    {currentMarketplacePlan === 'free' &&
                                    marketplaceAccess?.status !== 'expired'
                                        ? 'Current Plan'
                                        : 'Free tier'}
                                </Button>
                            </CardFooter>
                        </Card>

                        {marketplacePlans.map((plan) => {
                            const price = Number(plan.price_etb[billingPeriod]);
                            const isCurrent =
                                currentMarketplacePlan === plan.slug &&
                                currentMarketplaceBillingPeriod ===
                                    billingPeriod;
                            const isOnPlan =
                                currentMarketplacePlan === plan.slug;

                            return (
                                <Card
                                    key={plan.slug}
                                    className={`relative flex flex-col ${
                                        isCurrent
                                            ? 'border-primary shadow-md ring-2 ring-primary/20'
                                            : plan.popular
                                              ? 'border-primary/50 shadow-xl'
                                              : ''
                                    }`}
                                >
                                    {isCurrent ? (
                                        <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                            <Badge className="gap-1 bg-green-600 px-3 py-1 font-bold shadow-lg">
                                                <Check className="h-3.5 w-3.5 stroke-[3]" />{' '}
                                                Your Plan
                                            </Badge>
                                        </div>
                                    ) : (
                                        plan.popular && (
                                            <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                                <Badge className="gap-1 bg-primary px-3 py-1 font-bold shadow-lg">
                                                    <Sparkles className="h-3.5 w-3.5" />{' '}
                                                    Popular
                                                </Badge>
                                            </div>
                                        )
                                    )}
                                    <CardHeader className="pt-8 text-center">
                                        <CardTitle className="text-2xl font-black tracking-tight">
                                            {plan.name}
                                        </CardTitle>
                                        <CardDescription className="font-medium text-muted-foreground/80">
                                            {plan.description}
                                        </CardDescription>
                                        <div className="mt-4">
                                            <span className="text-4xl font-black tracking-tighter">
                                                {price.toLocaleString()} ETB
                                            </span>
                                            <span className="text-lg font-bold text-muted-foreground">
                                                /{periodSuffix}
                                            </span>
                                            {billingPeriod === 'weekly' && (
                                                <span className="ml-2 text-xs font-medium text-muted-foreground">
                                                    7-day
                                                </span>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 px-6 pt-2">
                                        <ul className="space-y-3">
                                            {plan.features.map(
                                                (feature, index) => (
                                                    <li
                                                        key={index}
                                                        className="flex gap-2 text-sm"
                                                    >
                                                        <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                                        <span>{feature}</span>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </CardContent>
                                    <CardFooter className="px-6 pb-6">
                                        <Button
                                            className="h-11 w-full font-bold"
                                            variant={
                                                isCurrent
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                            disabled={
                                                !isOwner ||
                                                processing !== null ||
                                                pendingReference !== null
                                            }
                                            onClick={() =>
                                                handleSubscribe(plan.slug)
                                            }
                                        >
                                            {processing === plan.slug && (
                                                <Spinner className="mr-2" />
                                            )}
                                            {isOnPlan
                                                ? 'Extend plan'
                                                : marketplaceAccess?.status ===
                                                    'expired'
                                                  ? 'Renew plan'
                                                  : `Subscribe — ${price.toLocaleString()} ETB`}
                                        </Button>
                                        {manualPayment?.enabled && (
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                disabled={
                                                    !isOwner ||
                                                    processing !== null
                                                }
                                                onClick={() =>
                                                    router.visit(
                                                        `/marketplace/manual/${plan.slug}/${billingPeriod}`,
                                                    )
                                                }
                                            >
                                                Pay manually
                                            </Button>
                                        )}
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>

                    {manualPayment?.enabled && (
                        <div className="mx-auto max-w-3xl rounded-md border bg-muted/20 p-4 text-sm">
                            <p className="font-medium">Prefer to transfer?</p>
                            <p className="mt-1 text-muted-foreground">
                                Pay by{' '}
                                {Object.values(manualPayment.methods)
                                    .map((m) => m.label)
                                    .join(', ')}
                                . You will get the account details and a
                                reference to put in the transfer note. We
                                confirm within {manualPayment.expiry_days} days,
                                and nothing is activated until an administrator
                                confirms your payment.
                            </p>
                            <Button
                                asChild
                                variant="link"
                                className="mt-2 h-auto p-0"
                            >
                                <Link href={manualPayment.index_url}>
                                    See your manual payment requests
                                </Link>
                            </Button>
                        </div>
                    )}

                    {marketplaceEntitlement && (
                        <p className="text-center text-xs text-muted-foreground">
                            Remaining live posts:{' '}
                            {marketplaceEntitlement.remaining_posts === -1
                                ? 'Unlimited'
                                : marketplaceEntitlement.remaining_posts}
                        </p>
                    )}

                    <div className="mx-auto max-w-3xl">
                        <PlanQandA
                            title="Plans — Q&A"
                            items={
                                marketplacePlans.flatMap((p) => p.faq ?? [])
                                    .length
                                    ? (marketplacePlans.flatMap(
                                          (p) => p.faq ?? [],
                                      ) as Array<{ q: string; a: string }>)
                                    : undefined
                            }
                        />
                    </div>
                </div>
            </WorkspaceLayout>
        </AppLayout>
    );
}
