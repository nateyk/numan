import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, Sparkles } from 'lucide-react';
import { useState } from 'react';

interface MarketplacePlan {
    slug: string;
    name: string;
    description: string | null;
    price_weekly_etb: number;
    price_monthly_etb: number;
    price_yearly_etb: number;
    is_popular: boolean;
    features: string[];
    limits: Record<string, number>;
}

export function LandingPricing({ plans = [] }: { plans?: MarketplacePlan[] }) {
    const { auth } = usePage<SharedData>().props;
    const { t } = useTranslations();
    const [period, setPeriod] = useState<'weekly' | 'monthly' | 'yearly'>('monthly');

    const displayPrice = (plan: MarketplacePlan) => {
        if (plan.slug === 'weekly') return plan.price_weekly_etb;
        if (period === 'weekly') return plan.price_weekly_etb || plan.price_monthly_etb;
        if (period === 'monthly') return plan.price_monthly_etb;
        return plan.price_yearly_etb;
    };

    const periodLabel = period === 'weekly' ? '/wk' : period === 'monthly' ? '/mo' : '/yr';
    const hasPlans = plans && plans.length > 0;

    return (
        <section id="pricing" className="bg-muted/30 py-20 sm:py-32">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-3xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">
                        Simple <span className="text-primary">ETB</span> pricing for every fleet
                    </h2>
                    <p className="mt-4 text-lg text-muted-foreground">Weekly 7-day, Free, Pro & Business — pay in ETB via Chapa (Telebirr, cards). No hidden fees.</p>
                    {hasPlans && (
                        <div className="mt-6 flex justify-center">
                            <div className="inline-flex items-center gap-1 rounded-lg border bg-background p-1">
                                {(['weekly', 'monthly', 'yearly'] as const).map((p) => (
                                    <Button
                                        key={p}
                                        variant={period === p ? 'default' : 'ghost'}
                                        size="sm"
                                        className="h-7 px-3 text-xs capitalize"
                                        onClick={() => setPeriod(p)}
                                        aria-pressed={period === p}
                                    >
                                        {p === 'weekly' ? 'Weekly 7d' : p === 'monthly' ? 'Monthly' : 'Yearly'}
                                        {p === 'yearly' && <span className="ml-1 text-[10px]">Save 17%</span>}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {hasPlans ? (
                    <div className="mx-auto mt-12 grid max-w-7xl gap-6 md:grid-cols-2 lg:grid-cols-4">
                        {plans.map((plan) => {
                            const price = displayPrice(plan);
                            const isWeekly = plan.slug === 'weekly';
                            return (
                                <Card key={plan.slug} className={`relative flex flex-col ${plan.is_popular ? 'border-primary shadow-lg ring-1 ring-primary/20' : 'border-border'} ${isWeekly ? 'bg-amber-50/50 dark:bg-amber-950/10' : ''}`}>
                                    {plan.is_popular && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <Badge className="gap-1 bg-primary px-3 py-1 font-bold"><Sparkles className="h-3 w-3" /> Popular</Badge>
                                        </div>
                                    )}
                                    {isWeekly && !plan.is_popular && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <Badge variant="secondary" className="border-amber-200 bg-amber-100 px-3 py-1 font-bold text-amber-800">7-Day</Badge>
                                        </div>
                                    )}
                                    <CardHeader className="pt-8 text-center">
                                        <CardTitle className="text-xl font-bold">{plan.name}</CardTitle>
                                        <CardDescription className="min-h-[40px] text-sm">{plan.description}</CardDescription>
                                        <div className="mt-4">
                                            <span className="text-3xl font-black tracking-tight">{Number(price).toLocaleString()} ETB</span>
                                            <span className="text-sm font-bold text-muted-foreground">{isWeekly ? '/7d' : periodLabel}</span>
                                        </div>
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {isWeekly ? '7-day validity' : period === 'yearly' ? `${(Number(plan.price_yearly_etb) / 12).toFixed(0)} ETB/mo billed yearly` : 'Billed monthly via Chapa'}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="flex-1 space-y-3 px-6">
                                        <ul className="space-y-2">
                                            {plan.features.map((f, i) => (
                                                <li key={i} className="flex gap-2 text-sm">
                                                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-green-600" aria-hidden="true" />
                                                    <span>{f}</span>
                                                </li>
                                            ))}
                                        </ul>
                                        <div className="rounded-lg border bg-muted/30 px-3 py-2 text-xs">
                                            <div className="flex flex-wrap gap-2 text-muted-foreground">
                                                {Object.entries(plan.limits).map(([k, v]) => (
                                                    <span key={k}>
                                                        {k.replace(/_/g, ' ')}: <span className="font-medium text-foreground">{v === -1 ? '∞' : v}</span>
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    </CardContent>
                                    <CardFooter className="px-6 pb-6">
                                        <Button asChild className="h-10 w-full font-bold" variant={plan.is_popular ? 'default' : 'outline'}>
                                            <Link href={auth.user ? '/billing/plans' : register()}>
                                                {Number(price) === 0 ? 'Get Started Free' : `Get ${plan.name} — ${Number(price).toLocaleString()} ETB`}
                                                <ArrowRight className="ml-2 h-4 w-4" aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </CardFooter>
                                </Card>
                            );
                        })}
                    </div>
                ) : (
                    <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            { title: '100% Free', desc: 'No subscriptions, no per-project fees.' },
                            { title: 'MIT License', desc: 'Commercial use welcome.' },
                            { title: 'Production Ready', desc: 'Battle-tested Laravel best practices.' },
                        ].map((item) => (
                            <Card key={item.title} className="border-border">
                                <CardHeader>
                                    <CardTitle className="text-xl">{item.title}</CardTitle>
                                    <CardDescription>{item.desc}</CardDescription>
                                </CardHeader>
                            </Card>
                        ))}
                    </div>
                )}

                <div className="mt-8 text-center">
                    <p className="text-sm text-muted-foreground">
                        Pay in ETB via <span className="font-medium text-foreground">Chapa</span> (Telebirr, Visa, Mastercard) • ETB invoices • 7-day Weekly or monthly/yearly
                    </p>
                </div>

                <div className="mt-10 text-center">
                    <Button size="lg" asChild className="h-12 px-8 text-base">
                        <Link href={auth.user ? '/billing/plans' : register()}>
                            {t('landing.pricing.cta.get_started', 'Get Started Free')}
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                    <p className="mt-4 text-sm text-muted-foreground">
                        {t('landing.pricing.cta.or', 'Or')}{' '}
                        <a href="https://github.com/xco-agency/laravel-saas-starter" target="_blank" rel="noopener noreferrer" className="font-medium text-primary hover:underline">
                            {t('landing.pricing.cta.github', 'view on GitHub')}
                        </a>
                    </p>
                </div>
            </div>
        </section>
    );
}
