import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { dashboard, login, register } from '@/routes';
import { discovery, index as myFreight, myInterests } from '@/routes/freight';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, Truck } from 'lucide-react';
import { useState } from 'react';

/**
 * The freight-scoped chrome for the public browse pages.
 *
 * This used to be the landing template's header: a "Laravel SAAS Starter"
 * wordmark over four anchor links (#features, #pricing, #testimonials, #faq)
 * that did nothing on /freight, closing with the starter kit's own footer
 * pitch. On a public trust surface the chrome is the credibility, so the
 * browse pages carry the product's own mark and real destinations now.
 */

const navLinks = [
    { href: discovery(), label: 'Browse' },
    { href: myFreight(), label: 'Post a Load' },
    { href: myInterests(), label: 'My Interests' },
];

function AccountLinks({
    canRegister = true,
    onNavigate,
}: {
    canRegister?: boolean;
    onNavigate?: () => void;
}) {
    const { auth } = usePage<SharedData>().props;

    if (auth.user) {
        return (
            <Button size="sm" asChild>
                <Link href={dashboard()} onClick={onNavigate}>
                    Dashboard
                </Link>
            </Button>
        );
    }

    return (
        <>
            <Button variant="ghost" size="sm" asChild>
                <Link href={login()} onClick={onNavigate}>
                    Sign in
                </Link>
            </Button>
            {canRegister && (
                <Button size="sm" asChild>
                    <Link href={register()} onClick={onNavigate}>
                        Register
                    </Link>
                </Button>
            )}
        </>
    );
}

export function DiscoveryHeader({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const currentUrl = usePage().url.split('?')[0];

    return (
        <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur-sm">
            <div className="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                <Link
                    href={discovery()}
                    className="flex shrink-0 items-center gap-2"
                >
                    <span
                        className="flex size-7 items-center justify-center rounded-sm bg-foreground"
                        aria-hidden="true"
                    >
                        <Truck className="size-4 text-background" />
                    </span>
                    <span className="text-base font-bold tracking-tight">
                        Numan Freight
                    </span>
                </Link>

                <nav
                    aria-label="Freight"
                    className="hidden items-center gap-1 md:flex"
                >
                    {navLinks.map((link) => (
                        <Link
                            key={link.href.url}
                            href={link.href}
                            aria-current={
                                currentUrl === link.href.url
                                    ? 'page'
                                    : undefined
                            }
                            className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                currentUrl === link.href.url
                                    ? 'text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            {link.label}
                        </Link>
                    ))}
                    <span className="ml-2 flex items-center gap-2">
                        <AccountLinks canRegister={canRegister} />
                    </span>
                </nav>

                <Sheet
                    open={mobileMenuOpen}
                    onOpenChange={setMobileMenuOpen}
                >
                    <SheetTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="md:hidden"
                            aria-label="Open navigation menu"
                        >
                            <Menu className="size-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent
                        side="right"
                        className="flex flex-col gap-4"
                    >
                        <nav
                            aria-label="Freight"
                            className="flex flex-col gap-1"
                        >
                            {navLinks.map((link) => (
                                <Link
                                    key={link.href.url}
                                    href={link.href}
                                    aria-current={
                                        currentUrl === link.href.url
                                            ? 'page'
                                            : undefined
                                    }
                                    onClick={() => setMobileMenuOpen(false)}
                                    className={`rounded-md px-3 py-2 text-sm font-medium ${
                                        currentUrl === link.href.url
                                            ? 'text-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    {link.label}
                                </Link>
                            ))}
                        </nav>
                        <div className="flex flex-col gap-2 border-t pt-4">
                            <AccountLinks
                                canRegister={canRegister}
                                onNavigate={() => setMobileMenuOpen(false)}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </header>
    );
}

export function DiscoveryFooter() {
    return (
        <footer className="border-t">
            <div className="mx-auto flex w-full max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted-foreground sm:flex-row sm:px-6 lg:px-8">
                <p>© {new Date().getFullYear()} Numan Freight</p>
                <nav
                    aria-label="Footer"
                    className="flex flex-wrap items-center justify-center gap-4"
                >
                    <Link
                        href={discovery()}
                        className="transition-colors hover:text-foreground"
                    >
                        Browse freight
                    </Link>
                    <Link
                        href={myFreight()}
                        className="transition-colors hover:text-foreground"
                    >
                        Post a load
                    </Link>
                    <Link
                        href={myInterests()}
                        className="transition-colors hover:text-foreground"
                    >
                        My interests
                    </Link>
                </nav>
            </div>
        </footer>
    );
}
