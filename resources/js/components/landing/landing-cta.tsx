import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import { MapPinned } from 'lucide-react';

export function LandingCta() {
    const { t } = useTranslations();

    return (
        <section className="py-16 sm:py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="relative overflow-hidden rounded-lg bg-foreground p-8 sm:p-12 lg:p-16">
                    <div className="mx-auto max-w-3xl text-center">
                        <div className="mb-5 inline-flex items-center gap-2 rounded-sm bg-white/10 px-3 py-1 text-xs font-semibold tracking-wider text-white/80 uppercase">
                            <MapPinned className="h-3.5 w-3.5" />
                            {t(
                                'landing.cta.badge',
                                'Built for Ethiopia • ETB via Chapa',
                            )}
                        </div>

                        <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl md:text-5xl">
                            {t(
                                'landing.cta.title',
                                'Ready to Move Freight Across Ethiopia?',
                            )}
                        </h2>

                        <p className="mt-4 text-lg text-white/60 sm:text-xl">
                            {t(
                                'landing.cta.description',
                                'Join shippers and carriers moving goods on the Addis–Djibouti corridor and nationwide. Post a load or find a truck in minutes — secure ETB payments via Chapa, KYC-verified carriers, and live tracking.',
                            )}
                        </p>

                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            <PostFreightButton
                                size="lg"
                                className="h-11 bg-accent px-7 text-sm font-semibold text-foreground hover:bg-accent/90"
                                label={t(
                                    'landing.cta.get_started',
                                    'Post a Load Free',
                                )}
                            />
                            <Button
                                size="lg"
                                variant="outline"
                                asChild
                                className="h-11 border-white/20 bg-transparent px-7 text-sm text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href="/freight">
                                    {t('landing.cta.github', 'Browse Loads')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
