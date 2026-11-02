import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';

export function LandingFooter({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { t } = useTranslations();

    return (
        <footer className="border-t bg-[#0F294A] text-white">
            <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-10 sm:flex-row sm:items-end sm:justify-between sm:px-6 lg:px-8">
                <div className="space-y-4">
                    <Link href="/" aria-label="Numan Freight home">
                        <img
                            src="/brand/numanfreight-wordmark-white.svg"
                            alt="Numan Freight"
                            className="h-7 w-auto"
                        />
                    </Link>
                    <p className="max-w-md text-sm leading-relaxed text-white/70">
                        {t(
                            'brand.home.footer.tagline',
                            'A freight marketplace connecting shippers and carriers across Ethiopia.',
                        )}
                    </p>
                </div>

                <nav
                    aria-label="Footer"
                    className="flex flex-wrap gap-x-6 gap-y-3 text-sm"
                >
                    <Link
                        href="/freight"
                        className="text-white/80 transition-colors hover:text-white"
                    >
                        {t('brand.home.nav.browse', 'Browse freight')}
                    </Link>
                    {canRegister && (
                        <Link
                            href="/register"
                            className="text-white/80 transition-colors hover:text-white"
                        >
                            {t('brand.home.nav.post_load', 'Post a load')}
                        </Link>
                    )}
                    <a
                        href="/#how-it-works"
                        className="text-white/80 transition-colors hover:text-white"
                    >
                        {t('brand.home.nav.how_it_works', 'How it works')}
                    </a>
                </nav>

                <p className="text-xs text-white/50">
                    © {new Date().getFullYear()} Numan Freight
                </p>
            </div>
        </footer>
    );
}
