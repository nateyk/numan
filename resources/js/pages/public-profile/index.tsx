import CapabilityFields from '@/components/profile/capability-fields';
import IdentityFields, {
    type IdentityValues,
} from '@/components/profile/identity-fields';
import ProfileStrength, {
    type ProfileStrengthItem,
} from '@/components/profile/profile-strength';
import TransportFields from '@/components/profile/transport-fields';
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
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/components/ui/toast';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Building2,
    CheckCircle2,
    Eye,
    Info,
    Package,
    Save,
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

    const listingIsPublic =
        profile.provides_transport && profile.status === 'approved';

    const carrierStrengthItems: ProfileStrengthItem[] =
        profile.provides_transport
            ? [
                  {
                      label: 'Truck types and routes',
                      state:
                          profile.truck_type_ids.length > 0 &&
                          profile.routes.length > 0
                              ? 'done'
                              : 'todo',
                      href: '#carrier',
                  },
                  {
                      label: 'Documents',
                      state: profile.documents.length > 0 ? 'done' : 'todo',
                      href: '#documents',
                  },
              ]
            : [];

    const verificationStrength: ProfileStrengthItem = {
        label:
            profile.verification_status === 'pending'
                ? 'Verification in review'
                : profile.verification_status === 'verified'
                  ? 'Identity verified'
                  : 'Identity verification',
        state:
            profile.verification_status === 'verified'
                ? 'done'
                : profile.verification_status === 'pending'
                  ? 'pending'
                  : 'todo',
        href: '#verification',
    };

    const strengthItems: ProfileStrengthItem[] = [
        {
            label: savedIsBusiness
                ? 'Name, phone, and legal details'
                : 'Name and phone',
            state: identityReady ? 'done' : 'todo',
            href: '#identity',
        },
        {
            label: 'Base location',
            state: profile.primary_location_id ? 'done' : 'todo',
            href: '#identity',
        },
        {
            label: 'About you',
            state: profile.business_description?.trim() ? 'done' : 'todo',
            href: '#identity',
        },
        {
            label: 'Marketplace role',
            state:
                profile.posts_freight || profile.provides_transport
                    ? 'done'
                    : 'todo',
            href: '#role',
        },
        ...carrierStrengthItems,
        verificationStrength,
    ];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'My Profile', href: '/public-profile' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs} contentClassName="overflow-x-clip">
            <Head title="Public profile" />

            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <header className="flex flex-col gap-5 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-3">
                        <div className="space-y-1.5">
                            <p className="text-sm font-medium text-muted-foreground">
                                My profile
                            </p>
                            <h1 className="text-2xl font-bold tracking-tight text-balance sm:text-3xl">
                                Your public profile
                            </h1>
                            <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                                This is how shippers and carriers find you.
                                Contact details stay private until someone
                                reveals them.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">
                                {form.data.account_type === 'business' ? (
                                    <Building2 aria-hidden="true" />
                                ) : (
                                    <UserRound aria-hidden="true" />
                                )}
                                {ACCOUNT_TYPE_LABELS[form.data.account_type] ??
                                    form.data.account_type}
                            </Badge>

                            {form.data.posts_freight && (
                                <Badge variant="secondary">
                                    <Package aria-hidden="true" />
                                    Posts freight
                                </Badge>
                            )}

                            {form.data.provides_transport && (
                                <Badge variant="secondary">
                                    <Truck aria-hidden="true" />
                                    Provides transport
                                </Badge>
                            )}

                            <Badge className={verification.className}>
                                {profile.verification_status === 'verified' ? (
                                    <BadgeCheck aria-hidden="true" />
                                ) : (
                                    <ShieldQuestion aria-hidden="true" />
                                )}
                                {verification.label}
                            </Badge>
                        </div>

                        {profile.provides_transport && (
                            <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <Info
                                    className="mt-0.5 size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                {listingIsPublic
                                    ? 'Your carrier listing is live in the public directory.'
                                    : 'Your carrier listing is not public yet.'}
                            </p>
                        )}
                    </div>

                    <div className="flex shrink-0 flex-col gap-2 sm:items-end">
                        <Button
                            variant="outline"
                            asChild
                            className="w-full sm:w-auto"
                        >
                            <Link href={`/p/${profile.slug}`}>
                                <Eye aria-hidden="true" />
                                Preview public profile
                            </Link>
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            /p/{profile.slug}
                        </span>
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
                    <div className="flex min-w-0 flex-col gap-6">
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <Card id="identity" className="scroll-mt-24">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Identity
                                    </CardTitle>
                                    <CardDescription>
                                        Who you are on the marketplace, and the
                                        channels a reveal may release.
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

                            <Card id="role" className="scroll-mt-24">
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Marketplace role
                                    </CardTitle>
                                    <CardDescription>
                                        Choose at least one. Both can be on, and
                                        you can change this whenever you need
                                        to.
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

                            <div className="sticky bottom-4 z-10 flex flex-col gap-3 rounded-lg border bg-background/95 p-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                                <p
                                    role="status"
                                    aria-live="polite"
                                    className="text-sm"
                                >
                                    {form.processing ? (
                                        <span className="text-muted-foreground">
                                            Saving your changes…
                                        </span>
                                    ) : form.isDirty ? (
                                        <span className="font-medium text-amber-700 dark:text-amber-400">
                                            Unsaved changes
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-1.5 text-muted-foreground">
                                            <CheckCircle2
                                                className="size-4 text-emerald-600 dark:text-emerald-400"
                                                aria-hidden="true"
                                            />
                                            All changes saved
                                        </span>
                                    )}
                                </p>

                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="w-full sm:w-auto"
                                    data-test="profile-save"
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save aria-hidden="true" />
                                    )}
                                    Save changes
                                </Button>
                            </div>
                        </form>

                        {profile.provides_transport && (
                            <TransportFields
                                status={profile.status}
                                rejectionReason={listing_rejection_reason}
                                truckTypes={truckTypes}
                                locations={locations}
                                truckTypeIds={profile.truck_type_ids}
                                routes={profile.routes}
                                documents={profile.documents}
                            />
                        )}
                    </div>

                    <aside className="flex flex-col gap-6 lg:sticky lg:top-20">
                        <ProfileStrength
                            score={
                                profile.provides_transport
                                    ? completionScore
                                    : null
                            }
                            items={strengthItems}
                        />

                        <VerificationCard
                            status={profile.verification_status}
                            note={profile.verification_note}
                            identityReady={identityReady}
                        />
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}
