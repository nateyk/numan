import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { LanguageSwitcher } from '@/components/language-switcher';
import { PublicAccountMenu } from '@/components/public-account-menu';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { useTranslations } from '@/hooks/use-translations';
import { login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
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
        },
    ];

    return (
        <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur-sm">
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
                <nav className="hidden items-center gap-6 md:flex">
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

                <div className="hidden items-center gap-3 md:flex">
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

                {/* Mobile Menu */}
                <div className="flex items-center gap-2 md:hidden">
                    <Sheet
                        open={mobileMenuOpen}
                        onOpenChange={setMobileMenuOpen}
                    >
                        <SheetTrigger asChild>
                            <Button variant="ghost" size="icon">
                                <Menu className="h-5 w-5" />
                                <span className="sr-only">Open menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="w-full sm:max-w-sm"
                        >
                            <div className="flex flex-col gap-6 pt-6">
                                <nav className="flex flex-col gap-4">
                                    <Link
                                        href="/freight"
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="text-lg font-medium text-foreground"
                                    >
                                        {t(
                                            'brand.home.nav.browse',
                                            'Browse freight',
                                        )}
                                    </Link>
                                    {navLinks.map((link) => (
                                        <a
                                            key={link.href}
                                            href={link.href}
                                            onClick={(e) => {
                                                setMobileMenuOpen(false);
                                                if (link.href.startsWith('#')) {
                                                    e.preventDefault();
                                                    setTimeout(() => {
                                                        const element =
                                                            document.querySelector(
                                                                link.href,
                                                            );
                                                        element?.scrollIntoView(
                                                            {
                                                                behavior:
                                                                    'smooth',
                                                                block: 'start',
                                                            },
                                                        );
                                                    }, 100);
                                                }
                                            }}
                                            className="text-lg font-medium text-foreground"
                                        >
                                            {link.label}
                                        </a>
                                    ))}
                                </nav>
                                <div className="flex flex-col gap-3">
                                    {auth.user ? (
                                        <PublicAccountMenu
                                            onNavigate={() =>
                                                setMobileMenuOpen(false)
                                            }
                                            className="w-full justify-start"
                                        />
                                    ) : (
                                        <>
                                            <Button
                                                variant="outline"
                                                asChild
                                                className="w-full"
                                            >
                                                <Link href={login()}>
                                                    {t(
                                                        'landing.nav.signin',
                                                        'Sign In',
                                                    )}
                                                </Link>
                                            </Button>
                                            {canRegister && (
                                                <Button
                                                    asChild
                                                    className="w-full"
                                                >
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
                                <div className="flex items-center gap-3 border-t pt-4">
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
