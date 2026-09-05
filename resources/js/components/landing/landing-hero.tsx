import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { register } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle,
    CreditCard,
    MapPinned,
    ShieldCheck,
    Truck,
} from 'lucide-react';

export function LandingHero() {
    const { auth } = usePage<SharedData>().props;
    const { t } = useTranslations();

    const stats = [
        {
            icon: Truck,
            value: '5,000+',
            label: t('landing.hero.stats.developers', 'Loads Posted'),
        },
        {
            icon: ShieldCheck,
            value: '2,000+',
            label: t('landing.hero.stats.features', 'Verified Carriers'),
        },
        {
            icon: CreditCard,
            value: 'ETB',
            label: t('landing.hero.stats.opensource', 'Via Chapa'),
        },
    ];

    const features = [
        t('landing.hero.features.auth', 'Post Loads in Seconds'),
        t('landing.hero.features.team', 'Discover Verified Trucks'),
        t('landing.hero.features.billing', 'ETB Payments via Chapa'),
        t('landing.hero.features.admin', 'Real-time Tracking'),
        t('landing.hero.features.flags', 'KYC Verification'),
        t('landing.hero.features.audit', 'Addis–Djibouti & Nationwide'),
    ];

    return (
        <section className="relative overflow-hidden py-20 sm:py-28 lg:py-36">
            {/* Subtle background */}
            <div className="absolute inset-0 -z-10">
                <div className="absolute inset-0 bg-gradient-to-b from-background via-background to-muted/30" />
            </div>

            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-3xl text-center">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-sm border border-accent/40 bg-accent/15 px-3 py-1 text-xs font-semibold tracking-wider text-foreground uppercase">
                        <MapPinned className="h-3.5 w-3.5 text-accent-foreground" />
                        <span>
                            {t(
                                'landing.hero.badge',
                                "Ethiopia's Freight Marketplace • ETB via Chapa",
                            )}
                        </span>
                    </div>

                    {/* Headline — freight-forward */}
                    <h1 className="text-4xl font-extrabold tracking-tight text-foreground sm:text-5xl md:text-6xl lg:text-7xl">
                        <span className="block">
                            {t(
                                'landing.hero.title.part1',
                                "Ethiopia's Freight",
                            )}
                        </span>
                        <span className="block text-primary">
                            {t('landing.hero.title.part2', 'Marketplace')}
                        </span>
                    </h1>

                    {/* Subheadline */}
                    <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground sm:text-xl">
                        {t(
                            'landing.hero.description',
                            'Post loads, discover verified carriers, and move freight from Addis Ababa to Djibouti and nationwide. Secure payments in ETB via Chapa, live tracking, and KYC-verified transporters.',
                        )}{' '}
                        <span className="font-semibold text-foreground">
                            {t(
                                'landing.hero.description_highlight',
                                'Pay in ETB via Chapa.',
                            )}
                        </span>
                    </p>

                    {/* CTA Buttons */}
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Button
                            size="lg"
                            asChild
                            className="h-11 px-7 text-sm font-semibold"
                        >
                            <Link href={auth.user ? '/dashboard' : register()}>
                                {t(
                                    'landing.hero.cta.get_started',
                                    'Post a Load Free',
                                )}
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            asChild
                            className="h-11 px-7 text-sm"
                        >
                            <Link href={auth.user ? '/dashboard' : register()}>
                                <Truck className="mr-2 h-4 w-4" />
                                {t(
                                    'landing.hero.cta.github',
                                    'Find Trucks',
                                )}
                            </Link>
                        </Button>
                    </div>

                    {/* Quick Features */}
                    <div className="mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                        {features.map((feature) => (
                            <div
                                key={feature}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground"
                            >
                                <CheckCircle className="h-3.5 w-3.5 text-primary" />
                                {feature}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="mx-auto mt-14 grid max-w-2xl grid-cols-3 divide-x divide-border border-y">
                    {stats.map((stat) => (
                        <div key={stat.label} className="py-6 text-center">
                            <div className="text-2xl font-bold tabular-nums sm:text-3xl">
                                {stat.value}
                            </div>
                            <div className="mt-1 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Hero preview card — freight board mock */}
                <div className="relative mx-auto mt-12 max-w-5xl">
                    <div className="overflow-hidden rounded-lg border bg-foreground shadow-xl">
                        {/* Window chrome bar */}
                        <div className="flex items-center gap-1.5 border-b border-white/10 bg-foreground px-4 py-3">
                            <span className="h-3 w-3 rounded-full bg-red-500/70" />
                            <span className="h-3 w-3 rounded-full bg-yellow-500/70" />
                            <span className="h-3 w-3 rounded-full bg-green-500/70" />
                            <div className="ml-4 flex-1 rounded bg-white/10 px-3 py-1 text-xs text-white/40">
                                app.numanfreight.et/board • ETB • Chapa Secured
                            </div>
                        </div>
                        <div className="flex h-64 items-center justify-center p-8 sm:h-80">
                            <div className="text-center">
                                <div className="mb-4 flex justify-center">
                                    <div className="flex h-14 w-14 items-center justify-center rounded-md bg-primary/20">
                                        <Truck className="h-7 w-7 text-primary" />
                                    </div>
                                </div>
                                <p className="text-base font-semibold text-white">
                                    {t(
                                        'landing.hero.preview.title',
                                        'Live Freight Board',
                                    )}
                                </p>
                                <p className="mt-2 text-sm text-white/50">
                                    {t(
                                        'landing.hero.preview.description',
                                        'Post loads, get bids, and track shipments across Ethiopia — all in ETB via Chapa',
                                    )}
                                </p>
                                <div className="mt-5 flex flex-wrap justify-center gap-2">
                                    {[
                                        t(
                                            'landing.hero.preview.tags.multi_tenant',
                                            'Post Load',
                                        ),
                                        t(
                                            'landing.hero.preview.tags.stripe_billing',
                                            'Chapa ETB',
                                        ),
                                        t(
                                            'landing.hero.preview.tags.admin_panel',
                                            'Live Tracking',
                                        ),
                                        t(
                                            'landing.hero.preview.tags.team_roles',
                                            'Verified Trucks',
                                        ),
                                        t(
                                            'landing.hero.preview.tags.feature_flags',
                                            'KYC Verified',
                                        ),
                                    ].map((tag) => (
                                        <span
                                            key={tag}
                                            className="rounded-sm border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-medium text-white/70"
                                        >
                                            {tag}
                                        </span>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
