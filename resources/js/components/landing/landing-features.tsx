import { useTranslations } from '@/hooks/use-translations';
import {
    BadgeCheck,
    Building2,
    CreditCard,
    MapPinned,
    PackagePlus,
    Route,
    Search,
    ShieldCheck,
    Star,
    Truck,
    Users,
    Zap,
} from 'lucide-react';

export function LandingFeatures() {
    const { t } = useTranslations();

    const features = [
        {
            icon: PackagePlus,
            title: t(
                'landing.features.items.auth.title',
                'Instant Load Posting',
            ),
            description: t(
                'landing.features.items.auth.description',
                'Post loads in seconds — origin, destination, tonnage, truck type, and ETB rate. From Addis to Djibouti, Dire Dawa, Mojo & nationwide.',
            ),
            color: 'text-blue-500',
            bgColor: 'bg-blue-500/10',
        },
        {
            icon: Search,
            title: t(
                'landing.features.items.workspaces.title',
                'Smart Discovery & Matching',
            ),
            description: t(
                'landing.features.items.workspaces.description',
                'Carriers discover loads by route, truck type, and ETB payout. Shippers get matched with verified nearby trucks instantly.',
            ),
            color: 'text-purple-500',
            bgColor: 'bg-purple-500/10',
        },
        {
            icon: CreditCard,
            title: t('landing.features.items.billing.title', 'Chapa ETB Payments'),
            description: t(
                'landing.features.items.billing.description',
                'Secure payments in Ethiopian Birr via Chapa — cards, mobile money, and bank transfer. Transparent fees, instant confirmations.',
            ),
            color: 'text-orange-500',
            bgColor: 'bg-orange-500/10',
        },
        {
            icon: MapPinned,
            title: t('landing.features.items.admin.title', 'Live Tracking'),
            description: t(
                'landing.features.items.admin.description',
                'Track shipments from pickup to delivery with status updates, ETB milestone releases, and proof-of-delivery — Addis–Djibouti corridor and beyond.',
            ),
            color: 'text-rose-500',
            bgColor: 'bg-rose-500/10',
        },
        {
            icon: ShieldCheck,
            title: t('landing.features.items.flags.title', 'Verification & KYC'),
            description: t(
                'landing.features.items.flags.description',
                'KYC-verified carriers, truck plates, and driver documents. BadgeCheck system so shippers book with confidence.',
            ),
            color: 'text-amber-500',
            bgColor: 'bg-amber-500/10',
        },
        {
            icon: BadgeCheck,
            title: t('landing.features.items.audit.title', 'Trusted Reviews'),
            description: t(
                'landing.features.items.audit.description',
                'Ratings and reviews after every trip in ETB. Build reputation for on-time delivery and cargo care across Ethiopia.',
            ),
            color: 'text-teal-500',
            bgColor: 'bg-teal-500/10',
        },
        {
            icon: Route,
            title: t(
                'landing.features.items.announcements.title',
                'Corridor & Nationwide Coverage',
            ),
            description: t(
                'landing.features.items.announcements.description',
                'Optimized for Addis–Djibouti, with coverage to Dire Dawa, Mojo Dry Port, Kombolcha, Hawassa, Bahir Dar and all major corridors.',
            ),
            color: 'text-indigo-500',
            bgColor: 'bg-indigo-500/10',
        },
        {
            icon: Zap,
            title: t('landing.features.items.i18n.title', 'Bidding & Offers'),
            description: t(
                'landing.features.items.i18n.description',
                'Carriers bid in ETB, shippers compare offers and lock in the best rate — fast, competitive, and transparent via Chapa.',
            ),
            color: 'text-cyan-500',
            bgColor: 'bg-cyan-500/10',
        },
        {
            icon: Building2,
            title: t('landing.features.items.dark_mode.title', 'Fleet Workspaces'),
            description: t(
                'landing.features.items.dark_mode.description',
                'Manage fleets, drivers, and trucks under one workspace. Assign roles, track loads, and centralize ETB payouts.',
            ),
            color: 'text-slate-500 dark:text-slate-400',
            bgColor: 'bg-slate-500/10 dark:bg-slate-400/10',
        },
        {
            icon: Users,
            title: t('landing.features.items.stack.title', 'Roles & Permissions'),
            description: t(
                'landing.features.items.stack.description',
                'Owner, admin, and member roles for shippers and transporters. Control who posts loads, accepts bids, and releases ETB.',
            ),
            color: 'text-yellow-500',
            bgColor: 'bg-yellow-500/10',
        },
        {
            icon: Star,
            title: t('landing.features.items.ui.title', 'Ethiopia-First Experience'),
            description: t(
                'landing.features.items.ui.description',
                'Built for Ethiopia — ETB by default, Amharic-ready, lightweight for low-bandwidth, and support for local corridors.',
            ),
            color: 'text-pink-500',
            bgColor: 'bg-pink-500/10',
        },
        {
            icon: Truck,
            title: t('landing.features.items.team.title', 'Carrier Network'),
            description: t(
                'landing.features.items.team.description',
                'Grow your business as a carrier. Find consistent loads, get paid fast in ETB via Chapa, and keep your trucks moving.',
            ),
            color: 'text-green-500',
            bgColor: 'bg-green-500/10',
        },
    ];

    return (
        <section id="features" className="py-20 sm:py-32">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                {/* Section Header */}
                <div className="mx-auto max-w-3xl text-center">
                    <h2 className="text-3xl font-bold tracking-tight sm:text-4xl md:text-5xl">
                        {t(
                            'landing.features.title.part1',
                            'Everything You Need to',
                        )}{' '}
                        <span className="text-primary">
                            {t('landing.features.title.part2', 'Move Freight')}
                        </span>
                    </h2>
                    <p className="mt-4 text-lg text-muted-foreground">
                        {t(
                            'landing.features.description',
                            'From posting a load in Addis to securing a truck to Djibouti, every tool for shippers and carriers — with ETB payments via Chapa and KYC verification built in.',
                        )}
                    </p>
                </div>

                {/* Features Grid */}
                <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {features.map((feature) => (
                        <div
                            key={feature.title}
                            className="group relative rounded-md border bg-card p-5 transition-colors hover:border-primary/40 hover:bg-card"
                        >
                            <div
                                className={`inline-flex h-10 w-10 items-center justify-center rounded-md ${feature.bgColor}`}
                            >
                                <feature.icon
                                    className={`h-5 w-5 ${feature.color}`}
                                />
                            </div>
                            <h3 className="mt-3 text-sm font-semibold">
                                {feature.title}
                            </h3>
                            <p className="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                {feature.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
