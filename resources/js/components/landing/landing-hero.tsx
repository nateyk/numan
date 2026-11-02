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
            className="relative isolate overflow-hidden border-b bg-[#F5FAFD] dark:bg-[#091A2D]"
        >
            <div
                aria-hidden="true"
                className="absolute -top-48 -right-44 -z-10 size-[34rem] rounded-full bg-[#2D9CDB]/10 blur-3xl"
            />
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-16 lg:px-8 lg:py-28">
                <div className="max-w-2xl">
                    <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-[#2D9CDB]/25 bg-white/80 px-3.5 py-1.5 text-sm font-medium text-[#0F294A] shadow-sm dark:bg-slate-900 dark:text-slate-100">
                        <span className="size-2 rounded-full bg-[#2D9CDB]" />
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
                    <p className="mt-6 max-w-xl text-lg leading-8 text-slate-600 sm:text-xl dark:text-slate-300">
                        {t(
                            'brand.home.hero.description',
                            'Post a load, find freight, and connect with carriers. Numan Freight brings the people moving goods across Ethiopia into one marketplace.',
                        )}
                    </p>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                        <PostFreightButton
                            size="lg"
                            className="h-12 bg-[#0F294A] px-6 text-white hover:bg-[#173B67] dark:bg-[#2D9CDB] dark:text-[#0F294A] dark:hover:bg-[#65BCEB]"
                            label={t('brand.home.nav.post_load', 'Post a load')}
                        />
                        <Button
                            asChild
                            size="lg"
                            variant="outline"
                            className="h-12 border-[#0F294A]/20 bg-white px-6 text-[#0F294A] hover:bg-[#0F294A]/5 dark:border-slate-600 dark:bg-slate-900 dark:!text-slate-100 dark:hover:bg-slate-800"
                        >
                            <Link href="/freight">
                                {t('brand.home.nav.browse', 'Browse freight')}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    </div>

                    <div className="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-slate-600">
                        <span className="inline-flex items-center gap-2 dark:text-slate-300">
                            <Package className="size-4 text-[#2D9CDB]" />
                            {t(
                                'brand.home.hero.point.loads',
                                'Loads with clear routes and cargo details',
                            )}
                        </span>
                        <span className="inline-flex items-center gap-2 dark:text-slate-300">
                            <Truck className="size-4 text-[#2D9CDB]" />
                            {t(
                                'brand.home.hero.point.carriers',
                                'Carriers ready to find their next run',
                            )}
                        </span>
                    </div>
                </div>

                <div className="relative mx-auto w-full max-w-xl lg:ml-auto">
                    <div className="absolute -inset-4 rounded-[2rem] bg-[#2D9CDB]/10 blur-xl" />
                    <div className="relative overflow-hidden rounded-2xl border border-[#0F294A]/10 bg-white shadow-[0_24px_70px_-35px_rgba(15,41,74,0.35)] dark:border-white/10 dark:bg-slate-900">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-6">
                            <div>
                                <p className="text-sm font-semibold text-[#0F294A] dark:text-slate-100">
                                    {t(
                                        'brand.home.hero.route_title',
                                        'Every load starts with a route',
                                    )}
                                </p>
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
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
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#2D9CDB]/10 text-[#0F294A] dark:text-sky-300">
                                    <MapPin className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        {t(
                                            'brand.home.hero.origin_label',
                                            'Origin',
                                        )}
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-[#0F294A] dark:text-slate-100">
                                        {t(
                                            'brand.home.hero.origin_value',
                                            'Where the freight begins',
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="ml-5 h-8 border-l-2 border-dashed border-[#2D9CDB]/50" />
                            <div className="flex items-center gap-4 py-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0F294A] text-white">
                                    <MapPin className="size-5" />
                                </div>
                                <div>
                                    <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        {t(
                                            'brand.home.hero.destination_label',
                                            'Destination',
                                        )}
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-[#0F294A] dark:text-slate-100">
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
                            className="flex items-center justify-between bg-[#F3F8FB] px-5 py-4 text-sm font-medium text-[#0F294A] transition-colors hover:bg-[#EAF4FA] sm:px-6 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                        >
                            {t(
                                'brand.home.hero.route_link',
                                'Explore freight on the board',
                            )}
                            <ArrowDown className="size-4 -rotate-90 text-[#2D9CDB]" />
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    );
}
