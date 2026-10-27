import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgeCheck,
    Building2,
    Download,
    ExternalLink,
    FileText,
    Mail,
    MapPin,
    Package,
    Phone,
    ShieldQuestion,
    Truck,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';

interface LocationSummary {
    id: number;
    name: string;
    region_name: string | null;
}

interface ProfileDocument {
    id: number;
    type: string;
    original_name: string;
    status: string;
    mime: string | null;
    size: number | null;
    reviewed_at: string | null;
    reviewer: string | null;
    uploaded_at: string | null;
}

interface Moderation {
    id: number;
    kind: string;
    from_status: string | null;
    to_status: string;
    reason: string | null;
    moderator: string | null;
    created_at: string | null;
}

interface Profile {
    id: number;
    slug: string;
    display_name: string;
    type: 'consignor' | 'transporter' | 'both';
    account_type: string | null;
    phone: string | null;
    whatsapp: string | null;
    email: string | null;
    business_description: string | null;
    legal_name: string | null;
    legal_form: string | null;
    legal_form_other: string | null;
    posts_freight: boolean;
    provides_transport: boolean;
    phone_visible: boolean;
    whatsapp_visible: boolean;
    email_visible: boolean;
    status: string | null;
    verification_status: string | null;
    verification_note: string | null;
    verification_requested_at: string | null;
    verification_reviewed_at: string | null;
    completion_score: number;
    primary_location: LocationSummary | null;
    truck_types: { id: number; name: string }[];
    routes: { id: number; origin: string | null; destination: string | null }[];
    documents: ProfileDocument[];
    moderations: Moderation[];
    user: { id: number; name: string; email: string };
    created_at: string | null;
    updated_at: string | null;
}

interface Props {
    profile: Profile;
    public_url: string;
}

const TYPE_LABELS: Record<Profile['type'], string> = {
    consignor: 'Consignor',
    transporter: 'Transport carrier',
    both: 'Consignor and carrier',
};

const LISTING_CONFIG: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-muted text-muted-foreground' },
    pending: {
        label: 'Pending review',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    approved: {
        label: 'Approved',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    rejected: {
        label: 'Rejected',
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

const VERIFICATION_CONFIG: Record<string, { label: string; className: string }> = {
    unverified: {
        label: 'Unverified',
        className: 'bg-muted text-muted-foreground',
    },
    pending: {
        label: 'Pending review',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    verified: {
        label: 'Verified',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    },
    rejected: {
        label: 'Rejected',
        className: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

const DOCUMENT_LABELS: Record<string, string> = {
    license: 'Transport licence',
    insurance: 'Insurance policy',
    business_registration: 'Business registration',
    id: 'Identity document',
    other: 'Other document',
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat('en', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          }).format(date);
}

export default function AdminProfileShow({ profile, public_url }: Props) {
    const page = usePage<SharedData & { errors: Record<string, string> }>();
    const flash = page.props.flash;
    const errors = page.props.errors ?? {};

    const [rejectionReason, setRejectionReason] = useState('');
    const [verificationNote, setVerificationNote] = useState('');

    const listing = profile.status ? LISTING_CONFIG[profile.status] : null;
    const verification =
        VERIFICATION_CONFIG[profile.verification_status ?? 'unverified'] ??
        VERIFICATION_CONFIG.unverified;

    const moderateListing = (status: 'approved' | 'rejected') => {
        if (status === 'rejected' && rejectionReason.trim() === '') {
            return;
        }

        router.patch(
            `/admin/profiles/${profile.slug}/listing`,
            {
                status,
                reason: status === 'rejected' ? rejectionReason : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => setRejectionReason(''),
            },
        );
    };

    const moderateVerification = (
        status: 'verified' | 'rejected' | 'unverified' | 'pending',
    ) => {
        if (status === 'rejected' && verificationNote.trim() === '') {
            return;
        }

        router.patch(
            `/admin/profiles/${profile.slug}/verification`,
            {
                status,
                note: status === 'rejected' ? verificationNote : undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => setVerificationNote(''),
            },
        );
    };

    const reviewDocument = (document: ProfileDocument, status: string) => {
        router.patch(
            `/admin/profiles/documents/${document.id}/review`,
            { status },
            { preserveScroll: true },
        );
    };

    return (
        <AdminLayout>
            <Head title={`Profile: ${profile.display_name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                <div className="flex flex-wrap items-start gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/admin/profiles">
                            <ArrowLeft className="mr-1.5 size-4" />
                            Back to profiles
                        </Link>
                    </Button>

                    <div className="flex-1">
                        <h2 className="text-2xl font-bold tracking-tight">
                            {profile.display_name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {profile.user.name} · {profile.user.email}
                        </p>
                    </div>

                    <Button variant="outline" size="sm" asChild>
                        <a
                            href={public_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="mr-1.5 size-4" />
                            Open public page
                        </a>
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">
                        {profile.type === 'transporter' ? (
                            <Truck className="mr-1 size-3.5" />
                        ) : profile.type === 'both' ? (
                            <Building2 className="mr-1 size-3.5" />
                        ) : (
                            <Package className="mr-1 size-3.5" />
                        )}
                        {TYPE_LABELS[profile.type]}
                    </Badge>

                    <Badge variant="outline">
                        <UserRound className="mr-1 size-3.5" />
                        {profile.account_type === 'business'
                            ? 'Business'
                            : 'Individual'}
                    </Badge>

                    {listing ? (
                        <Badge className={listing.className}>
                            {listing.label}
                        </Badge>
                    ) : (
                        <Badge variant="outline">No carrier listing</Badge>
                    )}

                    <Badge className={verification.className}>
                        {profile.verification_status === 'verified' ? (
                            <BadgeCheck className="mr-1 size-3.5" />
                        ) : (
                            <ShieldQuestion className="mr-1 size-3.5" />
                        )}
                        {verification.label}
                    </Badge>
                </div>

                {flash?.success && (
                    <div className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700 dark:bg-emerald-900/30">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Identity</CardTitle>
                                <CardDescription>
                                    The contact details a reveal would release.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Display name
                                    </p>
                                    <p className="text-sm font-medium">
                                        {profile.display_name}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Legal name
                                    </p>
                                    <p className="text-sm">
                                        {profile.legal_name ?? '—'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Email
                                    </p>
                                    <p className="flex items-center gap-1.5 text-sm">
                                        <Mail className="size-3.5 text-muted-foreground" />
                                        {profile.email ?? '—'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Phone
                                    </p>
                                    <p className="flex items-center gap-1.5 text-sm">
                                        <Phone className="size-3.5 text-muted-foreground" />
                                        {profile.phone ?? '—'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        WhatsApp
                                    </p>
                                    <p className="text-sm">
                                        {profile.whatsapp ?? '—'}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Base location
                                    </p>
                                    <p className="flex items-center gap-1.5 text-sm">
                                        <MapPin className="size-3.5 text-muted-foreground" />
                                        {profile.primary_location
                                            ? `${profile.primary_location.name}${profile.primary_location.region_name ? ` · ${profile.primary_location.region_name}` : ''}`
                                            : '—'}
                                    </p>
                                </div>

                                <div className="sm:col-span-2">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Published contact
                                    </p>
                                    <p className="text-sm">
                                        Phone{' '}
                                        {profile.phone_visible
                                            ? 'allowed'
                                            : 'hidden'}{' '}
                                        · WhatsApp{' '}
                                        {profile.whatsapp_visible
                                            ? 'allowed'
                                            : 'hidden'}{' '}
                                        · Email{' '}
                                        {profile.email_visible
                                            ? 'allowed'
                                            : 'hidden'}
                                    </p>
                                </div>

                                {profile.business_description && (
                                    <div className="sm:col-span-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            About
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {profile.business_description}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {profile.provides_transport && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Carrier details</CardTitle>
                                    <CardDescription>
                                        What the directory shows once the listing
                                        is approved.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Truck types
                                        </p>
                                        {profile.truck_types.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                None selected.
                                            </p>
                                        ) : (
                                            <div className="mt-1 flex flex-wrap gap-2">
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
                                        )}
                                    </div>

                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Routes
                                        </p>
                                        {profile.routes.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                No regular routes.
                                            </p>
                                        ) : (
                                            <ul className="mt-1 space-y-1 text-sm">
                                                {profile.routes.map((route) => (
                                                    <li
                                                        key={route.id}
                                                        className="flex items-center gap-1.5"
                                                    >
                                                        <MapPin className="size-3.5 text-muted-foreground" />
                                                        {route.origin ?? '—'} →{' '}
                                                        {route.destination ??
                                                            '—'}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>

                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Completion
                                        </p>
                                        <div className="mt-1 flex items-center gap-2">
                                            <div className="h-2 w-40 rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary"
                                                    style={{
                                                        width: `${profile.completion_score}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm text-muted-foreground">
                                                {profile.completion_score}%
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="size-4" />
                                    Documents
                                </CardTitle>
                                <CardDescription>
                                    Private identity material. Only the owner
                                    and a superadmin can download these.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {profile.documents.length === 0 ? (
                                    <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                                        No documents uploaded.
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-md border">
                                        {profile.documents.map((document) => (
                                            <li
                                                key={document.id}
                                                className="flex flex-wrap items-center justify-between gap-3 px-3 py-3"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {document.original_name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {DOCUMENT_LABELS[
                                                            document.type
                                                        ] ?? document.type}
                                                        {document.reviewer
                                                            ? ` · ${document.reviewer}`
                                                            : ''}
                                                        {` · ${formatDate(document.reviewed_at)}`}
                                                    </p>
                                                </div>

                                                <div className="flex flex-wrap items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="capitalize"
                                                    >
                                                        {document.status}
                                                    </Badge>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/admin/profiles/documents/${document.id}/download`}
                                                        >
                                                            <Download className="mr-1 size-3.5" />
                                                            Download
                                                        </a>
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            document.status ===
                                                            'approved'
                                                        }
                                                        onClick={() =>
                                                            reviewDocument(
                                                                document,
                                                                'approved',
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            document.status ===
                                                            'rejected'
                                                        }
                                                        onClick={() =>
                                                            reviewDocument(
                                                                document,
                                                                'rejected',
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Moderation history</CardTitle>
                                <CardDescription>
                                    Every listing and verification decision
                                    against this profile.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {profile.moderations.length === 0 ? (
                                    <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                                        No moderation decisions yet.
                                    </p>
                                ) : (
                                    <ul className="divide-y rounded-md border">
                                        {profile.moderations.map(
                                            (moderation) => (
                                                <li
                                                    key={moderation.id}
                                                    className="space-y-1 px-3 py-3"
                                                >
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className="capitalize"
                                                        >
                                                            {moderation.kind}
                                                        </Badge>
                                                        <span className="text-sm">
                                                            {moderation.from_status ??
                                                                'none'}{' '}
                                                            →{' '}
                                                            {moderation.to_status}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDate(
                                                                moderation.created_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {moderation.reason && (
                                                        <p className="text-sm text-muted-foreground">
                                                            {moderation.reason}
                                                        </p>
                                                    )}
                                                    <p className="text-xs text-muted-foreground">
                                                        By{' '}
                                                        {moderation.moderator ??
                                                            'system'}
                                                    </p>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        {profile.provides_transport && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Carrier listing</CardTitle>
                                    <CardDescription>
                                        Approval puts the profile in the public
                                        directory.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <InputError message={errors.status} />

                                    <Button
                                        className="w-full"
                                        disabled={profile.status === 'approved'}
                                        onClick={() =>
                                            moderateListing('approved')
                                        }
                                    >
                                        Approve listing
                                    </Button>

                                    <div className="space-y-2">
                                        <Textarea
                                            value={rejectionReason}
                                            onChange={(event) =>
                                                setRejectionReason(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Reason for rejection"
                                        />
                                        <InputError
                                            message={errors.reason}
                                        />
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            disabled={
                                                rejectionReason.trim() === ''
                                            }
                                            onClick={() =>
                                                moderateListing('rejected')
                                            }
                                        >
                                            Reject listing
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Verification</CardTitle>
                                <CardDescription>
                                    One badge covers posting and carrying.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {profile.verification_requested_at && (
                                    <p className="text-xs text-muted-foreground">
                                        Requested{' '}
                                        {formatDate(
                                            profile.verification_requested_at,
                                        )}
                                    </p>
                                )}

                                {profile.verification_status === 'rejected' &&
                                    profile.verification_note && (
                                        <p className="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                                            {profile.verification_note}
                                        </p>
                                    )}

                                <InputError message={errors.note} />

                                <Button
                                    className="w-full"
                                    disabled={
                                        profile.verification_status ===
                                        'verified'
                                    }
                                    onClick={() =>
                                        moderateVerification('verified')
                                    }
                                >
                                    Verify identity
                                </Button>

                                <div className="space-y-2">
                                    <Textarea
                                        value={verificationNote}
                                        onChange={(event) =>
                                            setVerificationNote(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Note for the owner"
                                    />
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        disabled={
                                            verificationNote.trim() === ''
                                        }
                                        onClick={() =>
                                            moderateVerification('rejected')
                                        }
                                    >
                                        Reject verification
                                    </Button>
                                </div>

                                <Button
                                    variant="ghost"
                                    className="w-full"
                                    disabled={
                                        profile.verification_status ===
                                            'unverified' ||
                                        profile.verification_status === null
                                    }
                                    onClick={() =>
                                        moderateVerification('unverified')
                                    }
                                >
                                    Reset to unverified
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Account</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <p>
                                    <span className="text-muted-foreground">
                                        Login:{' '}
                                    </span>
                                    {profile.user.email}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Joined:{' '}
                                    </span>
                                    {formatDate(profile.created_at)}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Updated:{' '}
                                    </span>
                                    {formatDate(profile.updated_at)}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Slug:{' '}
                                    </span>
                                    {profile.slug}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
