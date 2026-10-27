import InputError from '@/components/input-error';
import LocationSearchInput from '@/components/maps/location-search-input';
import { AccountTypeCard } from '@/components/onboarding/account-type-card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Building2, UserRound } from 'lucide-react';

export interface IdentityValues {
    display_name: string;
    account_type: string;
    phone: string;
    whatsapp: string;
    email: string;
    business_description: string;
    primary_location_id: number | null;
    legal_name: string;
    legal_form: string;
    legal_form_other: string;
    phone_visible: boolean;
    whatsapp_visible: boolean;
    email_visible: boolean;
}

interface Props {
    values: IdentityValues;
    errors: Partial<Record<string, string>>;
    locations: {
        id: number;
        name: string;
        type: string;
        region_name: string | null;
    }[];
    accountTypes: string[];
    legalForms: string[];
    onChange: <K extends keyof IdentityValues>(
        key: K,
        value: IdentityValues[K],
    ) => void;
}

const ACCOUNT_TYPE_COPY: Record<
    string,
    { title: string; description: string }
> = {
    personal: {
        title: 'Individual',
        description: 'For a person posting or carrying freight themselves.',
    },
    business: {
        title: 'Business',
        description: 'For a company or organisation that trades on its behalf.',
    },
};

const VISIBILITY_FIELDS: {
    key: 'phone_visible' | 'whatsapp_visible' | 'email_visible';
    label: string;
    description: string;
}[] = [
    {
        key: 'phone_visible',
        label: 'Share my phone number',
        description:
            'Released only to somebody who reveals your contact, never printed on your public page.',
    },
    {
        key: 'whatsapp_visible',
        label: 'Share my WhatsApp number',
        description: 'Adds WhatsApp to the contact a reveal releases.',
    },
    {
        key: 'email_visible',
        label: 'Share my email address',
        description: 'Adds email to the contact a reveal releases.',
    },
];

export default function IdentityFields({
    values,
    errors,
    locations,
    accountTypes,
    legalForms,
    onChange,
}: Props) {
    const selectedLocation = values.primary_location_id
        ? locations.find(
              (location) => location.id === values.primary_location_id,
          )
        : undefined;

    const isBusiness = values.account_type === 'business';

    return (
        <div className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="display_name">Display name</Label>
                    <Input
                        id="display_name"
                        value={values.display_name}
                        onChange={(event) =>
                            onChange('display_name', event.target.value)
                        }
                        aria-invalid={Boolean(errors.display_name)}
                    />
                    <p className="text-xs text-muted-foreground">
                        The name people see on your posts and profile.
                    </p>
                    <InputError message={errors.display_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="account_type">Account type</Label>
                    <div
                        id="account_type"
                        className="grid gap-2 sm:grid-cols-2"
                        role="radiogroup"
                        aria-label="Account type"
                    >
                        {accountTypes.map((type) => {
                            const copy = ACCOUNT_TYPE_COPY[type] ?? {
                                title: type,
                                description: '',
                            };

                            return (
                                <AccountTypeCard
                                    key={type}
                                    icon={
                                        type === 'business' ? (
                                            <Building2 className="size-4" />
                                        ) : (
                                            <UserRound className="size-4" />
                                        )
                                    }
                                    title={copy.title}
                                    description={copy.description}
                                    selected={values.account_type === type}
                                    onSelect={() =>
                                        onChange('account_type', type)
                                    }
                                />
                            );
                        })}
                    </div>
                    <InputError message={errors.account_type} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="phone">Phone number</Label>
                    <Input
                        id="phone"
                        inputMode="tel"
                        value={values.phone}
                        onChange={(event) =>
                            onChange('phone', event.target.value)
                        }
                        placeholder="+251 91 123 4567"
                        aria-invalid={Boolean(errors.phone)}
                    />
                    <InputError message={errors.phone} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="whatsapp">WhatsApp number</Label>
                    <Input
                        id="whatsapp"
                        inputMode="tel"
                        value={values.whatsapp}
                        onChange={(event) =>
                            onChange('whatsapp', event.target.value)
                        }
                        placeholder="Optional"
                        aria-invalid={Boolean(errors.whatsapp)}
                    />
                    <InputError message={errors.whatsapp} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="email">Contact email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={values.email}
                        onChange={(event) =>
                            onChange('email', event.target.value)
                        }
                        aria-invalid={Boolean(errors.email)}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="primary_location_id">Base location</Label>
                    <LocationSearchInput
                        id="primary_location_id"
                        initialName={selectedLocation?.name}
                        onChange={(id) =>
                            onChange(
                                'primary_location_id',
                                id === 0 ? null : id,
                            )
                        }
                        onSelectLocation={(location) => {
                            if (location === null) {
                                onChange('primary_location_id', null);
                            }
                        }}
                        localOnly
                        type="city"
                        placeholder="Search your city or town"
                    />
                    <p className="text-xs text-muted-foreground">
                        Where you usually start from. Carriers need one before
                        they can express interest.
                    </p>
                    <InputError message={errors.primary_location_id} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="legal_name">
                        Legal name{' '}
                        <span className="text-xs font-normal text-muted-foreground">
                            (business)
                        </span>
                    </Label>
                    <Input
                        id="legal_name"
                        value={values.legal_name}
                        onChange={(event) =>
                            onChange('legal_name', event.target.value)
                        }
                        aria-invalid={Boolean(errors.legal_name)}
                    />
                    <InputError message={errors.legal_name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="legal_form">Legal form</Label>
                    <Select
                        value={values.legal_form || 'none'}
                        onValueChange={(value) =>
                            onChange(
                                'legal_form',
                                value === 'none' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger id="legal_form">
                            <SelectValue placeholder="Not specified" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">Not specified</SelectItem>
                            {legalForms.map((form) => (
                                <SelectItem key={form} value={form}>
                                    {form}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.legal_form} />
                </div>
            </div>

            {isBusiness && values.legal_form === 'Other' && (
                <div className="grid gap-1.5">
                    <Label htmlFor="legal_form_other">
                        Describe the legal form
                    </Label>
                    <Input
                        id="legal_form_other"
                        value={values.legal_form_other}
                        onChange={(event) =>
                            onChange('legal_form_other', event.target.value)
                        }
                        aria-invalid={Boolean(errors.legal_form_other)}
                    />
                    <InputError message={errors.legal_form_other} />
                </div>
            )}

            <div className="grid gap-1.5">
                <Label htmlFor="business_description">About</Label>
                <Textarea
                    id="business_description"
                    rows={4}
                    value={values.business_description}
                    onChange={(event) =>
                        onChange('business_description', event.target.value)
                    }
                    placeholder="What you carry, where you run, or what you usually ship."
                    aria-invalid={Boolean(errors.business_description)}
                />
                <p className="text-xs text-muted-foreground">
                    Shown on your public page. Do not include contact details;
                    those are released through a reveal.
                </p>
                <InputError message={errors.business_description} />
            </div>

            <div className="space-y-3 rounded-lg border bg-muted/30 p-4">
                <div>
                    <h3 className="text-sm font-semibold">
                        Who can see your contact details
                    </h3>
                    <p className="mt-1 text-xs text-muted-foreground">
                        These switches decide which channels a reveal may
                        release. Turning one off withholds that channel even
                        from somebody already allowed to reveal you.
                    </p>
                </div>

                {VISIBILITY_FIELDS.map((field) => (
                    <div
                        key={field.key}
                        className="flex items-start justify-between gap-4"
                    >
                        <div>
                            <p className="text-sm font-medium">{field.label}</p>
                            <p className="text-xs text-muted-foreground">
                                {field.description}
                            </p>
                        </div>
                        <Switch
                            checked={values[field.key]}
                            onCheckedChange={(checked) =>
                                onChange(field.key, checked)
                            }
                            aria-label={field.label}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
}
