import {
    LandingFooter,
    LandingHeader,
    LandingHero,
} from '@/components/landing';
import { useTranslations } from '@/hooks/use-translations';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Handshake, PackageCheck, ShieldCheck, Truck } from 'lucide-react';

interface WelcomeProps {
    canRegister?: boolean;
    appUrl: string;
}

export default function Welcome({ canRegister = true, appUrl }: WelcomeProps) {
    const { t } = useTranslations();
    const { locale = 'en' } = usePage<SharedData>().props;
    const canonicalUrl = appUrl.replace(/\/$/, '');
    const socialImage = `${canonicalUrl}/brand/numanfreight-social.png`;
    const description = t(
        'brand.home.meta.description',
        'Find freight, post loads, and connect with carriers across Ethiopia. Numan Freight brings shippers and transporters together to plan routes and move goods.',
    );
    const pageTitle = t(
        'brand.home.meta.title',
        'Ethiopia Freight Marketplace',
    );
    const socialTitle = t(
        'brand.home.meta.og_title',
        'Numan Freight | Ethiopia Freight Marketplace',
    );
    const websiteData = {
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'Organization',
                '@id': `${canonicalUrl}/#organization`,
                name: 'Numan Freight',
                url: canonicalUrl,
                logo: `${canonicalUrl}/brand/numanfreight-symbol.svg`,
                description,
            },
            {
                '@type': 'WebSite',
                '@id': `${canonicalUrl}/#website`,
                url: canonicalUrl,
                name: 'Numan Freight',
                description,
                publisher: { '@id': `${canonicalUrl}/#organization` },
                inLanguage: locale,
            },
        ],
    };

    const benefits = [
        {
            icon: PackageCheck,
            title: t('brand.home.shippers.title', 'For people shipping goods'),
            description: t(
                'brand.home.shippers.description',
                'Describe your cargo, route, and schedule. Find transporters and compare the responses that fit your load.',
            ),
        },
        {
            icon: Truck,
            title: t('brand.home.carriers.title', 'For transporters'),
            description: t(
                'brand.home.carriers.description',
                'Browse available freight, find routes that work for your trucks, and respond to shippers in one place.',
            ),
        },
        {
            icon: ShieldCheck,
            title: t(
                'brand.home.trust.title',
                'Clear details. Private contact.',
            ),
            description: t(
                'brand.home.trust.description',
                'Review freight and public profile details first. Contact information stays private until it is revealed through the platform.',
            ),
        },
    ];

    return (
        <>
            <Head title={pageTitle}>
                <meta name="description" content={description} />
                <meta name="robots" content="index, follow" />
                <meta name="theme-color" content="#0F294A" />
                <link rel="canonical" href={canonicalUrl} />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content="Numan Freight" />
                <meta property="og:title" content={socialTitle} />
                <meta property="og:description" content={description} />
                <meta property="og:url" content={canonicalUrl} />
                <meta property="og:image" content={socialImage} />
                <meta property="og:image:width" content="1440" />
                <meta property="og:image:height" content="780" />
                <meta
                    property="og:image:alt"
                    content="Numan Freight, Ethiopia freight marketplace"
                />
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={socialTitle} />
                <meta name="twitter:description" content={description} />
                <meta name="twitter:image" content={socialImage} />
                <script type="application/ld+json">
                    {JSON.stringify(websiteData)}
                </script>
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main>
                    <LandingHero />

                    <section
                        id="how-it-works"
                        className="scroll-mt-20 px-4 py-16 sm:px-6 sm:py-20 lg:px-8"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-2xl">
                                <p className="text-sm font-semibold tracking-wide text-brand-sky-strong uppercase dark:text-brand-sky">
                                    {t(
                                        'brand.home.section.eyebrow',
                                        'One marketplace, both sides of the journey',
                                    )}
                                </p>
                                <h2 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                                    {t(
                                        'brand.home.section.title',
                                        'Freight starts moving when the right people connect.',
                                    )}
                                </h2>
                                <p className="mt-4 text-base leading-7 text-muted-foreground sm:text-lg">
                                    {t(
                                        'brand.home.section.description',
                                        'Whether you need a truck or have one ready, Numan Freight helps you find the next step.',
                                    )}
                                </p>
                            </div>

                            <div className="mt-10 grid gap-4 md:grid-cols-3">
                                {benefits.map((benefit) => (
                                    <article
                                        key={benefit.title}
                                        className="rounded-xl border border-border bg-card p-6"
                                    >
                                        <div className="flex size-11 items-center justify-center rounded-lg bg-brand-sky/10 text-brand-sky-strong dark:text-brand-sky">
                                            <benefit.icon className="size-5" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-semibold text-card-foreground">
                                            {benefit.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                            {benefit.description}
                                        </p>
                                    </article>
                                ))}
                            </div>

                            <div className="mt-10 flex flex-col gap-4 rounded-xl bg-secondary p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                                <div className="flex items-start gap-4">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-card text-brand-sky-strong shadow-sm dark:text-brand-sky">
                                        <Handshake className="size-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-card-foreground">
                                            {t(
                                                'brand.home.cta.title',
                                                'Ready for your next load?',
                                            )}
                                        </h3>
                                        <p className="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground">
                                            {t(
                                                'brand.home.cta.description',
                                                'Browse freight already posted or create a listing and connect with a transporter.',
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    href="/freight"
                                    className="inline-flex h-11 shrink-0 items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {t(
                                        'brand.home.cta.button',
                                        'Browse freight',
                                    )}
                                </Link>
                            </div>
                        </div>
                    </section>
                </main>
                <LandingFooter canRegister={canRegister} />
            </div>
        </>
    );
}
