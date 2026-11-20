import {
    LandingCta,
    LandingFaq,
    LandingFeatures,
    LandingFooter,
    LandingHeader,
    LandingHero,
    LandingPricing,
} from '@/components/landing';
import { LandingCapabilities, LandingJourney, LandingRouteStrip } from '@/components/landing/landing-journey';
import { useTranslations } from '@/hooks/use-translations';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';

interface WelcomeProps {
    canRegister?: boolean;
    appUrl: string;
    marketplacePlans?: ComponentProps<typeof LandingPricing>['plans'];
    freeLimits?: React.ComponentProps<typeof LandingPricing>['freeLimits'];
}

export default function Welcome({ canRegister = true, appUrl, marketplacePlans = [], freeLimits }: WelcomeProps) {
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

            <div className="min-h-svh bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main id="main-content" className="relative">
                    <LandingHero />
                    <LandingRouteStrip />
                    <LandingJourney />
                    <LandingFeatures />
                    <LandingCapabilities />
                    <LandingPricing plans={marketplacePlans} freeLimits={freeLimits} />
                    <LandingFaq />
                    <LandingCta />
                </main>
                <LandingFooter canRegister={canRegister} />
            </div>
        </>
    );
}
