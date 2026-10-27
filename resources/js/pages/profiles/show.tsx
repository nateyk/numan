import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    FileCheck2,
    FileText,
    Info,
    MapPin,
    Package,
    Route,
    ShieldCheck,
    Truck,
    UserRound,
} from 'lucide-react';

interface PublicProfile {
    id: number;
    slug: string;
    display_name: string;
    account_type: string | null;
    status: string | null;
    verification_status: string | null;
    is_verified: boolean;
    posts_freight: boolean;
    provides_transport: boolean;
    business_description: string | null;
    primary_location: {
        id: number;
        name: string;
        region_name: string | null;
    } | null;
    member_since: string | null;
    truck_types: { id: number; name: string; slug: string }[];
    routes: { id: number; origin: string | null; destination: string | null }[];
    has_documents: boolean;
    documents_reviewed: boolean;
}

interface ContactPreview {
    name: string;
    phone: string | null;
    email: string | null;
    whatsapp: string | null;
}

interface Props {
    profile: PublicProfile;
    is_owner: boolean;
    already_revealed: boolean;
    reveal_context_url?: string;
    contact_preview?: ContactPreview;
    noindex: boolean;
}

const statusLabel = (status: string | null): string => {
    switch (status) {
        case 'approved':
            return 'Approved';
        case 'pending':
            return 'Pending review';
        case 'rejected':
            return 'Rejected';
        case 'draft':
            return 'Draft';
        default:
            return 'Not listed';
    }
};

const verificationLabel = (status: string | null): string => {
    switch (status) {
        case 'verified':
            return 'Verified';
        case 'pending':
            return 'Verification pending';
        case 'rejected':
            return 'Verification rejected';
        default:
            return 'Unverified';
    }
};

function formatMonth(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en', {
        month: 'long',
        year: 'numeric',
    }).format(date);
}

export default function ProfileShow({
    profile,
    is_owner,
    already_revealed,
    reveal_context_url,
    contact_preview,
    noindex,
}: Props) {
    const memberSince = formatMonth(profile.member_since);
    const locationLine = profile.primary_location
        ? [profile.primary_location.name, profile.primary_location.region_name]
              .filter(Boolean)
              .join(', ')
        : null;

    return (
        <>
            <Head title={`${profile.display_name} — Numan Freight`}>
                {noindex && <meta name="robots" content="noindex" />}
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                <DiscoveryHeader />

                <main>
                    <div className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                        {is_owner && (
                            <div className="mb-6 rounded-md border border-blue-300 bg-blue-50 px-4 py-3 text-sm dark:border-blue-800 dark:bg-blue-950/40">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <p className="font-medium">
                                        This is how your profile appears to
                                        other people.
                                    </p>
                                    <Button size="sm" asChild>
                                        <Link href="/public-profile">
                                            Edit my profile
                                        </Link>
                                    </Button>
                                </div>
                                <div className="mt-2 flex flex-wrap gap-2 text-xs">
                                    <Badge variant="outline">
                                        Listing: {statusLabel(profile.status)}
                                    </Badge>
                                    <Badge variant="outline">
                                        {verificationLabel(
                                            profile.verification_status,
                                        )}
                                    </Badge>
                                </div>
                            </div>
                        )}

                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                            <div className="flex flex-col gap-6">
                                <section className="rounded-lg border bg-card p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <h1 className="text-2xl font-bold tracking-tight">
                                                {profile.display_name}
                                            </h1>
                                            <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                                                <span className="flex items-center gap-1.5">
                                                    {profile.account_type ===
                                                    'business' ? (
                                                        <Building2 className="size-4" />
                                                    ) : (
                                                        <UserRound className="size-4" />
                                                    )}
                                                    {profile.account_type ===
                                                    'business'
                                                        ? 'Business'
                                                        : profile.account_type ===
                                                            'personal'
                                                          ? 'Individual'
                                                          : 'Account'}
                                                </span>
                                                {locationLine && (
                                                    <span className="flex items-center gap-1.5">
                                                        <MapPin className="size-4" />
                                                        {locationLine}
                                                    </span>
                                                )}
                                                {memberSince && (
                                                    <span>
                                                        Member since{' '}
                                                        {memberSince}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        {profile.is_verified && (
                                            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                                <BadgeCheck className="mr-1 size-3.5" />
                                                Verified
                                            </Badge>
                                        )}
                                    </div>

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {profile.posts_freight && (
                                            <Badge variant="secondary">
                                                <Package className="mr-1 size-3.5" />
                                                Posts freight
                                            </Badge>
                                        )}
                                        {profile.provides_transport && (
                                            <Badge variant="secondary">
                                                <Truck className="mr-1 size-3.5" />
                                                Provides transport
                                            </Badge>
                                        )}
                                    </div>

                                    {profile.business_description && (
                                        <div className="mt-6">
                                            <h2 className="text-sm font-semibold">
                                                About
                                            </h2>
                                            <p className="mt-2 text-sm whitespace-pre-line text-muted-foreground">
                                                {profile.business_description}
                                            </p>
                                        </div>
                                    )}
                                </section>

                                {profile.provides_transport && (
                                    <section className="rounded-lg border bg-card p-6">
                                        <h2 className="flex items-center gap-2 text-base font-semibold">
                                            <Truck className="size-4" />
                                            Carrier details
                                        </h2>

                                        <div className="mt-4 grid gap-6 sm:grid-cols-2">
                                            <div>
                                                <h3 className="text-sm font-medium">
                                                    Truck types
                                                </h3>
                                                {profile.truck_types.length >
                                                0 ? (
                                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                                        {profile.truck_types.map(
                                                            (type) => (
                                                                <Badge
                                                                    key={type.id}
                                                                    variant="outline"
                                                                >
                                                                    {type.name}
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                ) : (
                                                    <p className="mt-2 text-sm text-muted-foreground">
                                                        Not listed.
                                                    </p>
                                                )}
                                            </div>

                                            <div>
                                                <h3 className="flex items-center gap-1.5 text-sm font-medium">
                                                    <Route className="size-3.5" />
                                                    Routes
                                                </h3>
                                                {profile.routes.length > 0 ? (
                                                    <ul className="mt-2 flex flex-col gap-1.5 text-sm text-muted-foreground">
                                                        {profile.routes.map(
                                                            (route) => (
                                                                <li
                                                                    key={
                                                                        route.id
                                                                    }
                                                                >
                                                                    {route.origin ??
                                                                        'Unknown'}{' '}
                                                                    →{' '}
                                                                    {route.destination ??
                                                                        'Unknown'}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                ) : (
                                                    <p className="mt-2 text-sm text-muted-foreground">
                                                        Not listed.
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="mt-6 flex flex-wrap gap-4 border-t pt-4 text-sm text-muted-foreground">
                                            <span className="flex items-center gap-1.5">
                                                {profile.documents_reviewed ? (
                                                    <FileCheck2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                                                ) : (
                                                    <FileText className="size-4" />
                                                )}
                                                {profile.has_documents
                                                    ? profile.documents_reviewed
                                                        ? 'Documents reviewed'
                                                        : 'Documents on file'
                                                    : 'No documents on file'}
                                            </span>
                                            <span className="flex items-center gap-1.5">
                                                <ShieldCheck className="size-4" />
                                                {verificationLabel(
                                                    profile.verification_status,
                                                )}
                                            </span>
                                        </div>
                                    </section>
                                )}
                            </div>

                            <aside className="flex flex-col gap-4">
                                {is_owner && contact_preview ? (
                                    <section className="rounded-lg border bg-card p-5">
                                        <h2 className="text-sm font-semibold">
                                            What a reveal releases
                                        </h2>
                                        <dl className="mt-3 flex flex-col gap-2 text-sm">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Name
                                                </dt>
                                                <dd>{contact_preview.name}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Phone
                                                </dt>
                                                <dd>
                                                    {contact_preview.phone ??
                                                        'Hidden'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    WhatsApp
                                                </dt>
                                                <dd>
                                                    {contact_preview.whatsapp ??
                                                        'Hidden'}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Email
                                                </dt>
                                                <dd>
                                                    {contact_preview.email ??
                                                        'Hidden'}
                                                </dd>
                                            </div>
                                        </dl>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="mt-4 w-full"
                                            asChild
                                        >
                                            <Link href="/public-profile">
                                                Change what is shown
                                            </Link>
                                        </Button>
                                    </section>
                                ) : already_revealed ? (
                                    <section className="rounded-lg border bg-card p-5 text-sm">
                                        <h2 className="flex items-center gap-2 font-semibold">
                                            <Info className="size-4" />
                                            You already have this contact
                                        </h2>
                                        <p className="mt-2 text-muted-foreground">
                                            You revealed this person&apos;s
                                            contact details earlier, so no
                                            further reveal is needed.
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="mt-4 w-full"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    reveal_context_url ??
                                                    '/freight'
                                                }
                                            >
                                                Back to where you met
                                            </Link>
                                        </Button>
                                    </section>
                                ) : (
                                    <section className="rounded-lg border bg-card p-5 text-sm">
                                        <h2 className="flex items-center gap-2 font-semibold">
                                            <Info className="size-4" />
                                            Contact details
                                        </h2>
                                        <p className="mt-2 text-muted-foreground">
                                            Phone, WhatsApp, and email are
                                            released through the freight
                                            board&apos;s reveal action, so this
                                            page never publishes them.
                                        </p>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="mt-4 w-full"
                                            asChild
                                        >
                                            <Link href="/freight">
                                                Browse freight
                                            </Link>
                                        </Button>
                                    </section>
                                )}
                            </aside>
                        </div>
                    </div>
                </main>

                <DiscoveryFooter />
            </div>
        </>
    );
}
