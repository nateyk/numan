import InputError from '@/components/input-error';
import LocationSearchInput from '@/components/maps/location-search-input';
import ContactNumberFields from '@/components/profile/contact-number-fields';
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

export interface IdentityValues {
    display_name: string;
    account_type: string;
    phone: string;
    whatsapp: string;
    phone_numbers: string[];
    whatsapp_numbers: string[];
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
    section?: 'basics' | 'contact';
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

export default function IdentityFields({
    section = 'basics',
    values,
    errors,
    locations,
    accountTypes,
    legalForms,
    onChange,
}: Props) {
    const selectedLocation = locations.find(
        (location) => location.id === values.primary_location_id,
    );
    if (section === 'contact')
        return (
            <div className="space-y-5">
                <p className="rounded-md border bg-muted/30 px-3 py-3 text-sm leading-relaxed text-muted-foreground">
                    Your contact details are private until revealed. Use a country code for each number.
                </p>
                <ContactNumberFields
                    field="phone_numbers"
                    label="Phone"
                    numbers={values.phone_numbers}
                    required
                    errors={errors}
                    onChange={(numbers) => onChange('phone_numbers', numbers)}
                />
                <InputError message={errors.phone} />
                <ContactNumberFields
                    field="whatsapp_numbers"
                    label="WhatsApp"
                    numbers={values.whatsapp_numbers}
                    errors={errors}
                    onChange={(numbers) =>
                        onChange('whatsapp_numbers', numbers)
                    }
                />
                <div className="grid gap-1.5">
                    <Label htmlFor="email">Contact email (optional)</Label>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="email"
                        className="h-11"
                        value={values.email}
                        onChange={(event) =>
                            onChange('email', event.target.value)
                        }
                        aria-invalid={Boolean(errors.email)}
                    />
                    <InputError message={errors.email} />
                </div>
                <fieldset className="space-y-3 border-t pt-4">
                    <legend className="px-1 text-sm font-semibold">
                        Share after a contact reveal
                    </legend>
                    <p className="text-xs leading-relaxed text-muted-foreground">
                        Choose which contact methods people can see after they unlock your details.
                    </p>
                    {(
                        [
                            ['phone_visible', 'Phone numbers'],
                            ['whatsapp_visible', 'WhatsApp numbers'],
                            ['email_visible', 'Email'],
                        ] as const
                    ).map(([key, label]) => (
                        <div
                            key={key}
                            className="flex min-h-12 items-center justify-between gap-4 rounded-md border px-3 py-2"
                        >
                            <Label htmlFor={key}>{label}</Label>
                            <Switch
                                id={key}
                                checked={values[key]}
                                onCheckedChange={(checked) =>
                                    onChange(key, checked)
                                }
                            />
                        </div>
                    ))}
                </fieldset>
            </div>
        );

    return (
        <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="display_name">Display name *</Label>
                    <Input
                        id="display_name"
                        required
                        autoComplete="name"
                        className="h-11"
                        value={values.display_name}
                        onChange={(event) =>
                            onChange('display_name', event.target.value)
                        }
                        aria-invalid={Boolean(errors.display_name)}
                    />
                    <InputError message={errors.display_name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="account_type">Account type</Label>
                    <Select
                        value={values.account_type}
                        onValueChange={(value) =>
                            onChange('account_type', value)
                        }
                    >
                        <SelectTrigger id="account_type" className="h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {accountTypes.map((type) => (
                                <SelectItem key={type} value={type}>
                                    {type === 'business'
                                        ? 'Business'
                                        : 'Individual'}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.account_type} />
                </div>
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="primary_location_id">Base location</Label>
                <LocationSearchInput
                    id="primary_location_id"
                    initialName={selectedLocation?.name}
                    onChange={(id) =>
                        onChange('primary_location_id', id || null)
                    }
                    onSelectLocation={(location) => {
                        if (!location) onChange('primary_location_id', null);
                    }}
                    localOnly
                    type="city"
                    className="w-full [&_input]:h-11"
                    placeholder="Search city or town"
                />
                <InputError message={errors.primary_location_id} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor="business_description">About (optional)</Label>
                <Textarea
                    id="business_description"
                    rows={3}
                    className="min-h-24 resize-y"
                    value={values.business_description}
                    onChange={(event) =>
                        onChange('business_description', event.target.value)
                    }
                    placeholder="What you ship or carry. Leave contact details out."
                    aria-invalid={Boolean(errors.business_description)}
                />
                <InputError message={errors.business_description} />
            </div>
            {values.account_type === 'business' && (
                <fieldset className="space-y-4 border-t pt-5">
                    <legend className="px-1 text-sm font-semibold">
                        Business details
                    </legend>
                    <p className="text-xs text-muted-foreground">
                        Optional for setup. Complete these to request
                        verification.
                    </p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="legal_name">Legal name</Label>
                            <Input
                                id="legal_name"
                                autoComplete="organization"
                                className="h-11"
                                value={values.legal_name}
                                onChange={(e) =>
                                    onChange('legal_name', e.target.value)
                                }
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
                                <SelectTrigger id="legal_form" className="h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Not specified
                                    </SelectItem>
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
                    {values.legal_form === 'Other' && (
                        <div className="grid gap-1.5">
                            <Label htmlFor="legal_form_other">
                                Other legal form
                            </Label>
                            <Input
                                id="legal_form_other"
                                className="h-11"
                                value={values.legal_form_other}
                                onChange={(e) =>
                                    onChange('legal_form_other', e.target.value)
                                }
                            />
                            <InputError message={errors.legal_form_other} />
                        </div>
                    )}
                </fieldset>
            )}
        </div>
    );
}
