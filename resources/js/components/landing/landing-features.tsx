import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    ClipboardList,
    LockKeyhole,
    MapPin,
    Package,
    ShieldCheck,
    Truck,
} from 'lucide-react';

export function LandingFeatures() {
    const { t } = useTranslations();

    const rows = [
        {
            eyebrow: t('brand.home.features.shippers.eyebrow', 'For shippers'),
            title: t('brand.home.shippers.title', 'For people shipping goods'),
            description: t(
                'brand.home.shippers.description',
                'Describe your cargo, route, and schedule. Find transporters and compare the responses that fit your load.',
            ),
            icon: Package,
            bullets: [
                t('brand.home.features.shippers.one', 'Pickup and destination details'),
                t('brand.home.features.shippers.two', 'Cargo requirements and truck type'),
                t('brand.home.features.shippers.three', 'A clear place to manage your load'),
            ],
        },
        {
            eyebrow: t('brand.home.features.carriers.eyebrow', 'For transporters'),
            title: t('brand.home.carriers.title', 'For transporters'),
            description: t(
                'brand.home.carriers.description',
                'Browse available freight, find routes that work for your trucks, and respond to shippers in one place.',
            ),
            icon: Truck,
            bullets: [
                t('brand.home.features.carriers.one', 'Find freight based on its route'),
                t('brand.home.features.carriers.two', 'Review load requirements before responding'),
                t('brand.home.features.carriers.three', 'Manage access through your account'),
            ],
        },
        {
            eyebrow: t('brand.home.features.privacy.eyebrow', 'Built-in privacy'),
            title: t('brand.home.trust.title', 'Clear details. Private contact.'),
            description: t(
                'brand.home.trust.description',
                'Review freight and public profile details first. Contact information stays private until it is revealed through the platform.',
            ),
            icon: ShieldCheck,
            bullets: [
                t('brand.home.features.privacy.one', 'Protected contact information'),
                t('brand.home.features.privacy.two', 'Member-only freight discovery'),
                t('brand.home.features.privacy.three', 'Access checks when plan limits change'),
            ],
        },
    ];

    return (
        <section id="features" className="scroll-mt-20 border-y border-border bg-muted/30 py-16 sm:py-24">
            <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-2xl text-center">
                    <span className="text-xs font-semibold uppercase tracking-widest text-brand-sky-strong dark:text-brand-sky">
                        {t('brand.home.features.eyebrow', 'One marketplace, two sides of the journey')}
                    </span>
                    <h2 className="mt-4 text-balance text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('brand.home.section.title', 'Freight starts moving when the right people connect.')}
                    </h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        {t('brand.home.section.description', 'Whether you need a truck or have one ready, Numan Freight helps you find the next step.')}
                    </p>
                </div>

                <div className="mt-12">
                    {rows.map((row, index) => (
                        <div key={row.eyebrow}>
                            <div className={'grid gap-8 py-10 md:grid-cols-2 md:items-center md:gap-12 lg:gap-16 ' + (index % 2 ? 'md:[&>*:first-child]:order-2' : '')}>
                                <div>
                                    <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                                        <span className="flex size-7 items-center justify-center rounded-md border border-border bg-card">
                                            <row.icon aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />
                                        </span>
                                        {row.eyebrow}
                                    </div>
                                    <h3 className="mt-5 text-2xl font-semibold tracking-tight sm:text-3xl">{row.title}</h3>
                                    <p className="mt-4 max-w-lg leading-7 text-muted-foreground">{row.description}</p>
                                    <ul className="mt-6 space-y-3">
                                        {row.bullets.map((bullet) => (
                                            <li key={bullet} className="flex items-start gap-3 text-sm text-foreground">
                                                <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-secondary text-brand-sky-strong dark:text-brand-sky">
                                                    <ShieldCheck aria-hidden="true" className="size-3.5" />
                                                </span>
                                                {bullet}
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="mt-7">
                                        {index === 0 ? (
                                            <PostFreightButton size="lg" className="h-11" label={t('brand.home.nav.post_load', 'Post a load')} />
                                        ) : (
                                            <Button asChild size="lg" variant="outline" className="h-11">
                                                <Link href="/freight">{t('brand.home.nav.browse', 'Browse freight')}<ArrowRight aria-hidden="true" className="size-4" /></Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                <div className="min-w-0 rounded-lg border border-border bg-card p-4 sm:p-6">
                                    <div className="flex items-center justify-between border-b border-border pb-4">
                                        <span className="text-sm font-semibold">{row.eyebrow}</span>
                                        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <span className="size-2 rounded-full bg-brand-sky" aria-hidden="true" />
                                            Numan Freight
                                        </span>
                                    </div>
                                    {index === 0 ? (
                                        <div className="mt-5 space-y-3">
                                            <IllustratedField icon={MapPin} label={t('brand.home.hero.origin_label', 'Origin')} />
                                            <IllustratedField icon={MapPin} label={t('brand.home.hero.destination_label', 'Destination')} />
                                            <IllustratedField icon={Package} label={t('brand.home.features.cargo_label', 'Cargo & truck')} />
                                            <div className="flex items-center justify-end pt-2">
                                                <span className="rounded-md bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground">{t('brand.home.nav.post_load', 'Post a load')}</span>
                                            </div>
                                        </div>
                                    ) : index === 1 ? (
                                        <div className="mt-5 space-y-3">
                                            <div className="rounded-md border border-border bg-muted/50 p-4">
                                                <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('brand.home.features.route_preview', 'Freight route')}</span>
                                                <div className="mt-3 flex items-center gap-2 text-sm font-medium">
                                                    <MapPin aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />
                                                    <span>{t('brand.home.hero.origin_label', 'Origin')}</span>
                                                    <ArrowRight aria-hidden="true" className="size-4 text-muted-foreground" />
                                                    <span>{t('brand.home.hero.destination_label', 'Destination')}</span>
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 gap-3">
                                                <IllustratedMini icon={Package} label={t('brand.home.features.cargo_label', 'Cargo & truck')} />
                                                <IllustratedMini icon={Truck} label={t('brand.home.features.transport_label', 'Transport requirements')} />
                                            </div>
                                            <div className="rounded-md bg-secondary px-4 py-3 text-sm font-medium text-secondary-foreground">{t('brand.home.features.discover_label', 'Browse eligible freight')}</div>
                                        </div>
                                    ) : (
                                        <div className="mt-5 flex flex-col items-center justify-center rounded-md border border-border bg-muted/40 px-5 py-12 text-center">
                                            <span className="flex size-14 items-center justify-center rounded-full bg-secondary text-brand-sky-strong dark:text-brand-sky">
                                                <LockKeyhole aria-hidden="true" className="size-6" />
                                            </span>
                                            <p className="mt-5 font-semibold">{t('brand.home.features.privacy.preview_title', 'Contact details stay protected')}</p>
                                            <p className="mt-2 max-w-xs text-sm leading-6 text-muted-foreground">{t('brand.home.features.privacy.preview_description', 'The platform checks account access before revealing private information.')}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                            {index < rows.length - 1 && <Separator />}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function IllustratedField({
    icon: Icon,
    label,
}: {
    icon: typeof MapPin;
    label: string;
}) {
    return (
        <div className="flex items-center gap-3 rounded-md border border-border bg-muted/30 px-4 py-3">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-secondary text-brand-sky-strong dark:text-brand-sky"><Icon aria-hidden="true" className="size-4" /></span>
            <div className="min-w-0 flex-1">
                <span className="text-xs font-medium text-muted-foreground">{label}</span>
                <div className="mt-1.5 h-2 w-2/3 rounded-full bg-border" aria-hidden="true" />
            </div>
        </div>
    );
}

function IllustratedMini({
    icon: Icon,
    label,
}: {
    icon: typeof MapPin;
    label: string;
}) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <Icon aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />
            <p className="mt-3 text-xs font-medium">{label}</p>
        </div>
    );
}
