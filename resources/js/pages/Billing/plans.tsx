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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/components/ui/toast';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import WorkspaceLayout from '@/layouts/settings/workspace-layout';
import http from '@/lib/http';
import { type BreadcrumbItem, type Plan, type WorkspaceRole } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Sparkles } from 'lucide-react';
import { useState } from 'react';
import PlanQandA from '@/components/marketplace/PlanQandA';

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

interface PlansPageProps {
    plans: Plan[];
    marketplacePlans?: MarketplacePlan[];
    currentPlan: string;
    currentMarketplacePlan?: string;
    currentBillingPeriod: 'weekly' | 'monthly' | 'yearly' | null;
    currentMarketplaceBillingPeriod?: 'weekly' | 'monthly' | 'yearly' | null;
    recommendedPlan: 'pro' | 'business' | 'weekly' | null;
    recommendedBillingPeriod: 'weekly' | 'monthly' | 'yearly' | null;
    fromOnboarding: boolean;
    userRole: WorkspaceRole;
    marketplaceEntitlement?: { can_post: boolean; remaining_posts: number; usage: Record<string, { label: string; count: number; limit: number; percentage: number }> };
}

export default function PlansPage({
    plans,
    marketplacePlans,
    currentPlan,
    currentMarketplacePlan,
    currentBillingPeriod,
    currentMarketplaceBillingPeriod,
    recommendedPlan,
    recommendedBillingPeriod,
    fromOnboarding,
    userRole,
    marketplaceEntitlement,
}: PlansPageProps) {
    const { t } = useTranslations();
    const [billingPeriod, setBillingPeriod] = useState<'weekly' | 'monthly' | 'yearly'>(
        (recommendedBillingPeriod as 'weekly' | 'monthly' | 'yearly') || (currentBillingPeriod as 'weekly' | 'monthly' | 'yearly') || 'monthly',
    );
    const [processing, setProcessing] = useState<string | null>(null);
    const { addToast } = useToast();
    const isOwner = userRole === 'owner';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('billing.title', 'Billing'), href: '/billing' },
        { title: t('billing.plans.title', 'Plans'), href: '/billing/plans' },
    ];

    // Check if this is the exact current plan (same plan AND same billing period)
    const isExactCurrentPlan = (plan: Plan) => {
        if (plan.id === 'free') {
            return currentPlan === 'free';
        }
        return (
            plan.id === currentPlan && billingPeriod === currentBillingPeriod
        );
    };

    // Check if user is on this plan (regardless of billing period)
    const isOnThisPlan = (plan: Plan) => plan.id === currentPlan;

    const handleSubscribe = async (planId: string) => {
        if (!isOwner) return;

        setProcessing(planId);

        try {
            const { data } = await http.post<{
                checkout_url?: string;
                success?: boolean;
                error?: string;
            }>('/billing/subscribe', {
                body: { plan: planId, billing_period: billingPeriod },
            });

            if (data.checkout_url) {
                window.location.href = data.checkout_url;
            } else if (data.success) {
                router.visit('/billing');
            } else if (data.error) {
                addToast(data.error, 'error');
                setProcessing(null);
            } else {
                router.visit('/billing');
            }
        } catch (error) {
            console.error('Subscription error:', error);
            setProcessing(null);
        }
    };

    const handleMarketplaceSubscribe = async (slug: string) => {
        if (!isOwner) return;
        setProcessing(`marketplace-${slug}`);
        try {
            const { data } = await http.post<{ checkout_url?: string; success?: boolean; error?: string }>('/marketplace/checkout', {
                body: { plan: slug, billing_interval: billingPeriod },
            });
            if (data.checkout_url) {
                window.location.href = data.checkout_url;
            } else if (data.success) {
                addToast('Plan activated', 'success');
                router.visit('/billing');
            } else if (data.error) {
                addToast(data.error, 'error');
                setProcessing(null);
            } else {
                router.visit('/billing');
            }
        } catch (e: unknown) {
            const msg = e instanceof Error ? e.message : 'Chapa checkout failed';
            addToast(msg, 'error');
            setProcessing(null);
        }
    };

    const handleCancelSubscription = async () => {
        if (!isOwner) return;
        if (
            !confirm(
                'Are you sure you want to cancel your subscription? You will lose access to premium features at the end of your billing period.',
            )
        ) {
            return;
        }

        setProcessing('free');

        try {
            const { data } = await http.post<{
                success?: boolean;
                error?: string;
            }>('/billing/cancel');

            if (data.success) {
                router.visit('/billing');
            } else {
                addToast(
                    data.error || 'Failed to cancel subscription',
                    'error',
                );
                setProcessing(null);
            }
        } catch (error) {
            console.error('Cancel error:', error);
            setProcessing(null);
        }
    };

    const getButtonText = (plan: Plan) => {
        if (plan.id === 'free') {
            return currentPlan === 'free'
                ? t('billing.plans.current_plan', 'Current Plan')
                : t('billing.plans.downgrade_to_free', 'Downgrade to Free');
        }

        // Exact match: same plan AND same billing period
        if (isExactCurrentPlan(plan)) {
            return t('billing.plans.current_plan', 'Current Plan');
        }

        // Same plan but different billing period
        if (isOnThisPlan(plan)) {
            return billingPeriod === 'yearly'
                ? t('billing.plans.switch_to_yearly', 'Switch to Yearly')
                : t('billing.plans.switch_to_monthly', 'Switch to Monthly');
        }

        // Different plan
        if (currentPlan === 'free') {
            return t('billing.upgrade', 'Upgrade');
        }
        if (currentPlan === 'pro' && plan.id === 'business') {
            return t('billing.upgrade', 'Upgrade');
        }
        if (currentPlan === 'business' && plan.id === 'pro') {
            return t('billing.downgrade', 'Downgrade');
        }

        return t('billing.plans.change_billing_period', 'Switch Plan');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('billing.plans.title', 'Pricing Plans')} />

            <WorkspaceLayout
                title={t('billing.plans.title', 'Pricing Plans')}
                description={t(
                    'billing.plans.description',
                    'Choose the plan that best fits your needs.',
                )}
                fullWidth
            >
                <div className="space-y-6">
                    {fromOnboarding && recommendedPlan && (
                        <Card className="border-primary/40 bg-primary/5">
                            <CardContent className="py-4">
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'billing.plans.onboarding_hint',
                                        'You chose the {{plan}} plan during onboarding. Complete your subscription below or pick a different option.',
                                        {
                                            plan:
                                                recommendedPlan
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                recommendedPlan.slice(1),
                                        },
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Billing Period Toggle — weekly/monthly/yearly */}
                    <div className="flex items-center justify-center">
                        <div className="inline-flex items-center gap-1 rounded-lg border bg-muted p-1">
                            {(['weekly', 'monthly', 'yearly'] as const).map((period) => (
                                <Button
                                    key={period}
                                    variant={billingPeriod === period ? 'default' : 'ghost'}
                                    size="sm"
                                    className="h-7 px-4 text-xs capitalize"
                                    onClick={() => setBillingPeriod(period)}
                                    aria-pressed={billingPeriod === period}
                                >
                                    {period === 'weekly' ? 'Weekly' : period === 'monthly' ? 'Monthly' : 'Yearly'}
                                    {period === 'yearly' && (
                                        <Badge variant="secondary" className="ml-1 border-none bg-primary/10 px-1 py-0 text-[10px] font-bold text-primary">
                                            Save 17%
                                        </Badge>
                                    )}
                                    {period === 'weekly' && <span className="ml-1 text-[10px]">7d</span>}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {marketplacePlans && marketplacePlans.length > 0 && (
                        <div className="space-y-6">
                            <div className="text-center">
                                <h3 className="text-xl font-bold tracking-tight">NumanFreight ETB — Chapa</h3>
                                <p className="text-sm text-muted-foreground">Pay in ETB via Chapa (Telebirr, cards). Verified, ETB invoices.</p>
                                {marketplaceEntitlement && (
                                    <p className="mt-1 text-xs text-muted-foreground">Remaining active posts: {marketplaceEntitlement.remaining_posts === -1 ? 'Unlimited' : marketplaceEntitlement.remaining_posts}</p>
                                )}
                            </div>
                            <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-3 lg:grid-cols-4">
                                {marketplacePlans.map((plan, index) => {
                                    const isCurrent = currentMarketplacePlan === plan.slug && billingPeriod === (currentMarketplaceBillingPeriod ?? billingPeriod);
                                    const price =
                                        billingPeriod === 'weekly'
                                            ? plan.price_etb.weekly
                                            : billingPeriod === 'monthly'
                                              ? plan.price_etb.monthly
                                              : plan.price_etb.yearly;
                                    return (
                                        <Card
                                            key={`mp-${plan.slug}`}
                                            className={`relative flex flex-col border-primary/20 ${isCurrent ? 'z-10 border-primary shadow-md ring-2 ring-primary/20' : plan.popular ? 'border-primary/50 shadow-xl' : 'hover:shadow-lg'}`}
                                        >
                                            {isCurrent ? (
                                                <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                                    <Badge className="gap-1 bg-green-600 px-3 py-1 font-bold shadow-lg">
                                                        <Check className="h-3.5 w-3.5 stroke-[3]" /> Your ETB Plan
                                                    </Badge>
                                                </div>
                                            ) : (
                                                plan.popular && (
                                                    <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                                        <Badge className="gap-1 bg-primary px-3 py-1 font-bold shadow-lg">
                                                            <Sparkles className="h-3.5 w-3.5" /> Popular
                                                        </Badge>
                                                    </div>
                                                )
                                            )}
                                            <CardHeader className="pt-8 text-center">
                                                <CardTitle className="text-2xl font-black tracking-tight">{plan.name}</CardTitle>
                                                <CardDescription className="font-medium text-muted-foreground/80">{plan.description}</CardDescription>
                                                <div className="mt-4">
                                                    <span className="text-4xl font-black tracking-tighter">{Number(price).toLocaleString()} ETB</span>
                                                    <span className="text-lg font-bold text-muted-foreground">
                                                        /{billingPeriod === 'weekly' ? 'wk' : billingPeriod === 'monthly' ? 'mo' : 'yr'}
                                                    </span>
                                                    {billingPeriod === 'weekly' && <span className="ml-2 text-xs font-medium text-muted-foreground">7-day</span>}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="flex-1 px-6 pt-2">
                                                <ul className="space-y-3">
                                                    {plan.features.map((feature, fi) => (
                                                        <li key={fi} className="flex gap-2 text-sm">
                                                            <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
                                                            <span>{feature}</span>
                                                        </li>
                                                    ))}
                                                    <li className="pt-2 text-xs text-muted-foreground">Limits: {Object.entries(plan.limits).map(([k, v]) => `${k}: ${v === -1 ? '∞' : v}`).join(' • ')}</li>
                                                </ul>
                                            </CardContent>
                                            <CardFooter className="px-6 pb-6">
                                                <Button
                                                    className="h-11 w-full font-bold"
                                                    variant={isCurrent ? 'secondary' : 'default'}
                                                    disabled={isCurrent || !isOwner || processing !== null}
                                                    onClick={() => handleMarketplaceSubscribe(plan.slug)}
                                                >
                                                    {processing === `marketplace-${plan.slug}` && <Spinner className="mr-2" />}
                                                    {isCurrent ? 'Current ETB Plan' : Number(price) === 0 ? 'Use Free' : `Pay ${Number(price).toLocaleString()} ETB via Chapa`}
                                                </Button>
                                            </CardFooter>
                                        </Card>
                                    );
                                })}
                            </div>
                            <div className="mx-auto max-w-3xl">
                                <PlanQandA
                                    title="ETB Plans — Q&A"
                                    items={
                                        marketplacePlans.flatMap((p) => p.faq ?? []).length
                                            ? marketplacePlans.flatMap((p) => p.faq as { q: string; a: string }[])
                                            : undefined
                                    }
                                />
                            </div>
                        </div>
                    )}

                    {/* Stripe Plans Grid (legacy USD) */}
                    <div className="mx-auto grid max-w-7xl gap-8 pb-12 md:grid-cols-3">
                        {plans.map((plan, index) => (
                            <Card
                                key={plan.id}
                                className={`glass animate-fade-in-up relative flex flex-col border-primary/20 transition-all duration-500 hover:scale-105 hover:border-primary active:scale-95 delay-${(index + 1) * 100} ${
                                    isExactCurrentPlan(plan)
                                        ? 'z-10 border-primary shadow-md ring-2 ring-primary/20'
                                        : recommendedPlan === plan.id
                                          ? 'border-primary/50 shadow-xl'
                                          : plan.popular
                                            ? 'border-primary/50 shadow-xl'
                                            : 'hover:shadow-lg'
                                }`}
                            >
                                {isExactCurrentPlan(plan) ? (
                                    <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                        <Badge
                                            variant="default"
                                            className="gap-1 bg-green-600 px-3 py-1 font-bold shadow-lg"
                                        >
                                            <Check className="h-3.5 w-3.5 stroke-[3]" />
                                            {t(
                                                'billing.plans.your_plan',
                                                'Your Plan',
                                            )}
                                        </Badge>
                                    </div>
                                ) : (
                                    plan.popular && (
                                        <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                            <Badge className="animate-pulse-premium gap-1 bg-primary px-3 py-1 font-bold shadow-lg">
                                                <Sparkles className="h-3.5 w-3.5" />
                                                {t(
                                                    'billing.plans.most_popular',
                                                    'Most Popular',
                                                )}
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
                                    <div className="mt-6">
                                        <span className="text-5xl font-black tracking-tighter">
                                            $
                                            {billingPeriod === 'monthly'
                                                ? plan.price.monthly
                                                : plan.price.yearly}
                                        </span>
                                        <span className="text-lg font-bold text-muted-foreground">
                                            /
                                            {billingPeriod === 'monthly'
                                                ? t('common.mo', 'mo')
                                                : t('common.yr', 'yr')}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1 px-8 pt-4">
                                    <ul className="space-y-4">
                                        {plan.features.map((feature, index) => (
                                            <li
                                                key={index}
                                                className="flex items-start gap-3 text-sm font-medium text-foreground/80"
                                            >
                                                <div className="mt-0.5 rounded-full bg-green-100 p-0.5 dark:bg-green-900/30">
                                                    <Check className="h-3.5 w-3.5 stroke-[3] text-green-600 dark:text-green-400" />
                                                </div>
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                                <CardFooter className="px-8 pb-8">
                                    {plan.id === 'free' ? (
                                        <Button
                                            variant="outline"
                                            className="h-12 w-full rounded-md font-bold transition-all hover:bg-muted"
                                            disabled={
                                                currentPlan === 'free' ||
                                                !isOwner ||
                                                processing !== null
                                            }
                                            onClick={handleCancelSubscription}
                                        >
                                            {processing === 'free' && (
                                                <Spinner className="mr-2" />
                                            )}
                                            {getButtonText(plan)}
                                        </Button>
                                    ) : (
                                        <Button
                                            variant={
                                                isExactCurrentPlan(plan)
                                                    ? 'secondary'
                                                    : plan.popular
                                                      ? 'default'
                                                      : 'outline'
                                            }
                                            className={`h-12 w-full rounded-md font-bold transition-all active:scale-95 ${
                                                !isExactCurrentPlan(plan) &&
                                                (plan.popular ||
                                                    plan.id === recommendedPlan)
                                                    ? 'animate-pulse-premium border-none px-8 shadow-lg shadow-primary/20'
                                                    : ''
                                            }`}
                                            disabled={
                                                isExactCurrentPlan(plan) ||
                                                !isOwner ||
                                                processing !== null
                                            }
                                            onClick={() =>
                                                handleSubscribe(plan.id)
                                            }
                                        >
                                            {processing === plan.id && (
                                                <Spinner className="mr-2" />
                                            )}
                                            {getButtonText(plan)}
                                        </Button>
                                    )}
                                </CardFooter>
                            </Card>
                        ))}
                    </div>

                    {/* FAQ or Additional Info */}
                    <Card className="mt-8">
                        <CardHeader>
                            <CardTitle>
                                {t(
                                    'billing.plans.faq.title',
                                    'Frequently Asked Questions',
                                )}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <h4 className="font-medium">
                                    {t(
                                        'billing.plans.faq.change_plans',
                                        'Can I change plans later?',
                                    )}
                                </h4>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'billing.plans.faq.change_plans_answer',
                                        'Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately and are prorated.',
                                    )}
                                </p>
                            </div>
                            <div>
                                <h4 className="font-medium">
                                    {t(
                                        'billing.plans.faq.payment_methods',
                                        'What payment methods do you accept?',
                                    )}
                                </h4>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'billing.plans.faq.payment_methods_answer',
                                        'We accept all major credit cards (Visa, MasterCard, American Express) through our secure payment processor, Stripe.',
                                    )}
                                </p>
                            </div>
                            <div>
                                <h4 className="font-medium">
                                    {t(
                                        'billing.plans.faq.cancel',
                                        'Can I cancel my subscription?',
                                    )}
                                </h4>
                                <p className="text-sm text-muted-foreground">
                                    {t(
                                        'billing.plans.faq.cancel_answer',
                                        "Yes, you can cancel your subscription at any time. You'll continue to have access to paid features until the end of your billing period.",
                                    )}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {!isOwner && (
                        <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950/50">
                            <CardContent className="py-4">
                                <p className="text-sm text-yellow-800 dark:text-yellow-200">
                                    {t(
                                        'billing.plans.only_owner',
                                        'Only the workspace owner can manage billing and subscriptions.',
                                    )}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </WorkspaceLayout>
        </AppLayout>
    );
}
