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
                logo: `${canonicalUrl}/logo.svg`,
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

            <div className="min-h-screen bg-background text-[#0F294A] dark:text-slate-100">
                <LandingHeader canRegister={canRegister} />
                <main>
                    <LandingHero />

                    <section
                        id="how-it-works"
                        className="scroll-mt-20 px-4 py-16 sm:px-6 sm:py-20 lg:px-8"
                    >
                        <div className="mx-auto max-w-7xl">
                            <div className="max-w-2xl">
                                <p className="text-sm font-semibold tracking-wide text-[#2D9CDB] uppercase">
                                    {t(
                                        'brand.home.section.eyebrow',
                                        'One marketplace, both sides of the journey',
                                    )}
                                </p>
                                <h2 className="mt-3 text-3xl font-bold tracking-tight text-[#0F294A] sm:text-4xl dark:text-slate-50">
                                    {t(
                                        'brand.home.section.title',
                                        'Freight starts moving when the right people connect.',
                                    )}
                                </h2>
                                <p className="mt-4 text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">
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
                                        className="rounded-xl border border-slate-200 bg-white p-6 shadow-[0_12px_36px_-28px_rgba(15,41,74,0.45)] dark:border-white/10 dark:bg-slate-900"
                                    >
                                        <div className="flex size-11 items-center justify-center rounded-lg bg-[#2D9CDB]/10 text-[#0F294A] dark:text-sky-300">
                                            <benefit.icon className="size-5" />
                                        </div>
                                        <h3 className="mt-5 text-lg font-semibold text-[#0F294A] dark:text-slate-100">
                                            {benefit.title}
                                        </h3>
                                        <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {benefit.description}
                                        </p>
                                    </article>
                                ))}
                            </div>

                            <div className="mt-10 flex flex-col gap-4 rounded-xl bg-[#F3F8FB] p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8 dark:bg-slate-800">
                                <div className="flex items-start gap-4">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-full bg-white text-[#2D9CDB] shadow-sm dark:bg-slate-900">
                                        <Handshake className="size-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-[#0F294A] dark:text-slate-100">
                                            {t(
                                                'brand.home.cta.title',
                                                'Ready for your next load?',
                                            )}
                                        </h3>
                                        <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {t(
                                                'brand.home.cta.description',
                                                'Browse freight already posted or create a listing and connect with a transporter.',
                                            )}
                                        </p>
                                    </div>
                                </div>
                                <Link
                                    href="/freight"
                                    className="inline-flex h-11 shrink-0 items-center justify-center rounded-md bg-[#0F294A] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#173B67] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2D9CDB]"
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
