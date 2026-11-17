import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowRight, MapPin, Package, Truck } from 'lucide-react';

export function LandingHero() {
    const { t } = useTranslations();

    return (
        <section
            id="top"
            className="relative isolate overflow-hidden border-b bg-brand-sky-soft"
        >
            <div
                aria-hidden="true"
                className="absolute -top-48 -right-44 -z-10 size-[34rem] rounded-full bg-brand-sky/10 blur-3xl"
            />
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-16 lg:px-8 lg:py-28">
                <div className="max-w-2xl">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-brand-sky/25 bg-card/80 px-3.5 py-1.5 text-sm font-medium text-foreground">
                        <span className="size-2 rounded-full bg-brand-sky" />
                        {t(
                            'brand.home.hero.badge',
                            "Ethiopia's freight marketplace",
                        )}
                    </div>

                    <h1 className="max-w-2xl text-4xl leading-[1.08] font-bold tracking-tight text-[#0F294A] sm:text-5xl lg:text-6xl dark:text-slate-50">
                        {t(
                            'brand.home.hero.title',
                            'A clearer way to move freight.',
                        )}
                    </h1>
                    <p className="mt-6 max-w-xl text-lg leading-8 text-muted-foreground sm:text-xl">
                        {t(
                            'brand.home.hero.description',
                            'Post a load, find freight, and connect with carriers. Numan Freight brings the people moving goods across Ethiopia into one marketplace.',
                        )}
                    </p>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <PostFreightButton
                            size="lg"
                            className="h-12 px-6"
                            label={t('brand.home.nav.post_load', 'Post a load')}
                        />
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-12 px-6"
                        >
                            <Link href="/freight">
                                {t('brand.home.nav.browse', 'Browse freight')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-muted-foreground">
                        <span className="inline-flex items-center gap-2 dark:text-muted-foreground">
                            <Package className="size-4 text-brand-sky-strong dark:text-brand-sky" />
                            {t(
                                'brand.home.hero.point.loads',
                                'Loads with clear routes and cargo details',
                            )}
                        </span>
                        <span className="inline-flex items-center gap-2 dark:text-muted-foreground">
                            <Truck className="size-4 text-brand-sky-strong dark:text-brand-sky" />
                            {t(
                                'brand.home.hero.point.carriers',
                                'Carriers ready to find their next run',
                            )}
                        </span>
                    </div>
                </div>

                <div className="relative mx-auto w-full max-w-xl lg:ml-auto">
                    <div className="absolute -inset-4 rounded-[2rem] bg-brand-sky/10 blur-xl" />
                    <div className="relative overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        <div className="flex items-center justify-between border-b border-border px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-sm font-semibold text-card-foreground">
                                    {t(
                                        'brand.home.hero.route_title',
                                        'Every load starts with a route',
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {t(
                                        'brand.home.hero.route_description',
                                        'Bring the route, cargo, and people together.',
                                    )}
                                </p>
                            </div>
                            <img
                                src="/brand/numanfreight-symbol.svg"
                                alt=""
                                className="h-9 w-12 object-contain"
                            />
                        </div>

                        <div className="space-y-0 px-5 py-3 sm:px-6">
                            <div className="flex items-center gap-4 py-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-sky/10 text-brand-navy dark:text-brand-sky">
                                    <MapPin className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        {t(
                                            'brand.home.hero.origin_label',
                                            'Origin',
                                        )}
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-card-foreground">
                                        {t(
                                            'brand.home.hero.origin_value',
                                            'Where the freight begins',
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="ml-5 h-8 border-l-2 border-dashed border-brand-sky/50" />
                            <div className="flex items-center gap-4 py-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-navy text-white">
                                    <MapPin className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        {t(
                                            'brand.home.hero.destination_label',
                                            'Destination',
                                        )}
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-card-foreground">
                                        {t(
                                            'brand.home.hero.destination_value',
                                            'Where it needs to go',
                                        )}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Link
                            href="/freight"
                            className="flex items-center justify-between bg-muted px-5 py-4 text-sm font-medium text-foreground transition-colors hover:bg-accent sm:px-6"
                        >
                            {t(
                                'brand.home.hero.route_link',
                                'Explore freight on the board',
                            )}
                            <ArrowDown className="size-4 -rotate-90 text-brand-sky-strong dark:text-brand-sky" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
