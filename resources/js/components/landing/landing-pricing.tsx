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
import { Check, Sparkles } from 'lucide-react';
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

const formatLimit = (value: number): string =>
    value === -1 ? 'Unlimited' : String(value);

export function LandingPricing({
    plans = [],
    freeLimits,
}: {
    plans?: MarketplacePlan[];
    freeLimits?: FreeLimits;
}) {
    const [period, setPeriod] = useState<Period>('monthly');

    const free: FreeLimits = freeLimits ?? {
        free_active_posts: 5,
        free_interests_per_week: 3,
        free_contact_reveals: 5,
    };

    const priceFor = (plan: MarketplacePlan, chosen: Period): number => {
        if (chosen === 'weekly') return Number(plan.price_weekly_etb);
        if (chosen === 'monthly') return Number(plan.price_monthly_etb);
        return Number(plan.price_yearly_etb);
    };

    /** Computed from the prices, so it stays right when an admin edits them. */
    const savingsPercent = (): number | null => {
        const plan = plans[0];
        if (!plan) return null;

        const monthly = Number(plan.price_monthly_etb);
        const yearly = Number(plan.price_yearly_etb);
        const fullYear = monthly * 12;

        if (fullYear <= 0 || yearly <= 0 || yearly >= fullYear) return null;

        return Math.round(((fullYear - yearly) / fullYear) * 100);
    };

    const suffix =
        period === 'weekly' ? '/wk' : period === 'monthly' ? '/mo' : '/yr';
    const savings = savingsPercent();

    return (
        <section id="pricing" className="bg-muted/30 py-20 sm:py-32">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                        One plan, three durations
                    </h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        Every duration includes the same features. Pay in ETB
                        via Chapa (Telebirr, cards). No hidden fees.
                    </p>
                </div>

                <div className="mt-10 flex items-center justify-center">
                    <div className="inline-flex items-center gap-1 rounded-lg border bg-background p-1">
                        {(['weekly', 'monthly', 'yearly'] as const).map(
                            (option) => (
                                <Button
                                    key={option}
                                    variant={
                                        period === option ? 'default' : 'ghost'
                                    }
                                    size="sm"
                                    className="h-8 px-4 text-xs capitalize"
                                    onClick={() => setPeriod(option)}
                                    aria-pressed={period === option}
                                >
                                    {option === 'weekly'
                                        ? 'Weekly'
                                        : option === 'monthly'
                                          ? 'Monthly'
                                          : 'Yearly'}
                                    {option === 'yearly' &&
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

                <div className="mx-auto mt-10 grid max-w-5xl gap-8 md:grid-cols-2">
                    {/* Free — values come from marketplace_settings */}
                    <Card className="relative flex flex-col">
                        <CardHeader className="text-center">
                            <CardTitle className="text-2xl font-black tracking-tight">
                                Free
                            </CardTitle>
                            <CardDescription>
                                Browse, search and post as a consignor
                            </CardDescription>
                            <div className="mt-2">
                                <span className="text-4xl font-black tracking-tighter">
                                    0 ETB
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent className="flex-1">
                            <ul className="space-y-3 text-sm">
                                {[
                                    `${formatLimit(free.free_active_posts)} active freight post${
                                        free.free_active_posts === 1 ? '' : 's'
                                    }`,
                                    `${formatLimit(free.free_contact_reveals)} contact reveal${
                                        free.free_contact_reveals === 1
                                            ? ''
                                            : 's'
                                    } (one-time)`,
                                ].map((label) => (
                                    <li key={label} className="flex gap-2">
                                        <Check className="h-4 w-4 shrink-0 text-green-600" />
                                        <span>{label}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Button
                                className="h-10 w-full font-bold"
                                variant="outline"
                                disabled
                            >
                                Free forever
                            </Button>
                        </CardFooter>
                    </Card>

                    {plans.map((plan) => {
                        const price = priceFor(plan, period);

                        return (
                            <Card
                                key={plan.slug}
                                className={`relative flex flex-col ${
                                    plan.is_popular
                                        ? 'border-primary shadow-lg ring-1 ring-primary/20'
                                        : ''
                                }`}
                            >
                                {plan.is_popular && (
                                    <div className="absolute -top-3 left-1/2 z-20 -translate-x-1/2">
                                        <Badge className="gap-1 bg-primary px-3 py-1 font-bold shadow-lg">
                                            <Sparkles className="h-3.5 w-3.5" />{' '}
                                            Popular
                                        </Badge>
                                    </div>
                                )}
                                <CardHeader className="text-center">
                                    <CardTitle className="text-2xl font-black tracking-tight">
                                        {plan.name}
                                    </CardTitle>
                                    <CardDescription>
                                        {plan.description}
                                    </CardDescription>
                                    <div className="mt-2">
                                        <span className="text-4xl font-black tracking-tighter">
                                            {price.toLocaleString()} ETB
                                        </span>
                                        <span className="text-lg font-bold text-muted-foreground">
                                            {suffix}
                                        </span>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex-1">
                                    <ul className="space-y-3 text-sm">
                                        {plan.features.map((feature, index) => (
                                            <li
                                                key={index}
                                                className="flex gap-2"
                                            >
                                                <Check className="h-4 w-4 shrink-0 text-green-600" />
                                                <span>{feature}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </CardContent>
                                <CardFooter>
                                    <Button
                                        className="h-10 w-full font-bold"
                                        asChild
                                    >
                                        <a href="/register">Subscribe</a>
                                    </Button>
                                </CardFooter>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
