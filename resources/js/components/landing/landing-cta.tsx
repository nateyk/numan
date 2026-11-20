import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { Link } from '@inertiajs/react';
import { ArrowRight, MapPinned } from 'lucide-react';

export function LandingCta() {
    const { t } = useTranslations();

    return (
        <section className="px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            <div className="mx-auto w-full max-w-6xl rounded-xl border border-border bg-secondary/60 px-6 py-12 text-center sm:px-12 sm:py-16">
                <Badge
                    variant="outline"
                    className="mb-5 gap-2 tracking-widest uppercase"
                >
                    <MapPinned aria-hidden="true" className="size-3" />
                    {t('brand.home.cta.eyebrow', 'Get moving')}
                </Badge>
                <h2 className="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                    {t(
                        'brand.home.cta.final_title',
                        'Your next freight journey starts here.',
                    )}
                </h2>
                <p className="mx-auto mt-4 max-w-xl text-base leading-7 text-muted-foreground">
                    {t(
                        'brand.home.cta.final_description',
                        'Post the load you need to move or explore available freight routes. Numan Freight brings both sides together in one marketplace.',
                    )}
                </p>
                <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <PostFreightButton
                        size="lg"
                        className="h-11 w-full sm:w-auto"
                        label={t('brand.home.nav.post_load', 'Post a load')}
                    />
                    <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="h-11 w-full sm:w-auto"
                    >
                        <Link href="/freight">
                            {t('brand.home.nav.browse', 'Browse freight')}
                            <ArrowRight aria-hidden="true" className="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    );
}
