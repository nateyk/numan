import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { useTranslations } from '@/hooks/use-translations';
import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import { ArrowLeft, MapPin, Package, Truck } from 'lucide-react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { t } = useTranslations();

    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-[#0F294A] p-10 text-white lg:flex">
                <div
                    aria-hidden="true"
                    className="absolute -top-40 -right-44 size-[32rem] rounded-full bg-[#2D9CDB]/20 blur-3xl"
                />
                <div className="relative flex items-center justify-between">
                    <Link
                        href={home()}
                        aria-label="Numan Freight home"
                        className="flex items-center gap-3"
                    >
                        <AppLogoIcon
                            alt=""
                            className="size-10 rounded-lg bg-white object-contain"
                        />
                        <img
                            src="/brand/numanfreight-wordmark-white.svg"
                            alt="Numan Freight"
                            className="h-7 w-auto"
                        />
                    </Link>
                    <Link
                        href={home()}
                        className="flex items-center gap-2 text-sm text-white/65 transition-colors hover:text-white"
                    >
                        <ArrowLeft className="size-4" />
                        {t('auth.layout.back_to_home', 'Back to home')}
                    </Link>
                </div>

                <div className="relative max-w-xl space-y-8">
                    <div className="space-y-4">
                        <p className="text-sm font-semibold tracking-wide text-[#72C5F2] uppercase">
                            {t(
                                'brand.home.hero.badge',
                                "Ethiopia's freight marketplace",
                            )}
                        </p>
                        <h2 className="text-4xl leading-tight font-bold text-white xl:text-5xl">
                            {t(
                                'brand.auth.title',
                                'Move your next load forward.',
                            )}
                        </h2>
                        <p className="max-w-lg text-lg leading-8 text-white/65">
                            {t(
                                'brand.home.hero.description',
                                'Post a load, find freight, and connect with carriers across Ethiopia.',
                            )}
                        </p>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#72C5F2]">
                                <Package className="size-4" />
                            </div>
                            <p className="text-sm leading-6 text-white/75">
                                {t(
                                    'brand.auth.routes',
                                    'Share routes and cargo details with the people who can move them.',
                                )}
                            </p>
                        </div>
                        <div className="flex items-start gap-3 rounded-xl border border-white/10 bg-white/5 p-4">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#72C5F2]">
                                <Truck className="size-4" />
                            </div>
                            <p className="text-sm leading-6 text-white/75">
                                {t(
                                    'brand.auth.carriers',
                                    'Find freight that fits your truck and your route.',
                                )}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 text-sm text-white/60">
                        <MapPin className="size-4 text-[#72C5F2]" />
                        {t(
                            'brand.auth.location',
                            'Built around the routes people travel every day.',
                        )}
                    </div>
                </div>

                <p className="relative text-xs text-white/45">
                    © {new Date().getFullYear()} Numan Freight
                </p>
            </div>

            <div className="flex min-h-svh flex-col">
                <div className="flex items-center justify-between border-b px-4 py-3 lg:hidden">
                    <Link
                        href={home()}
                        aria-label="Numan Freight home"
                        className="flex items-center gap-2"
                    >
                        <AppLogoIcon
                            alt=""
                            className="size-9 rounded-md bg-white object-contain"
                        />
                        <img
                            src="/brand/numanfreight-wordmark.svg"
                            alt="Numan Freight"
                            className="h-6 w-auto max-w-36"
                        />
                    </Link>
                    <AppearanceToggleDropdown />
                </div>

                <div className="flex flex-1 items-center justify-center p-6 md:p-10">
                    <div className="w-full max-w-sm">
                        <div className="mb-8 hidden justify-end lg:flex">
                            <AppearanceToggleDropdown />
                        </div>
                        <div className="flex flex-col gap-6">
                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
