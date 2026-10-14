import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import AppLayout from '@/layouts/app-layout';
import ProfileLayout from '@/layouts/settings/profile-layout';
import { Building2, MapPin, Truck } from 'lucide-react';

interface Details {
    display_name: string | null;
    phone: string | null;
    primary_location_id: number | null;
    phone_visible: boolean;
    whatsapp_visible: boolean;
    email_visible: boolean;
}

interface Props {
    profile: Details;
    capabilities: {
        posts_freight: boolean;
        provides_transport: boolean;
    };
    missingFields: Record<string, string[]>;
    ready: Record<string, boolean>;
    locations: { id: number; label: string }[];
}

const NO_SELECTION = 'none';

export default function MarketplaceDetailsPage({
    profile,
    capabilities,
    missingFields,
    ready,
    locations,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing, errors, wasSuccessful } = useForm(
        {
            display_name: profile.display_name ?? '',
            phone: profile.phone ?? '',
            primary_location_id: profile.primary_location_id
                ? String(profile.primary_location_id)
                : NO_SELECTION,
            phone_visible: profile.phone_visible,
            whatsapp_visible: profile.whatsapp_visible,
            email_visible: profile.email_visible,
        },
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.settings', 'Settings'), href: '/settings/profile' },
        {
            title: t('marketplace_details.title', 'Marketplace details'),
            href: '/settings/marketplace-details',
        },
    ];

    const actsInMarketplace =
        capabilities.posts_freight || capabilities.provides_transport;

    // Both are always sent, so turning one off leaves the other alone. Neither is
    // a meaningful state, so the server rejects it and the user picks at least one.
    const capabilityForm = useForm({
        posts_freight: capabilities.posts_freight,
        provides_transport: capabilities.provides_transport,
    });

    // Straight from MarketplaceReadiness. Recomputing it here is how the page and
    // the gate end up disagreeing about whether somebody is ready.
    const postReady = ready.posts_freight;
    const transportReady = ready.provides_transport;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={t('marketplace_details.title', 'Marketplace details')}
            />

            <ProfileLayout
                title={t('marketplace_details.title', 'Marketplace details')}
                description={t(
                    'marketplace_details.description',
                    'How other people see and reach you on Numan.',
                )}
            >
                <div className="space-y-6">
                    {!actsInMarketplace && (
                        <Alert>
                            <AlertDescription>
                                {t(
                                    'marketplace_details.no_capability_hint',
                                    'You have not chosen what you will do here yet, so nothing on this page is required. Set that first if you want to post freight or offer to carry it.',
                                )}{' '}
                                <Link
                                    href="/onboarding"
                                    className="underline underline-offset-4"
                                >
                                    {t(
                                        'marketplace_details.choose_capability',
                                        'Choose what you will do',
                                    )}
                                </Link>
                            </AlertDescription>
                        </Alert>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t(
                                    'marketplace_details.identity',
                                    'Your details',
                                )}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'marketplace_details.identity_description',
                                    'This is what someone sees on your posts and messages.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-1.5">
                                <Label htmlFor="display_name">
                                    {t(
                                        'marketplace_details.display_name',
                                        'Display name',
                                    )}
                                </Label>
                                <Input
                                    id="display_name"
                                    value={data.display_name}
                                    onChange={(e) =>
                                        setData('display_name', e.target.value)
                                    }
                                    aria-invalid={!!errors.display_name}
                                    aria-describedby={
                                        errors.display_name
                                            ? 'display_name-error'
                                            : undefined
                                    }
                                />
                                {errors.display_name && (
                                    <p
                                        id="display_name-error"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.display_name}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="phone">
                                    {t(
                                        'marketplace_details.phone',
                                        'Phone number',
                                    )}
                                </Label>
                                <Input
                                    id="phone"
                                    inputMode="tel"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                    aria-invalid={!!errors.phone}
                                    aria-describedby={
                                        errors.phone ? 'phone-error' : undefined
                                    }
                                />
                                {errors.phone && (
                                    <p
                                        id="phone-error"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.phone}
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="primary_location_id">
                                    {t(
                                        'marketplace_details.location',
                                        'Base location',
                                    )}
                                </Label>
                                <Select
                                    value={data.primary_location_id}
                                    onValueChange={(value) =>
                                        setData('primary_location_id', value)
                                    }
                                >
                                    <SelectTrigger id="primary_location_id">
                                        <SelectValue
                                            placeholder={t(
                                                'marketplace_details.location_placeholder',
                                                'Pick a location',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NO_SELECTION}>
                                            {t(
                                                'marketplace_details.location_none',
                                                'Not set',
                                            )}
                                        </SelectItem>
                                        {locations.map((location) => (
                                            <SelectItem
                                                key={location.id}
                                                value={String(location.id)}
                                            >
                                                {location.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.primary_location_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.primary_location_id}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t(
                                    'marketplace_details.visibility',
                                    'Who can see your contact details',
                                )}
                            </CardTitle>
                            <CardDescription>
                                {t(
                                    'marketplace_details.visibility_description',
                                    'Your details are only released to someone you have a live conversation with, and every release is recorded. These switches decide whether the number goes out when that happens.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {[
                                {
                                    key: 'phone_visible' as const,
                                    label: t(
                                        'marketplace_details.share_phone',
                                        'Share my phone number',
                                    ),
                                },
                                {
                                    key: 'whatsapp_visible' as const,
                                    label: t(
                                        'marketplace_details.share_whatsapp',
                                        'Share my WhatsApp number',
                                    ),
                                },
                                {
                                    key: 'email_visible' as const,
                                    label: t(
                                        'marketplace_details.share_email',
                                        'Share my email address',
                                    ),
                                },
                            ].map((field) => (
                                <label
                                    key={field.key}
                                    className="flex items-center gap-3"
                                >
                                    <input
                                        type="checkbox"
                                        checked={data[field.key]}
                                        onChange={(e) =>
                                            setData(field.key, e.target.checked)
                                        }
                                        className="size-4 rounded border-input"
                                    />
                                    <span className="text-sm">
                                        {field.label}
                                    </span>
                                </label>
                            ))}
                        </CardContent>
                    </Card>

                    {actsInMarketplace && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t(
                                        'marketplace_details.readiness',
                                        'What is left to do',
                                    )}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'marketplace_details.readiness_description',
                                        'These are the only things standing between you and trading.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-start gap-3">
                                    {postReady ? (
                                        <Badge variant="secondary">
                                            <Building2 className="size-3" />
                                            {t(
                                                'marketplace_details.posting_ready',
                                                'Ready to post freight',
                                            )}
                                        </Badge>
                                    ) : (
                                        <Badge variant="destructive">
                                            <Building2 className="size-3" />
                                            {t(
                                                'marketplace_details.posting_blocked',
                                                'Posting freight needs',
                                            )}
                                            {': '}
                                            {(
                                                missingFields.posts_freight ??
                                                []
                                            ).join(', ')}
                                        </Badge>
                                    )}
                                </div>

                                <div className="flex items-start gap-3">
                                    {transportReady ? (
                                        <Badge variant="secondary">
                                            <Truck className="size-3" />
                                            {t(
                                                'marketplace_details.transport_ready',
                                                'Ready to carry freight',
                                            )}
                                        </Badge>
                                    ) : (
                                        <Badge variant="destructive">
                                            <Truck className="size-3" />
                                            {t(
                                                'marketplace_details.transport_blocked',
                                                'Carrying freight needs',
                                            )}
                                            {': '}
                                            {(
                                                missingFields.provides_transport ??
                                                []
                                            ).join(', ')}
                                        </Badge>
                                    )}
                                </div>

                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <MapPin className="size-4" />
                                    {t(
                                        'marketplace_details.carrier_form_hint',
                                        'Trucks, routes and documents live on your carrier profile.',
                                    )}{' '}
                                    <Link
                                        href="/my-transporter-profile"
                                        className="underline underline-offset-4"
                                    >
                                        {t(
                                            'marketplace_details.open_carrier_profile',
                                            'Open carrier profile',
                                        )}
                                    </Link>
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={processing}
                            onClick={() =>
                                patch('/settings/marketplace-details')
                            }
                        >
                            {t('marketplace_details.save', 'Save')}
                        </Button>
                        {wasSuccessful && (
                            <p className="text-sm text-muted-foreground">
                                {t('marketplace_details.saved', 'Saved.')}
                            </p>
                        )}
                    </div>
                </div>
            </ProfileLayout>
        </AppLayout>
    );
}
