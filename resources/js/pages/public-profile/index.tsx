import CapabilityFields from '@/components/profile/capability-fields';
import IdentityFields, {
    type IdentityValues,
} from '@/components/profile/identity-fields';
import TransportFields from '@/components/profile/transport-fields';
import VerificationCard, {
    VERIFICATION_STATUS,
} from '@/components/profile/verification-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Spinner } from '@/components/ui/spinner';
import { useToast } from '@/components/ui/toast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';
import { Head, Link, useForm, usePage, useRemember } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Eye } from 'lucide-react';
import { useEffect, useRef } from 'react';

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
    documents: {
        id: number;
        type: string;
        name: string;
        status: string;
        uploaded_at: string | null;
    }[];
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
type Step = 'basics' | 'contact' | 'carrier' | 'review';

export default function PublicProfileIndex({
    profile,
    locations,
    truckTypes,
    accountTypes,
    legalForms,
    listing_rejection_reason,
}: Props) {
    const { flash } = usePage<SharedData>().props;
    const { addToast } = useToast();
    const [step, setStep] = useRemember<Step>(
        'basics',
        `profile-step-${profile.id}`,
    );
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        if (flash?.success) addToast(flash.success, 'success');
        if (flash?.error) addToast(flash.error, 'error');
    }, [flash?.success, flash?.error, addToast]);

    const form = useForm<
        IdentityValues & { posts_freight: boolean; provides_transport: boolean }
    >({
        display_name: profile.display_name ?? '',
        account_type: profile.account_type ?? 'personal',
        phone: profile.phone ?? '',
        whatsapp: profile.whatsapp ?? '',
        phone_numbers: profile.phone_numbers?.length
            ? profile.phone_numbers
            : [profile.phone ?? ''],
        whatsapp_numbers:
            profile.whatsapp_numbers ??
            (profile.whatsapp ? [profile.whatsapp] : []),
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
    const steps: { id: Step; label: string }[] = [
        { id: 'basics', label: 'Basics' },
        { id: 'contact', label: 'Contact' },
        ...(form.data.provides_transport
            ? [{ id: 'carrier' as const, label: 'Carrier details' }]
            : []),
        { id: 'review', label: 'Review' },
    ];
    const currentStep =
        step === 'carrier' && !profile.provides_transport ? 'contact' : step;
    const index = steps.findIndex((item) => item.id === currentStep);
    const goTo = (next: Step) => {
        setStep(next);
        requestAnimationFrame(() => {
            heading.current?.focus();
            heading.current?.scrollIntoView({
                block: 'start',
                behavior: 'instant',
            });
        });
    };
    const updateIdentity = <K extends keyof IdentityValues>(
        key: K,
        value: IdentityValues[K],
    ) => {
        form.setData({
            ...form.data,
            [key]: value,
            ...(key === 'phone_numbers'
                ? { phone: (value as string[])[0] ?? '' }
                : {}),
            ...(key === 'whatsapp_numbers'
                ? { whatsapp: (value as string[])[0] ?? '' }
                : {}),
        });
    };
    const save = (advance: boolean) => {
        form.patch('/public-profile', {
            preserveScroll: true,
            onSuccess: (page) => {
                form.setDefaults();
                if (advance) {
                    const saved = page.props.profile as ProfilePayload;
                    goTo(saved.provides_transport ? 'carrier' : 'review');
                }
            },
            onError: (errors) => {
                const contactError = Object.keys(errors).some((key) =>
                    /^(phone|whatsapp|email)/.test(key),
                );
                goTo(contactError ? 'contact' : 'basics');
            },
        });
    };
    const verification =
        VERIFICATION_STATUS[profile.verification_status ?? 'unverified'] ??
        VERIFICATION_STATUS.unverified;
    const business = profile.account_type === 'business';
    const hasBusinessProof = profile.documents.some(
        (document) =>
            document.type === 'business_registration' &&
            document.status !== 'rejected',
    );
    const identityReady = Boolean(
        profile.display_name?.trim() &&
        profile.phone?.trim() &&
        (!business ||
            (profile.legal_name?.trim() &&
                profile.legal_form &&
                hasBusinessProof)),
    );
    const transportProps = {
        status: profile.status,
        rejectionReason: listing_rejection_reason,
        truckTypes,
        locations,
        truckTypeIds: profile.truck_type_ids,
        routes: profile.routes,
        documents: profile.documents,
    };

    return (
        <AppLayout
            breadcrumbs={[{ title: 'My Profile', href: '/public-profile' }]}
            contentClassName="overflow-x-clip"
        >
            <Head title="My profile" />
            <div className="mx-auto w-full max-w-3xl space-y-6 px-4 py-6 sm:space-y-7 sm:px-6 sm:py-8">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            My profile
                        </h1>
                        <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                            Your details, contact, and marketplace role.
                        </p>
                    </div>
                    <Button variant="outline" className="min-h-11" asChild>
                        <Link href={`/p/${profile.slug}`}>
                            <Eye className="size-4" />
                            Preview
                        </Link>
                    </Button>
                </header>
                <nav
                    aria-label="Profile setup"
                    className="hidden auto-cols-fr grid-flow-col gap-1 rounded-lg border bg-muted/30 p-1 sm:grid"
                >
                    {steps.map((item, i) => (
                        <Button
                            key={item.id}
                            type="button"
                            variant="ghost"
                            className={cn(
                                'min-h-12 gap-2 px-2 text-sm font-medium',
                                currentStep === item.id &&
                                    'border border-border bg-background shadow-xs hover:bg-background',
                            )}
                            aria-current={currentStep === item.id ? 'step' : undefined}
                            disabled={
                                form.processing ||
                                (i > index && form.isDirty) ||
                                (item.id === 'carrier' &&
                                    !profile.provides_transport)
                            }
                            onClick={() => goTo(item.id)}
                        >
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    currentStep === item.id
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground',
                                )}
                                aria-hidden="true"
                            >
                                {i + 1}
                            </span>
                            <span className="truncate">{item.label}</span>
                        </Button>
                    ))}
                </nav>

                <div
                    className="space-y-2 sm:hidden"
                    role="progressbar"
                    aria-label="Profile setup progress"
                    aria-valuemin={1}
                    aria-valuemax={steps.length}
                    aria-valuenow={index + 1}
                    aria-valuetext={`Step ${index + 1} of ${steps.length}: ${steps[index]?.label}`}
                >
                    <p className="text-xs font-medium text-muted-foreground">
                        Step {index + 1} of {steps.length}
                    </p>
                    <div className="grid grid-flow-col gap-1" aria-hidden="true">
                        {steps.map((item, i) => (
                            <span
                                key={item.id}
                                className={cn(
                                    'h-1 rounded-full',
                                    i <= index ? 'bg-primary' : 'bg-border',
                                )}
                            />
                        ))}
                    </div>
                </div>

                <div className="space-y-1">
                    <h2
                        ref={heading}
                        tabIndex={-1}
                        className="scroll-mt-24 text-xl font-semibold tracking-tight outline-none"
                    >
                        {steps[index]?.label}
                    </h2>
                    <p className="hidden text-sm text-muted-foreground sm:block">
                        Step {index + 1} of {steps.length}
                    </p>
                </div>

                {(currentStep === 'basics' || currentStep === 'contact') && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (currentStep === 'basics') goTo('contact');
                            else save(true);
                        }}
                        className="space-y-4"
                    >
                        <Card className="gap-0 py-0 shadow-none">
                            <CardContent className="space-y-6 p-4 sm:p-6">
                                <IdentityFields
                                    section={currentStep}
                                    values={form.data}
                                    errors={form.errors}
                                    locations={locations}
                                    accountTypes={accountTypes}
                                    legalForms={legalForms}
                                    onChange={updateIdentity}
                                />
                                {currentStep === 'basics' && (
                                    <fieldset className="space-y-3 border-t pt-5">
                                        <legend className="px-1 text-sm font-semibold">
                                            What do you do?
                                        </legend>
                                        <CapabilityFields
                                            values={form.data}
                                            errors={form.errors}
                                            onChange={(key, value) =>
                                                form.setData(key, value)
                                            }
                                        />
                                    </fieldset>
                                )}
                            </CardContent>
                        </Card>
                        <footer className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-h-11 items-center gap-3">
                                {currentStep === 'contact' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="min-h-11"
                                        disabled={form.processing}
                                        onClick={() => goTo('basics')}
                                    >
                                        <ArrowLeft className="size-4" aria-hidden="true" />
                                        Back
                                    </Button>
                                )}
                                {form.isDirty && (
                                    <span role="status" className="text-xs text-muted-foreground">
                                        Unsaved changes
                                    </span>
                                )}
                            </div>
                            <div className="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row">
                                {currentStep === 'contact' && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="min-h-11 w-full sm:w-auto"
                                        disabled={form.processing}
                                        onClick={() => save(false)}
                                    >
                                        Save
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    className="min-h-11 w-full sm:w-auto"
                                    disabled={form.processing}
                                    data-test="profile-save"
                                >
                                    {form.processing ? <Spinner /> : null}
                                    {currentStep === 'basics'
                                        ? 'Continue'
                                        : 'Save & continue'}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </footer>
                    </form>
                )}

                <div hidden={currentStep !== 'carrier'}>
                    {profile.provides_transport && (
                        <TransportFields
                            {...transportProps}
                            section="carrier"
                            onSaved={() => goTo('review')}
                            onBack={() => goTo('contact')}
                        />
                    )}
                </div>
                <div hidden={currentStep !== 'review'} className="space-y-5">
                    <Card className="gap-0 py-0 shadow-none">
                        <CardHeader className="flex-row items-center justify-between gap-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                Profile summary
                            </CardTitle>
                            <Badge className={verification.className}>
                                {verification.label}
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd className="mt-1 font-medium">
                                        {profile.display_name}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Role
                                    </dt>
                                    <dd className="mt-1">
                                        {[
                                            profile.posts_freight &&
                                                'Posts freight',
                                            profile.provides_transport &&
                                                'Provides transport',
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || 'Not selected'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd className="mt-1">
                                        {profile.phone_numbers?.join(', ') ||
                                            profile.phone ||
                                            'Add a phone number'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Carrier listing
                                    </dt>
                                    <dd className="mt-1 capitalize">
                                        {profile.provides_transport
                                            ? (profile.status ?? 'Draft')
                                            : 'Not enabled'}
                                    </dd>
                                </div>
                            </dl>
                            <Button
                                className="mt-5 min-h-11"
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => goTo('basics')}
                            >
                                Edit details
                            </Button>
                        </CardContent>
                    </Card>
                    <Collapsible className="space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4 sm:p-5">
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    {business
                                        ? 'Business verification'
                                        : 'Identity verification'}{' '}
                                    <span className="font-normal text-muted-foreground">
                                        · Optional
                                    </span>
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {business
                                        ? 'Upload registration proof for an admin to review.'
                                        : 'Request a review for your verified badge.'}
                                </p>
                            </div>
                            <CollapsibleTrigger asChild>
                                <Button variant="outline" className="min-h-11">
                                    {profile.verification_status ===
                                        'pending' ||
                                    profile.verification_status === 'verified'
                                        ? 'View verification'
                                        : business
                                          ? 'Verify business'
                                          : 'Verify identity'}
                                </Button>
                            </CollapsibleTrigger>
                        </div>
                        <CollapsibleContent className="space-y-4">
                            <TransportFields
                                {...transportProps}
                                key={business ? 'business' : 'personal'}
                                section="documents"
                                defaultDocumentType={
                                    business ? 'business_registration' : 'id'
                                }
                            />
                            <VerificationCard
                                status={profile.verification_status}
                                note={profile.verification_note}
                                identityReady={identityReady}
                            />
                        </CollapsibleContent>
                    </Collapsible>
                    <footer className="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            className="min-h-11"
                            onClick={() =>
                                goTo(profile.provides_transport ? 'carrier' : 'contact')
                            }
                        >
                            <ArrowLeft className="size-4" aria-hidden="true" />
                            Back
                        </Button>
                        <Button variant="outline" className="min-h-11" asChild>
                            <Link href={`/p/${profile.slug}`}>
                                <Eye className="size-4" aria-hidden="true" />
                                View public profile
                            </Link>
                        </Button>
                    </footer>
                </div>
            </div>
        </AppLayout>
    );
}
