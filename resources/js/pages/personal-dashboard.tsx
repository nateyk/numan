import { PostFreightButton } from '@/components/profile/post-freight-button';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Compass,
    CreditCard,
    FileText,
    Package,
    Plus,
    Settings2,
    Truck,
    UserRound,
} from 'lucide-react';

/**
 * A person's home is their freight and public profile, not their underlying
 * workspace. The workspace still holds billing and ownership behind the UI.
 */
interface FreightDashboardProps {
    freightStats: {
        total: number;
        active: number;
        drafts: number;
        arrived: number;
    };
    availableFreight: number;
    recentFreight: Array<{
        ulid: string;
        title: string;
        route: string;
        status: string;
    }>;
}

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    under_review: 'In review',
    active: 'Active',
    closed: 'Closed',
    expired: 'Expired',
    rejected: 'Rejected',
    arrived: 'Arrived',
};

export default function PersonalDashboard() {
    const {
        auth,
        currentWorkspace,
        freightStats,
        recentFreight,
        availableFreight,
    } = usePage<SharedData & FreightDashboardProps>().props;
    const { t } = useTranslations();
    const firstName = auth.user.name?.trim().split(/\s+/)[0] || '';
    const profileScore = auth.user.profile_completeness?.score;
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.title', 'Dashboard'), href: '/dashboard' },
    ];
    const overview = [
        {
            label: 'Total posts',
            value: freightStats.total,
            description: 'All your listings',
            href: '/my-freight',
            icon: Package,
        },
        {
            label: 'Active posts',
            value: freightStats.active,
            description: 'Active status',
            href: '/my-freight?status=active',
            icon: Truck,
        },
        {
            label: 'Drafts',
            value: freightStats.drafts,
            description: 'Not yet submitted',
            href: '/my-freight?status=draft',
            icon: FileText,
        },
        {
            label: 'Arrived',
            value: freightStats.arrived,
            description: 'Delivery confirmed',
            href: '/my-freight?status=arrived',
            icon: CheckCircle2,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('dashboard.title', 'Dashboard')} />

            <main className="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 pb-10 sm:p-6 lg:gap-8 lg:p-8">
                <header className="flex flex-col gap-5 border-b pb-6 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0 space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                            {t(
                                'dashboard.welcome_back',
                                'Welcome back, {{name}}!',
                                {
                                    name: firstName,
                                },
                            )}
                        </h1>
                        <p className="max-w-xl text-sm text-muted-foreground sm:text-base">
                            {t(
                                'dashboard.personal_description',
                                'Manage your freight and public profile from one place.',
                            )}
                        </p>
                    </div>
                    <PostFreightButton className="w-full shrink-0 gap-2 sm:w-auto">
                        <Plus className="size-4" aria-hidden="true" />
                        {t('dashboard.create_freight', 'Post Freight')}
                    </PostFreightButton>
                </header>

                <section
                    aria-labelledby="freight-overview-title"
                    className="space-y-3"
                >
                    <h2
                        id="freight-overview-title"
                        className="text-base font-semibold"
                    >
                        Freight overview
                    </h2>
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {overview.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                className="flex min-w-0 flex-col gap-3 rounded-lg border bg-card p-4 shadow-xs transition-colors hover:border-primary/30 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                            >
                                <span className="flex items-center justify-between gap-2 text-sm font-medium text-muted-foreground">
                                    <span className="min-w-0 truncate">
                                        {item.label}
                                    </span>
                                    <item.icon
                                        className="size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                </span>
                                <span className="text-2xl font-semibold tracking-tight tabular-nums">
                                    {item.value.toLocaleString()}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {item.description}
                                </span>
                            </Link>
                        ))}
                    </div>
                </section>

                <section
                    aria-labelledby="freight-actions-title"
                    className="space-y-3"
                >
                    <h2
                        id="freight-actions-title"
                        className="text-base font-semibold"
                    >
                        {t('dashboard.freight_tools', 'Freight')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Card className="gap-3 shadow-none">
                            <CardHeader className="space-y-2">
                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <Package
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <CardTitle className="text-lg">
                                    {t('navigation.my_freight', 'My Freight')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'dashboard.my_freight_description',
                                        'See your posts, drafts, and their status.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/my-freight">
                                        {t(
                                            'dashboard.view_freight',
                                            'View my freight',
                                        )}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="gap-3 shadow-none">
                            <CardHeader className="space-y-2">
                                <div className="flex size-10 items-center justify-center rounded-lg bg-muted">
                                    <Compass
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </div>
                                <CardTitle className="text-lg">
                                    {t(
                                        'navigation.discover_freight',
                                        'Discover Freight',
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'dashboard.discover_description',
                                        'Browse available loads and find opportunities.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                <span className="text-sm text-muted-foreground">
                                    <span className="font-semibold text-foreground tabular-nums">
                                        {availableFreight.toLocaleString()}
                                    </span>{' '}
                                    available now
                                </span>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/freight">
                                        {t(
                                            'dashboard.browse_freight',
                                            'Browse freight',
                                        )}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <section
                    aria-labelledby="recent-freight-title"
                    className="space-y-3"
                >
                    <div className="flex items-center justify-between gap-3">
                        <h2
                            id="recent-freight-title"
                            className="text-base font-semibold"
                        >
                            Recent freight
                        </h2>
                        <Link
                            href="/my-freight"
                            className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                        >
                            View all
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </div>
                    <Card className="gap-0 overflow-hidden py-0 shadow-none">
                        {recentFreight.length > 0 ? (
                            <ul className="divide-y">
                                {recentFreight.map((post) => (
                                    <li key={post.ulid}>
                                        <Link
                                            href={`/my-freight/${post.ulid}`}
                                            className="flex items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none focus-visible:ring-inset"
                                        >
                                            <span className="min-w-0 space-y-1">
                                                <span className="block truncate text-sm font-medium">
                                                    {post.title}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {post.route}
                                                </span>
                                            </span>
                                            <span className="shrink-0 rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                                                {statusLabels[post.status] ??
                                                    post.status}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="px-4 py-6 text-sm text-muted-foreground">
                                You have not posted freight yet. Start with Post
                                Freight above.
                            </p>
                        )}
                    </Card>
                </section>

                <section aria-labelledby="account-title" className="space-y-3">
                    <h2 id="account-title" className="text-base font-semibold">
                        {t('navigation.account', 'Your account')}
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Card className="gap-3 shadow-none">
                            <CardHeader className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <UserRound
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <CardTitle className="text-base">
                                        {t(
                                            'navigation.my_profile',
                                            'My Profile',
                                        )}
                                    </CardTitle>
                                </div>
                                <CardDescription>
                                    {t(
                                        'dashboard.profile_description',
                                        'Your details and identity on Numan Freight.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {typeof profileScore === 'number' &&
                                    profileScore < 100 && (
                                        <div className="space-y-2">
                                            <div className="flex justify-between gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {t(
                                                        'dashboard.profile_completion',
                                                        'Profile completion',
                                                    )}
                                                </span>
                                                <span>{profileScore}%</span>
                                            </div>
                                            <Progress
                                                value={profileScore}
                                                className="h-1.5"
                                            />
                                        </div>
                                    )}
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/public-profile">
                                        {t(
                                            'dashboard.edit_profile',
                                            'Manage profile',
                                        )}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card className="gap-3 shadow-none">
                            <CardHeader className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <CreditCard
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <CardTitle className="text-base">
                                        {t(
                                            'dashboard.current_plan',
                                            'Current Plan',
                                        )}
                                    </CardTitle>
                                </div>
                                <CardDescription>
                                    {t(
                                        'dashboard.account_plan_description',
                                        'Manage your plan and account preferences.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center gap-x-4 gap-y-3">
                                <span className="mr-auto text-xl font-semibold">
                                    {currentWorkspace?.plan ?? 'Free'}
                                </span>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/billing">
                                        {t(
                                            'dashboard.manage_billing',
                                            'Manage billing',
                                        )}
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link href="/settings/profile">
                                        <Settings2
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {t(
                                            'dashboard.account_settings',
                                            'Account settings',
                                        )}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </main>
        </AppLayout>
    );
}
