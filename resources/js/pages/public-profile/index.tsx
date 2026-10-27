import CapabilityFields from '@/components/profile/capability-fields';
import IdentityFields, {
    type IdentityValues,
} from '@/components/profile/identity-fields';
import TransportFields, {
    LISTING_STATUS,
} from '@/components/profile/transport-fields';
import VerificationCard, {
    VERIFICATION_STATUS,
} from '@/components/profile/verification-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useToast } from '@/components/ui/toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    Eye,
    Info,
    Package,
    ShieldQuestion,
    Truck,
    UserRound,
} from 'lucide-react';
import { useEffect } from 'react';

interface ProfileDocument {
    id: number;
    type: string;
    name: string;
    status: string;
    uploaded_at: string | null;
}

interface ProfilePayload extends IdentityValues {
    id: number;
    slug: string;
    posts_freight: boolean;
    provides_transport: boolean;
    status: string | null;
    verification_status: string | null;
    verification_note: string | null;
    completion_score: number;
    truck_type_ids: number[];
    routes: {
        id?: number;
        origin_location_id: number | null;
        destination_location_id: number | null;
    }[];
    documents: ProfileDocument[];
}

interface Props {
    profile: ProfilePayload;
    locations: {
        id: number;
        name: string;
        type: string;
        region_name: string | null;
    }[];
    truckTypes: { id: number; name: string }[];
    accountTypes: string[];
    legalForms: string[];
    completionScore: number;
    listing_rejection_reason: string | null;
}

interface PublicProfileFormValues extends IdentityValues {
    posts_freight: boolean;
    provides_transport: boolean;
}

const ACCOUNT_TYPE_LABELS: Record<string, string> = {
    personal: 'Individual',
    business: 'Business',
};

export default function PublicProfileIndex({
    profile,
    locations,
    truckTypes,
    accountTypes,
    legalForms,
    completionScore,
    listing_rejection_reason,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const { addToast } = useToast();

    useEffect(() => {
        if (flash?.success) {
            addToast(flash.success, 'success');
        }

        if (flash?.error) {
            addToast(flash.error, 'error');
        }
    }, [flash?.success, flash?.error, addToast]);

    const form = useForm<PublicProfileFormValues>({
        display_name: profile.display_name ?? '',
        account_type: profile.account_type ?? 'personal',
        phone: profile.phone ?? '',
        whatsapp: profile.whatsapp ?? '',
        email: profile.email ?? '',
        business_description: profile.business_description ?? '',
        primary_location_id: profile.primary_location_id,
        legal_name: profile.legal_name ?? '',
        legal_form: profile.legal_form ?? '',
        legal_form_other: profile.legal_form_other ?? '',
        phone_visible: profile.phone_visible,
        whatsapp_visible: profile.whatsapp_visible,
        email_visible: profile.email_visible,
        posts_freight: profile.posts_freight,
        provides_transport: profile.provides_transport,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        form.patch('/public-profile', { preserveScroll: true });
    };

    const listing =
        LISTING_STATUS[profile.status ?? 'draft'] ?? LISTING_STATUS.draft;

    const verification =
        VERIFICATION_STATUS[profile.verification_status ?? 'unverified'] ??
        VERIFICATION_STATUS.unverified;

    const savedIsBusiness = profile.account_type === 'business';

    const updateIdentity = <K extends keyof IdentityValues>(
        key: K,
        value: IdentityValues[K],
    ) => {
        form.setData({ ...form.data, [key]: value });
    };

    // The verification endpoint reads the saved profile, not the draft in the
    // form. Derive readiness from the same values so the button cannot be
    // enabled by edits that have not been saved yet.
    const identityReady =
        (profile.display_name ?? '').trim() !== '' &&
        (profile.phone ?? '').trim() !== '' &&
        (!savedIsBusiness ||
            ((profile.legal_name ?? '').trim() !== '' &&
                (profile.legal_form ?? '') !== ''));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Profile', href: '/public-profile' },
    ];

    const listingIsPublic =
        profile.provides_transport && profile.status === 'approved';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My profile" />

            <div className="flex flex-col gap-6">
                <Card>
                    <CardContent className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                {form.data.account_type === 'business' ? (
                                    <Building2 className="size-5 text-muted-foreground" />
                                ) : (
                                    <UserRound className="size-5 text-muted-foreground" />
                                )}
                                <h1 className="truncate text-2xl font-bold tracking-tight">
                                    {profile.display_name ||
                                        'Your public profile'}
                                </h1>
                            </div>

                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    {ACCOUNT_TYPE_LABELS[
                                        form.data.account_type
                                    ] ?? form.data.account_type}
                                </Badge>

                                {form.data.posts_freight && (
                                    <Badge variant="secondary">
                                        <Package className="mr-1 size-3.5" />
                                        Posts freight
                                    </Badge>
                                )}

                                {form.data.provides_transport && (
                                    <Badge variant="secondary">
                                        <Truck className="mr-1 size-3.5" />
                                        Provides transport
                                    </Badge>
                                )}

                                <Badge className={listing.className}>
                                    {listing.label}
                                </Badge>

                                <Badge className={verification.className}>
                                    {profile.verification_status ===
                                    'verified' ? (
                                        <BadgeCheck className="mr-1 size-3.5" />
                                    ) : (
                                        <ShieldQuestion className="mr-1 size-3.5" />
                                    )}
                                    {verification.label}
                                </Badge>
                            </div>

                            <p className="mt-3 flex items-start gap-1.5 text-sm text-muted-foreground">
                                <Info className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {listingIsPublic
                                        ? `Your carrier listing is public at /p/${profile.slug}. Phone, WhatsApp, and email are still released only through a reveal.`
                                        : `Only you can open this page until a carrier listing is approved. Your contact details are never printed on /p/${profile.slug}.`}
                                </span>
                            </p>
                        </div>

                        <Button variant="outline" asChild>
                            <Link href={`/p/${profile.slug}`}>
                                <Eye className="mr-1.5 size-4" />
                                Preview public profile
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Identity</CardTitle>
                            <CardDescription>
                                Who you are on the marketplace. Your display
                                name is public; contact details are released
                                through a reveal.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <IdentityFields
                                values={form.data}
                                errors={form.errors}
                                locations={locations}
                                accountTypes={accountTypes}
                                legalForms={legalForms}
                                onChange={updateIdentity}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>What you do here</CardTitle>
                            <CardDescription>
                                Choose at least one capability. You can change
                                this whenever you need to.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <CapabilityFields
                                values={form.data}
                                errors={form.errors}
                                onChange={(key, value) =>
                                    form.setData(key, value)
                                }
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap items-center gap-4">
                        <Button type="submit" disabled={form.processing}>
                            Save profile
                        </Button>
                        {form.recentlySuccessful && (
                            <p className="text-sm text-muted-foreground">
                                Saved.
                            </p>
                        )}
                    </div>
                </form>

                {profile.provides_transport && (
                    <TransportFields
                        status={profile.status}
                        rejectionReason={listing_rejection_reason}
                        completionScore={completionScore}
                        truckTypes={truckTypes}
                        locations={locations}
                        truckTypeIds={profile.truck_type_ids}
                        routes={profile.routes}
                        documents={profile.documents}
                    />
                )}

                <VerificationCard
                    status={profile.verification_status}
                    note={profile.verification_note}
                    identityReady={identityReady}
                />
            </div>
        </AppLayout>
    );
}
