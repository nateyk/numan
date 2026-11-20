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
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
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

interface FreeLimits {
    free_active_posts: number;
    free_interests_per_week: number;
    free_contact_reveals: number;
}

type Period = 'weekly' | 'monthly' | 'yearly';

function formatLimit(value: number): string {
    return value === -1 ? 'Unlimited' : value.toLocaleString('en-ET');
}

export function LandingPricing({
    plans = [],
    freeLimits,
}: {
    plans?: MarketplacePlan[];
    freeLimits?: FreeLimits;
}) {
    const { t } = useTranslations();
    const [period, setPeriod] = useState<Period>('monthly');
    const periods: Period[] = ['weekly', 'monthly', 'yearly'];
    const priceKey = {
        weekly: 'price_weekly_etb',
        monthly: 'price_monthly_etb',
        yearly: 'price_yearly_etb',
    } as const;

    return (
        <section id="pricing" className="scroll-mt-20 border-y border-border bg-muted/30 py-16 sm:py-24">
            <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-2xl text-center">
                    <Badge variant="outline" className="mb-4 uppercase tracking-widest">{t('brand.home.pricing.eyebrow', 'Marketplace access')}</Badge>
                    <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('brand.home.pricing.title', 'Choose the access that works for you.')}
                    </h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        {t('brand.home.pricing.description', 'Review current marketplace plans and limits. Prices are displayed in Ethiopian Birr and come from the platform settings.')}
                    </p>
                </div>
                {plans.length > 0 && (
                    <div role="group" aria-label={t('brand.home.pricing.period', 'Billing period')} className="mx-auto mt-8 flex w-fit flex-wrap items-center gap-1 rounded-lg border border-border bg-card p-1">
                        {periods.map((option) => (
                            <Button
                                key={option}
                                type="button"
                                variant={period === option ? 'default' : 'ghost'}
                                size="sm"
                                aria-pressed={period === option}
                                onClick={() => setPeriod(option)}
                                className="capitalize"
                            >
                                {t('brand.home.pricing.' + option, option)}
                            </Button>
                        ))}
                    </div>
                )}

                <div className={'mx-auto mt-10 grid max-w-5xl gap-4 ' + (plans.length ? 'md:grid-cols-2' : 'max-w-md')}>
                    <Card className="flex flex-col gap-5">
                        <CardHeader>
                            <CardTitle className="text-lg font-semibold">{t('brand.home.pricing.free', 'Free')}</CardTitle>
                            <CardDescription>{t('brand.home.pricing.free_description', 'Get started with the marketplace and your account.')}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-1 flex-col gap-6">
                            <div className="flex items-baseline gap-2">
                                <span className="text-4xl font-bold tracking-tight tabular-nums">0</span>
                                <span className="text-sm text-muted-foreground">ETB</span>
                            </div>
                            {freeLimits && (
                                <ul className="space-y-3 text-sm">
                                    {[
                                        t('brand.home.pricing.free_posts', '{{value}} active posts', { value: formatLimit(freeLimits.free_active_posts) }),
                                        t('brand.home.pricing.free_reveals', '{{value}} contact reveals', { value: formatLimit(freeLimits.free_contact_reveals) }),
                                    ].map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <Check aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand-sky-strong dark:text-brand-sky" />
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                        <CardFooter>
                            <Button asChild variant="outline" className="w-full">
                                <Link href="/freight">{t('brand.home.nav.browse', 'Browse freight')}</Link>
                            </Button>
                        </CardFooter>
                    </Card>
                    {plans.map((plan) => {
                        const price = Number(plan[priceKey[period]]);
                        return (
                            <Card key={plan.slug} className={'flex flex-col gap-5 ' + (plan.is_popular ? 'border-primary ring-1 ring-primary/20' : '')}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <CardTitle className="text-lg font-semibold">{plan.name}</CardTitle>
                                        {plan.is_popular && <Badge variant="secondary">{t('brand.home.pricing.popular', 'Popular')}</Badge>}
                                    </div>
                                    {plan.description && <CardDescription>{plan.description}</CardDescription>}
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col gap-6">
                                    <div className="flex flex-wrap items-baseline gap-2">
                                        <span className="text-4xl font-bold tracking-tight tabular-nums">{price.toLocaleString('en-ET')}</span>
                                        <span className="text-sm text-muted-foreground">ETB {period === 'weekly' ? '/ week' : period === 'monthly' ? '/ month' : '/ year'}</span>
                                    </div>
                                    <ul className="space-y-3 text-sm">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-2">
                                                <Check aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-brand-sky-strong dark:text-brand-sky" />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    <Button asChild className="w-full"><Link href="/register">{t('brand.home.pricing.choose', 'View plan')}</Link></Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
                <p className="mt-6 text-center text-xs leading-6 text-muted-foreground">
                    {t('brand.home.pricing.note', 'Availability and account entitlements are confirmed after sign-in.')}
                </p>
            </div>
        </section>
    );
}
