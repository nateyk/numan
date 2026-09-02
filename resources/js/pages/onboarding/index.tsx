import InputError from '@/components/input-error';
import { AccountTypeCard } from '@/components/onboarding/account-type-card';
import { CapabilityCard } from '@/components/onboarding/capability-card';
import {
    Stepper,
    type OnboardingStepKey,
} from '@/components/onboarding/stepper';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { Building2, Package, Search, Truck, User } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

type AccountType = 'personal' | 'business';

interface OnboardingProfile {
    account_type: AccountType | null;
    display_name: string | null;
    phone: string | null;
    posts_freight: boolean;
    provides_transport: boolean;
    onboarding_step: string | null;
    primary_location_id: number | null;
    legal_name: string | null;
    legal_form: string | null;
}

interface LocationOption {
    id: number;
    name: string;
    type: string;
    region_name: string;
}

interface OnboardingIndexProps {
    profile: OnboardingProfile;
    currentStep: OnboardingStepKey;
    locations: LocationOption[];
}

const LEGAL_FORM_OPTIONS = [
    'Private Limited Company (PLC)',
    'Share Company (SC)',
    'Sole Proprietorship',
    'Partnership',
    'Government Organization',
    'NGO',
    'Association',
    'Cooperative',
    'Other',
] as const;

function stepKeyToNumber(key: OnboardingStepKey): 1 | 2 | 3 {
    if (key === 'capabilities') return 1;
    if (key === 'account_type') return 2;
    return 3;
}

export default function OnboardingIndex({
    profile,
    currentStep,
    locations,
}: OnboardingIndexProps) {
    const initialStep = useMemo(
        () => stepKeyToNumber(currentStep),
        [currentStep],
    );
    const [step, setStep] = useState<1 | 2 | 3>(initialStep);

    // Step 1 — capabilities
    const capabilitiesForm = useForm<{
        posts_freight: boolean;
        provides_transport: boolean;
    }>({
        posts_freight: profile.posts_freight ?? false,
        provides_transport: profile.provides_transport ?? false,
    });

    const capabilitiesHasSelection =
        capabilitiesForm.data.posts_freight ||
        capabilitiesForm.data.provides_transport;

    const submitCapabilities = (e: React.FormEvent): void => {
        e.preventDefault();
        if (!capabilitiesHasSelection) {
            capabilitiesForm.setError('posts_freight', 'Choose at least one.');
            return;
        }
        capabilitiesForm.clearErrors();
        capabilitiesForm.post('/onboarding/capabilities', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Preferences saved');
                setStep(2);
            },
            onError: () =>
                toast.error('Please correct the highlighted fields.'),
        });
    };

    // Step 2 — account type
    const accountTypeForm = useForm<{ account_type: AccountType | '' }>({
        account_type: (profile.account_type as AccountType | null) ?? '',
    });

    const submitAccountType = (e: React.FormEvent): void => {
        e.preventDefault();
        if (!accountTypeForm.data.account_type) {
            accountTypeForm.setError('account_type', 'Choose an account type.');
            return;
        }
        accountTypeForm.clearErrors();
        accountTypeForm.post('/onboarding/account-type', {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Account type saved');
                setStep(3);
            },
            onError: () => toast.error('Please select an account type.'),
        });
    };

    const effectiveAccountType: AccountType | '' =
        accountTypeForm.data.account_type ||
        (profile.account_type as AccountType | null) ||
        '';

    // Step 3 — details
    const isBusiness = effectiveAccountType === 'business';
    const [locationQuery, setLocationQuery] = useState<string>('');
    const [legalFormOther, setLegalFormOther] = useState<string>('');

    const detailsForm = useForm<{
        display_name: string;
        phone: string;
        primary_location_id: string;
        legal_name: string;
        legal_form: string;
        legal_form_other: string;
    }>({
        display_name: profile.display_name ?? '',
        phone: profile.phone ?? '',
        primary_location_id: profile.primary_location_id
            ? String(profile.primary_location_id)
            : '',
        legal_name: profile.legal_name ?? '',
        legal_form: profile.legal_form ?? '',
        legal_form_other: '',
    });

    // Keep legal_form_other synced to detailsForm for submit
    const handleLegalFormChange = (value: string): void => {
        detailsForm.setData('legal_form', value);
        if (value !== 'Other') {
            detailsForm.setData('legal_form_other', '');
            setLegalFormOther('');
        }
    };

    const filteredLocations = useMemo(() => {
        const q = locationQuery.trim().toLowerCase();
        if (!q) return locations.slice(0, 50);
        return locations
            .filter((l) => {
                const hay =
                    `${l.name} ${l.region_name} ${l.type}`.toLowerCase();
                return hay.includes(q);
            })
            .slice(0, 50);
    }, [locations, locationQuery]);

    const submitDetails = (e: React.FormEvent): void => {
        e.preventDefault();
        detailsForm.post('/onboarding/details', {
            preserveScroll: true,
            onSuccess: () =>
                toast.success('Onboarding complete — welcome to NumanFreight!'),
            onError: () => toast.error('Please check the highlighted fields.'),
        });
    };

    const displayNameLabel = isBusiness
        ? 'Contact / display name'
        : 'Display name';

    return (
        <AuthLayout
            title="Welcome to NumanFreight"
            description="Set up your account in three quick steps."
            maxWidth="lg"
        >
            <Head title="Onboarding" />

            <div className="flex flex-col gap-6">
                <Stepper currentStep={step} />

                {step === 1 && (
                    <Card className="border bg-card py-0 shadow-sm">
                        <CardContent className="space-y-5 p-6">
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    How will you use NumanFreight?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Choose at least one. You can change this
                                    later.
                                </p>
                            </div>

                            <form
                                onSubmit={submitCapabilities}
                                className="space-y-4"
                            >
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <CapabilityCard
                                        icon={<Package className="size-4" />}
                                        title="Post Freight"
                                        description="Find transport for cargo you need to move."
                                        selected={
                                            capabilitiesForm.data.posts_freight
                                        }
                                        onToggle={() =>
                                            capabilitiesForm.setData(
                                                'posts_freight',
                                                !capabilitiesForm.data
                                                    .posts_freight,
                                            )
                                        }
                                        testId="capability-post-freight"
                                    />
                                    <CapabilityCard
                                        icon={<Truck className="size-4" />}
                                        title="Find Freight"
                                        description="Provide transport and find available freight."
                                        selected={
                                            capabilitiesForm.data
                                                .provides_transport
                                        }
                                        onToggle={() =>
                                            capabilitiesForm.setData(
                                                'provides_transport',
                                                !capabilitiesForm.data
                                                    .provides_transport,
                                            )
                                        }
                                        testId="capability-find-freight"
                                    />
                                </div>

                                {(capabilitiesForm.errors.posts_freight ||
                                    capabilitiesForm.errors
                                        .provides_transport) && (
                                    <InputError
                                        message={
                                            capabilitiesForm.errors
                                                .posts_freight ??
                                            capabilitiesForm.errors
                                                .provides_transport
                                        }
                                    />
                                )}
                                {!capabilitiesHasSelection &&
                                    !capabilitiesForm.errors.posts_freight && (
                                        <p className="text-xs text-muted-foreground">
                                            Choose at least one to continue.
                                        </p>
                                    )}

                                <Button
                                    type="submit"
                                    className="w-full"
                                    size="lg"
                                    disabled={capabilitiesForm.processing}
                                    data-test="onboarding-capabilities-continue"
                                >
                                    {capabilitiesForm.processing && <Spinner />}
                                    Continue
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 2 && (
                    <Card className="border bg-card py-0 shadow-sm">
                        <CardContent className="space-y-5 p-6">
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    What type of account?
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    This determines the details we collect next.
                                </p>
                            </div>

                            <form
                                onSubmit={submitAccountType}
                                className="space-y-4"
                            >
                                <div
                                    role="radiogroup"
                                    aria-label="Account type"
                                    className="grid gap-3 sm:grid-cols-2"
                                >
                                    <AccountTypeCard
                                        icon={<User className="size-4" />}
                                        title="Personal"
                                        description="For individuals using NumanFreight personally."
                                        selected={
                                            accountTypeForm.data
                                                .account_type === 'personal'
                                        }
                                        onSelect={() =>
                                            accountTypeForm.setData(
                                                'account_type',
                                                'personal',
                                            )
                                        }
                                        testId="account-type-personal"
                                    />
                                    <AccountTypeCard
                                        icon={<Building2 className="size-4" />}
                                        title="Business"
                                        description="For companies and organizations."
                                        selected={
                                            accountTypeForm.data
                                                .account_type === 'business'
                                        }
                                        onSelect={() =>
                                            accountTypeForm.setData(
                                                'account_type',
                                                'business',
                                            )
                                        }
                                        testId="account-type-business"
                                    />
                                </div>
                                <InputError
                                    message={
                                        accountTypeForm.errors.account_type
                                    }
                                />

                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => setStep(1)}
                                        disabled={accountTypeForm.processing}
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="flex-1"
                                        size="lg"
                                        disabled={
                                            accountTypeForm.processing ||
                                            !accountTypeForm.data.account_type
                                        }
                                        data-test="onboarding-account-type-continue"
                                    >
                                        {accountTypeForm.processing && (
                                            <Spinner />
                                        )}
                                        Continue
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {step === 3 && (
                    <Card className="border bg-card py-0 shadow-sm">
                        <CardContent className="space-y-5 p-6">
                            <div className="space-y-1">
                                <h2 className="text-base font-semibold">
                                    Your details
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {isBusiness
                                        ? 'Tell us about your organization and primary location.'
                                        : 'Tell us how you want to appear and where you are based.'}
                                </p>
                            </div>

                            <form
                                onSubmit={submitDetails}
                                className="space-y-4"
                            >
                                {isBusiness && (
                                    <>
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="legal_name">
                                                Legal business name
                                            </Label>
                                            <Input
                                                id="legal_name"
                                                value={
                                                    detailsForm.data.legal_name
                                                }
                                                onChange={(e) =>
                                                    detailsForm.setData(
                                                        'legal_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. ABC Transport PLC"
                                                autoComplete="organization"
                                            />
                                            <InputError
                                                message={
                                                    detailsForm.errors
                                                        .legal_name
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="legal_form">
                                                Business type
                                            </Label>
                                            <Select
                                                value={
                                                    detailsForm.data.legal_form
                                                }
                                                onValueChange={
                                                    handleLegalFormChange
                                                }
                                            >
                                                <SelectTrigger id="legal_form">
                                                    <SelectValue placeholder="Select business type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {LEGAL_FORM_OPTIONS.map(
                                                        (opt) => (
                                                            <SelectItem
                                                                key={opt}
                                                                value={opt}
                                                            >
                                                                {opt}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    detailsForm.errors
                                                        .legal_form
                                                }
                                            />
                                        </div>

                                        {detailsForm.data.legal_form ===
                                            'Other' && (
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="legal_form_other">
                                                    Specify business type
                                                </Label>
                                                <Input
                                                    id="legal_form_other"
                                                    value={legalFormOther}
                                                    onChange={(e) => {
                                                        setLegalFormOther(
                                                            e.target.value,
                                                        );
                                                        detailsForm.setData(
                                                            'legal_form_other',
                                                            e.target.value,
                                                        );
                                                    }}
                                                    placeholder="Specify business type"
                                                />
                                                <InputError
                                                    message={
                                                        detailsForm.errors
                                                            .legal_form_other
                                                    }
                                                />
                                            </div>
                                        )}
                                    </>
                                )}

                                <div className="grid gap-1.5">
                                    <Label htmlFor="display_name">
                                        {displayNameLabel}
                                    </Label>
                                    <Input
                                        id="display_name"
                                        value={detailsForm.data.display_name}
                                        onChange={(e) =>
                                            detailsForm.setData(
                                                'display_name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            isBusiness
                                                ? 'e.g. Abebe — ABC Transport'
                                                : 'e.g. Abebe Kebede'
                                        }
                                        autoComplete="name"
                                    />
                                    <InputError
                                        message={
                                            detailsForm.errors.display_name
                                        }
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={detailsForm.data.phone}
                                        onChange={(e) =>
                                            detailsForm.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+251 91 123 4567"
                                        inputMode="tel"
                                        autoComplete="tel"
                                    />
                                    <InputError
                                        message={detailsForm.errors.phone}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="primary_location_id">
                                        Primary location
                                    </Label>
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="location_search"
                                            value={locationQuery}
                                            onChange={(e) =>
                                                setLocationQuery(e.target.value)
                                            }
                                            placeholder="Search Ethiopian towns…"
                                            className="pl-9"
                                        />
                                    </div>
                                    <Select
                                        value={
                                            detailsForm.data.primary_location_id
                                        }
                                        onValueChange={(v) =>
                                            detailsForm.setData(
                                                'primary_location_id',
                                                v,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="primary_location_id">
                                            <SelectValue placeholder="Select your primary town" />
                                        </SelectTrigger>
                                        <SelectContent className="max-h-64">
                                            {filteredLocations.length === 0 ? (
                                                <div className="px-2 py-6 text-center text-sm text-muted-foreground">
                                                    No towns match “
                                                    {locationQuery}”.
                                                </div>
                                            ) : (
                                                filteredLocations.map((loc) => (
                                                    <SelectItem
                                                        key={loc.id}
                                                        value={String(loc.id)}
                                                    >
                                                        {loc.name}{' '}
                                                        <span className="text-muted-foreground">
                                                            ({loc.type} ·{' '}
                                                            {loc.region_name})
                                                        </span>
                                                    </SelectItem>
                                                ))
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={
                                            detailsForm.errors
                                                .primary_location_id
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Example:{' '}
                                        <span className="font-medium">
                                            Kombolcha (South Wollo · Amhara)
                                        </span>{' '}
                                        — from{' '}
                                        <code className="rounded bg-muted px-1 py-0.5 text-[11px]">
                                            locations
                                        </code>{' '}
                                        prop.
                                    </p>
                                </div>

                                <div className="flex gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1"
                                        onClick={() => setStep(2)}
                                        disabled={detailsForm.processing}
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="flex-1"
                                        size="lg"
                                        disabled={detailsForm.processing}
                                        data-test="onboarding-finish-setup"
                                    >
                                        {detailsForm.processing && <Spinner />}
                                        Finish setup
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <p className="text-center text-xs text-muted-foreground">
                    Step {step} of 3 — your progress is saved automatically.
                </p>
            </div>
        </AuthLayout>
    );
}
