import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { Quote, Star } from 'lucide-react';

export function LandingTestimonials() {
    const { t } = useTranslations();

    const testimonials = [
        {
            quote: t(
                'landing.testimonials.items.sarah.quote',
                'NumanFreight cut our sourcing time from days to hours. We post a load in Addis and get verified bids in ETB within minutes — Chapa payouts are instant and tracking keeps our clients updated.',
            ),
            author: 'Alemayehu Bekele',
            role: t(
                'landing.testimonials.items.sarah.role',
                'Fleet Owner, Addis Ababa',
            ),
            avatar: null,
            rating: 5,
        },
        {
            quote: t(
                'landing.testimonials.items.michael.quote',
                'As an importer through Djibouti, verification matters. Every driver and truck is KYC-checked, and we pay securely in ETB via Chapa with proof-of-delivery before release.',
            ),
            author: 'Sara Mohammed',
            role: t(
                'landing.testimonials.items.michael.role',
                'Importer, Dire Dawa & Djibouti Corridor',
            ),
            avatar: null,
            rating: 5,
        },
        {
            quote: t(
                'landing.testimonials.items.emily.quote',
                'The board is live and built for Ethiopia. We move 20+ loads a week via Mojo Dry Port — discovery, bidding in ETB, and live tracking just works, even on low bandwidth.',
            ),
            author: 'Daniel Tadesse',
            role: t(
                'landing.testimonials.items.emily.role',
                'Logistics Manager, Mojo Dry Port',
            ),
            avatar: null,
            rating: 5,
        },
    ];

    const stats = [
        {
            value: '5,000+',
            label: t('landing.testimonials.stats.stars', 'Loads Posted'),
        },
        {
            value: '2,000+',
            label: t(
                'landing.testimonials.stats.features',
                'Verified Carriers',
            ),
        },
        {
            value: 'ETB',
            label: t('landing.testimonials.stats.opensource', 'Via Chapa'),
        },
        {
            value: 'Addis–Djibouti',
            label: t('landing.testimonials.stats.license', 'Nationwide'),
        },
    ];

    return (
        <section id="testimonials" className="py-20 sm:py-32">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {/* Section Header */}
                <div className="mx-auto max-w-3xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">
                        {t('landing.testimonials.title.part1', 'Trusted by')}{' '}
                        <span className="text-primary">
                            {t(
                                'landing.testimonials.title.part2',
                                'Shippers & Carriers',
                            )}
                        </span>{' '}
                        {t(
                            'landing.testimonials.title.part3',
                            'Across Ethiopia',
                        )}
                    </h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        {t(
                            'landing.testimonials.description',
                            'Shippers and carriers moving freight on the Addis–Djibouti corridor and nationwide — with ETB payments via Chapa and verified trucks.',
                        )}
                    </p>
                </div>

                {/* Stats */}
                <div className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-md border bg-card p-4 text-center"
                        >
                            <div className="text-2xl font-bold text-foreground tabular-nums sm:text-3xl">
                                {stat.value}
                            </div>
                            <div className="mt-1 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Testimonials */}
                <div className="mt-10 grid gap-4 md:grid-cols-3">
                    {testimonials.map((testimonial, index) => (
                        <Card key={index} className="relative">
                            <CardContent className="pt-5">
                                <Quote className="h-6 w-6 text-muted-foreground/30" />

                                {/* Stars */}
                                <div className="mt-3 flex gap-0.5">
                                    {Array.from({
                                        length: testimonial.rating,
                                    }).map((_, i) => (
                                        <Star
                                            key={i}
                                            className="h-3.5 w-3.5 fill-accent text-accent"
                                        />
                                    ))}
                                </div>

                                {/* Quote */}
                                <blockquote className="mt-3 text-sm leading-relaxed text-muted-foreground">
                                    "{testimonial.quote}"
                                </blockquote>

                                {/* Author */}
                                <div className="mt-5 flex items-center gap-3">
                                    <div className="flex h-8 w-8 items-center justify-center rounded-sm bg-foreground text-xs font-semibold text-background">
                                        {testimonial.author
                                            .split(' ')
                                            .map((n) => n[0])
                                            .join('')}
                                    </div>
                                    <div>
                                        <div className="text-sm font-medium">
                                            {testimonial.author}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {testimonial.role}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Freight Network CTA */}
                <div className="mt-16">
                    <p className="text-center text-sm text-muted-foreground">
                        {t(
                            'landing.testimonials.community.join',
                            "Join Ethiopia's freight network",
                        )}
                    </p>
                    <div className="mt-5 flex flex-wrap items-center justify-center gap-3">
                        <PostFreightButton
                            className="flex h-auto items-center gap-2 rounded-md border bg-transparent px-3 py-1.5 text-sm font-medium text-foreground shadow-none hover:bg-muted"
                            label={t(
                                'landing.testimonials.community.star',
                                'Post a Load',
                            )}
                        />
                        <a
                            href="/freight"
                            className="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium transition-colors hover:bg-muted"
                        >
                            {t(
                                'landing.testimonials.community.discussions',
                                'ETB via Chapa',
                            )}
                        </a>
                    </div>
                </div>
            </div>
        </section>
    );
}
