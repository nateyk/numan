import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { LanguageSwitcher } from '@/components/language-switcher';
import { PublicAccountMenu } from '@/components/public-account-menu';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useTranslations } from '@/hooks/use-translations';
import { login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    CreditCard,
    HelpCircle,
    LayoutGrid,
    Menu,
    Route,
    Truck,
} from 'lucide-react';
import { useState } from 'react';

interface LandingHeaderProps {
    canRegister?: boolean;
}

export function LandingHeader({ canRegister = true }: LandingHeaderProps) {
    const { t } = useTranslations();
    const { auth, locale } = usePage<SharedData>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    const navLinks = [
        {
            href: '#how-it-works',
            label: t('brand.home.nav.how_it_works', 'How it works'),
            icon: Route,
        },
        {
            href: '#features',
            label: t('brand.home.nav.features', 'Features'),
            icon: LayoutGrid,
        },
        {
            href: '#pricing',
            label: t('brand.home.nav.pricing', 'Plans'),
            icon: CreditCard,
        },
        {
            href: '#faq',
            label: t('brand.home.nav.faq', 'FAQ'),
            icon: HelpCircle,
        },
    ];

    const mobileNavItemClass =
        'flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium leading-5 text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

    return (
        <header className="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur-sm">
            <a
                href="#main-content"
                className="sr-only rounded-md bg-background px-4 py-2 text-foreground focus:not-sr-only focus:absolute focus:top-2 focus:left-3 focus:z-[60] focus:outline-2 focus:outline-ring"
            >
                {t('brand.home.nav.skip', 'Skip to content')}
            </a>
            <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                {/* Logo */}
                <Link
                    href="/"
                    aria-label="Numan Freight home"
                    className="flex items-center gap-2"
                >
                    <span className="flex h-6 w-8 items-center justify-center rounded bg-white sm:h-8 sm:w-10">
                        <img
                            src="/brand/numanfreight-symbol.svg"
                            alt=""
                            className="h-5 w-7 object-contain sm:h-7 sm:w-9"
                        />
                    </span>
                    <img
                        src="/brand/numanfreight-wordmark.svg"
                        alt="Numan Freight"
                        className="h-6 w-auto max-w-28 sm:h-7 sm:max-w-40 dark:hidden"
                    />
                    <img
                        src="/brand/numanfreight-wordmark-white.svg"
                        alt=""
                        aria-hidden="true"
                        className="hidden h-6 w-auto max-w-28 sm:h-7 sm:max-w-40 dark:block"
                    />
                </Link>

                {/* Desktop Navigation */}
                <nav
                    aria-label="Main navigation"
                    className="hidden items-center gap-5 lg:flex"
                >
                    <Link
                        href="/freight"
                        className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {t('brand.home.nav.browse', 'Browse freight')}
                    </Link>
                    {navLinks.map((link) => (
                        <a
                            key={link.href}
                            href={link.href}
                            className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                            onClick={(e) => {
                                if (link.href.startsWith('#')) {
                                    e.preventDefault();
                                    const element = document.querySelector(
                                        link.href,
                                    );
                                    element?.scrollIntoView({
                                        behavior: 'smooth',
                                        block: 'start',
                                    });
                                }
                            }}
                        >
                            {link.label}
                        </a>
                    ))}
                </nav>

                <div className="hidden items-center gap-3 lg:flex">
                    <LanguageSwitcher currentLocale={locale} />
                    <AppearanceToggleDropdown />
                    {auth.user ? (
                        <PublicAccountMenu />
                    ) : (
                        <>
                            <Button variant="ghost" asChild>
                                <Link href={login()}>
                                    {t('landing.nav.signin', 'Sign In')}
                                </Link>
                            </Button>
                            {canRegister && (
                                <Button asChild>
                                    <Link href={register()}>
                                        {t(
                                            'brand.home.nav.post_load',
                                            'Post a load',
                                        )}
                                    </Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>

                {/* Mobile navigation — reuse the same account destinations without nesting a dropdown in the sheet. */}
                <div className="flex items-center gap-2 lg:hidden">
                    <Sheet
                        open={mobileMenuOpen}
                        onOpenChange={setMobileMenuOpen}
                    >
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label={t(
                                    'brand.home.nav.open_menu',
                                    'Open menu',
                                )}
                                aria-expanded={mobileMenuOpen}
                                className="size-10"
                            >
                                <Menu aria-hidden="true" className="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="gap-0 overflow-hidden border-l border-border bg-background p-0 [&>button]:top-3 [&>button]:right-3 [&>button]:flex [&>button]:size-10 [&>button]:items-center [&>button]:justify-center [&>button]:rounded-md [&>button]:opacity-100"
                            style={{
                                width: 'min(23rem, calc(100vw - 1rem))',
                                maxWidth: '23rem',
                            }}
                        >
                            <SheetHeader className="shrink-0 border-b border-border px-5 py-4 pr-14">
                                <SheetTitle className="sr-only">
                                    {t(
                                        'brand.home.nav.menu',
                                        'Navigation menu',
                                    )}
                                </SheetTitle>
                                <Link
                                    href="/"
                                    aria-label={t(
                                        'brand.home.nav.home',
                                        'Numan Freight home',
                                    )}
                                    onClick={() => setMobileMenuOpen(false)}
                                    className="inline-flex w-fit items-center gap-2"
                                >
                                    <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-white">
                                        <img
                                            src="/brand/numanfreight-symbol.svg"
                                            alt=""
                                            className="h-7 w-8 object-contain"
                                        />
                                    </span>
                                    <img
                                        src="/brand/numanfreight-wordmark.svg"
                                        alt=""
                                        aria-hidden="true"
                                        className="h-6 w-auto max-w-32 dark:hidden"
                                    />
                                    <img
                                        src="/brand/numanfreight-wordmark-white.svg"
                                        alt=""
                                        aria-hidden="true"
                                        className="hidden h-6 w-auto max-w-32 dark:block"
                                    />
                                </Link>
                            </SheetHeader>

                            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-5">
                                <p className="px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t('brand.home.nav.explore', 'Explore')}
                                </p>
                                <nav
                                    aria-label={t(
                                        'brand.home.nav.site',
                                        'Site navigation',
                                    )}
                                    className="mt-2 flex flex-col gap-1"
                                >
                                    <Link
                                        href="/freight"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className={mobileNavItemClass}
                                    >
                                        <Truck
                                            aria-hidden="true"
                                            className="size-4 shrink-0 text-muted-foreground"
                                        />
                                        {t(
                                            'brand.home.nav.browse',
                                            'Browse freight',
                                        )}
                                    </Link>
                                    {navLinks.map((link) => {
                                        const Icon = link.icon;
                                        return (
                                            <a
                                                key={link.href}
                                                href={link.href}
                                                onClick={(event) => {
                                                    event.preventDefault();
                                                    setMobileMenuOpen(false);
                                                    window.setTimeout(() => {
                                                        document
                                                            .getElementById(
                                                                link.href.slice(
                                                                    1,
                                                                ),
                                                            )
                                                            ?.scrollIntoView({
                                                                behavior:
                                                                    window.matchMedia(
                                                                        '(prefers-reduced-motion: reduce)',
                                                                    ).matches
                                                                        ? 'auto'
                                                                        : 'smooth',
                                                                block: 'start',
                                                            });
                                                    }, 100);
                                                }}
                                                className={mobileNavItemClass}
                                            >
                                                <Icon
                                                    aria-hidden="true"
                                                    className="size-4 shrink-0 text-muted-foreground"
                                                />
                                                {link.label}
                                            </a>
                                        );
                                    })}
                                </nav>

                                <div className="mt-6 border-t border-border pt-5">
                                    {auth.user ? (
                                        <PublicAccountMenu
                                            display="inline"
                                            onNavigate={() =>
                                                setMobileMenuOpen(false)
                                            }
                                        />
                                    ) : (
                                        <div className="flex flex-col gap-2">
                                            <Button
                                                variant="outline"
                                                asChild
                                                className="h-11 w-full"
                                            >
                                                <Link
                                                    href={login()}
                                                    onClick={() =>
                                                        setMobileMenuOpen(false)
                                                    }
                                                >
                                                    {t(
                                                        'landing.nav.signin',
                                                        'Sign In',
                                                    )}
                                                </Link>
                                            </Button>
                                            {canRegister && (
                                                <Button
                                                    asChild
                                                    className="h-11 w-full"
                                                >
                                                    <Link
                                                        href={register()}
                                                        onClick={() =>
                                                            setMobileMenuOpen(
                                                                false,
                                                            )
                                                        }
                                                    >
                                                        {t(
                                                            'brand.home.nav.post_load',
                                                            'Post a load',
                                                        )}
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="shrink-0 border-t border-border bg-muted/30 px-5 pt-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                                <p className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    {t(
                                        'brand.home.nav.preferences',
                                        'Preferences',
                                    )}
                                </p>
                                <div className="flex items-center justify-between gap-3">
                                    <LanguageSwitcher currentLocale={locale} />
                                    <AppearanceToggleDropdown />
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
