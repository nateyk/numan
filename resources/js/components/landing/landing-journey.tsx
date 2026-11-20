import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Check,
    ClipboardList,
    LockKeyhole,
    MapPin,
    Package,
    Route,
    ShieldCheck,
    Truck,
    Users,
} from 'lucide-react';

const sectionClasses = 'mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8';

export function LandingRouteStrip() {
    const { t } = useTranslations();

    return (
        <section aria-label={t('brand.home.routes.label', 'Designed for freight journeys across Ethiopia')} className="border-b border-border bg-card/60">
            <div className={sectionClasses + ' flex flex-col gap-5 py-7 sm:flex-row sm:items-center sm:justify-between'}>
                <p className="max-w-sm text-sm font-medium text-muted-foreground">
                    {t('brand.home.routes.description', 'From the first pickup to the final destination, every load starts with a clear route.')}
                </p>
                <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-semibold text-foreground">
                    <span className="inline-flex items-center gap-1.5"><MapPin aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />{t('brand.home.routes.origin', 'Origin')}</span>
                    <ArrowRight aria-hidden="true" className="size-4 text-muted-foreground" />
                    <span className="inline-flex items-center gap-1.5"><MapPin aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />{t('brand.home.routes.destination', 'Destination')}</span>
                    <span className="hidden h-5 w-px bg-border sm:block" aria-hidden="true" />
                    <span className="inline-flex items-center gap-1.5"><Package aria-hidden="true" className="size-4 text-brand-sky-strong dark:text-brand-sky" />{t('brand.home.routes.cargo', 'Cargo details')}</span>
                </div>
            </div>
        </section>
    );
}

export function LandingJourney() {
    const { t } = useTranslations();
    const steps = [
        {
            icon: ClipboardList,
            number: '01',
            title: t('brand.home.steps.post.title', 'Describe the load'),
            description: t('brand.home.steps.post.description', 'Shippers add pickup, destination, cargo and schedule so transporters know what needs moving.'),
        },
        {
            icon: Route,
            number: '02',
            title: t('brand.home.steps.discover.title', 'Find a suitable route'),
            description: t('brand.home.steps.discover.description', 'Transporters browse available freight and review the route and load requirements.'),
        },
        {
            icon: Users,
            number: '03',
            title: t('brand.home.steps.connect.title', 'Connect through Numan'),
            description: t('brand.home.steps.connect.description', 'Respond using the marketplace while account and contact-access rules protect private details.'),
        },
    ];

    return (
        <section id="how-it-works" className="scroll-mt-20 py-16 sm:py-24">
            <div className={sectionClasses}>
                <div className="mx-auto max-w-2xl text-center">
                    <Badge variant="outline" className="mb-4 uppercase tracking-widest">{t('brand.home.steps.eyebrow', 'How it works')}</Badge>
                    <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('brand.home.steps.title', 'From a posted load to the right transporter.')}
                    </h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        {t('brand.home.steps.description', 'One straightforward process for shippers and carriers, with the route at the center.')}
                    </p>
                </div>
                <div className="mt-10 grid gap-4 md:grid-cols-3">
                    {steps.map((step) => (
                        <article key={step.number} className="flex flex-col rounded-lg border border-border bg-card p-6">
                            <div className="flex items-start justify-between gap-4">
                                <span className="flex size-11 items-center justify-center rounded-md bg-secondary text-brand-sky-strong dark:text-brand-sky"><step.icon aria-hidden="true" className="size-5" /></span>
                                <span className="text-sm font-semibold tabular-nums text-muted-foreground">{step.number}</span>
                            </div>
                            <h3 className="mt-6 text-lg font-semibold tracking-tight">{step.title}</h3>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">{step.description}</p>
                        </article>
                    ))}
                </div>
                <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <PostFreightButton size="lg" className="h-11 w-full sm:w-auto" label={t('brand.home.nav.post_load', 'Post a load')} />
                    <Button size="lg" variant="outline" asChild className="h-11 w-full sm:w-auto">
                        <Link href="/freight">{t('brand.home.nav.browse', 'Browse freight')}<ArrowRight aria-hidden="true" className="size-4" /></Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}

export function LandingCapabilities() {
    const { t } = useTranslations();

    return (
        <section id="capabilities" className="py-16 sm:py-24">
            <div className={sectionClasses}>
                <div className="max-w-2xl">
                    <Badge variant="outline" className="mb-4 uppercase tracking-widest">{t('brand.home.capabilities.eyebrow', 'Marketplace essentials')}</Badge>
                    <h2 className="text-balance text-3xl font-bold tracking-tight sm:text-4xl">
                        {t('brand.home.capabilities.title', 'The details you need, without the noise.')}
                    </h2>
                    <p className="mt-4 text-base leading-7 text-muted-foreground">
                        {t('brand.home.capabilities.description', 'A clearer way to share freight requirements, review routes and manage who can access contact information.')}
                    </p>
                </div>
                <div className="mt-10 grid gap-4 md:grid-cols-4 md:grid-rows-2">
                    <article className="flex flex-col justify-between gap-8 rounded-lg border border-border bg-card p-6 md:col-span-2 md:row-span-2 sm:p-8">
                        <div>
                            <div className="flex size-11 items-center justify-center rounded-md bg-primary text-primary-foreground"><Route aria-hidden="true" className="size-5" /></div>
                            <h3 className="mt-5 text-xl font-semibold tracking-tight">{t('brand.home.capabilities.route.title', 'Routes come first')}</h3>
                            <p className="mt-2 max-w-md text-sm leading-6 text-muted-foreground">{t('brand.home.capabilities.route.description', 'Understand where a load starts, where it needs to go and what is being transported before you respond.')}</p>
                        </div>
                        <div className="rounded-lg border border-border bg-muted/50 p-4 sm:p-5" aria-label={t('brand.home.capabilities.route.preview', 'Example route fields')}>
                            <div className="flex items-start gap-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-brand-sky-strong dark:text-brand-sky"><MapPin aria-hidden="true" className="size-4" /></span>
                                <div className="min-w-0 flex-1">
                                    <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('brand.home.hero.origin_label', 'Origin')}</span>
                                    <div className="mt-1 h-2.5 w-2/3 rounded-full bg-border" />
                                </div>
                            </div>
                            <div className="ml-4 h-6 border-l-2 border-dashed border-brand-sky/40" />
                            <div className="flex items-start gap-3">
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground"><MapPin aria-hidden="true" className="size-4" /></span>
                                <div className="min-w-0 flex-1">
                                    <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t('brand.home.hero.destination_label', 'Destination')}</span>
                                    <div className="mt-1 h-2.5 w-4/5 rounded-full bg-border" />
                                </div>
                            </div>
                        </div>
                    </article>
                    <article className="rounded-lg border border-border bg-card p-6 md:col-span-2">
                        <div className="flex size-10 items-center justify-center rounded-md border border-border bg-muted"><Package aria-hidden="true" className="size-5 text-brand-sky-strong dark:text-brand-sky" /></div>
                        <h3 className="mt-4 font-semibold tracking-tight">{t('brand.home.capabilities.cargo.title', 'Cargo at a glance')}</h3>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('brand.home.capabilities.cargo.description', 'Review load requirements and truck needs in a consistent listing.')}</p>
                    </article>
                    <article className="rounded-lg border border-border bg-card p-6">
                        <div className="flex size-10 items-center justify-center rounded-md border border-border bg-muted"><LockKeyhole aria-hidden="true" className="size-5 text-brand-sky-strong dark:text-brand-sky" /></div>
                        <h3 className="mt-4 font-semibold tracking-tight">{t('brand.home.capabilities.privacy.title', 'Private by default')}</h3>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('brand.home.capabilities.privacy.description', 'Contact details follow the platform access rules.')}</p>
                    </article>
                    <article className="rounded-lg border border-border bg-card p-6">
                        <div className="flex size-10 items-center justify-center rounded-md border border-border bg-muted"><ShieldCheck aria-hidden="true" className="size-5 text-brand-sky-strong dark:text-brand-sky" /></div>
                        <h3 className="mt-4 font-semibold tracking-tight">{t('brand.home.capabilities.access.title', 'Member access')}</h3>
                        <p className="mt-2 text-sm leading-6 text-muted-foreground">{t('brand.home.capabilities.access.description', 'Freight discovery is for signed-in members with eligible access.')}</p>
                    </article>
                </div>
                <div className="mt-8 grid gap-px overflow-hidden rounded-lg border border-border bg-border sm:grid-cols-3">
                    {[
                        { icon: Check, title: t('brand.home.capabilities.pillar1', 'Clear requirements') },
                        { icon: Truck, title: t('brand.home.capabilities.pillar2', 'Freight discovery') },
                        { icon: LockKeyhole, title: t('brand.home.capabilities.pillar3', 'Protected contact') },
                    ].map((item) => (
                        <div key={item.title} className="flex items-center gap-3 bg-card px-5 py-5">
                            <item.icon aria-hidden="true" className="size-5 shrink-0 text-brand-sky-strong dark:text-brand-sky" />
                            <span className="text-sm font-medium">{item.title}</span>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
