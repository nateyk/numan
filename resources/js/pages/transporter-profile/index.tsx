import { useForm } from '@inertiajs/react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import CompletionScore from '@/components/transporter/completion-score';
import LocationSearchInput from '@/components/maps/location-search-input';
import {
    Check,
    ChevronRight,
    MapPin,
    Package,
    Truck,
    Upload,
    User,
    Wallet,
} from 'lucide-react';
import { Fragment } from 'react';
import { useState } from 'react';

interface ProfileData {
    id?: number;
    display_name: string;
    legal_name: string;
    is_company: boolean;
    business_description: string;
    phone: string;
    whatsapp: string;
    email: string;
    phone_visible: boolean;
    whatsapp_visible: boolean;
    email_visible: boolean;
    primary_location_id: number | null;
    truck_type_ids: number[];
    routes: { origin_location_id: number; destination_location_id: number }[];
}

interface Props {
    profile: ProfileData | null;
    truckTypes: { id: number; name: string; slug: string; icon: string | null }[];
    cargoTypes: { id: number; name: string; slug: string }[];
    locations: { id: number; name: string; region_name: string; type: string }[];
    completionScore: number;
}

const STEPS = ['Basic Info', 'Fleet & Routes', 'Documents'];

export default function TransporterProfilePage({ profile, truckTypes, cargoTypes, locations, completionScore }: Props) {
    const [step, setStep] = useState(0);

    const form = useForm<ProfileData>({
        display_name: profile?.display_name ?? '',
        legal_name: profile?.legal_name ?? '',
        is_company: profile?.is_company ?? false,
        business_description: profile?.business_description ?? '',
        phone: profile?.phone ?? '',
        whatsapp: profile?.whatsapp ?? '',
        email: profile?.email ?? '',
        phone_visible: profile?.phone_visible ?? false,
        whatsapp_visible: profile?.whatsapp_visible ?? false,
        email_visible: profile?.email_visible ?? false,
        primary_location_id: profile?.primary_location_id ?? null,
        truck_type_ids: profile?.truck_type_ids ?? [],
        routes: profile?.routes ?? [{ origin_location_id: 0, destination_location_id: 0 }],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (step < STEPS.length - 1) {
            setStep(step + 1);
            return;
        }
        if (profile) {
            form.put('/my-transporter-profile', {
                onSuccess: () => router.visit('/my-transporter-profile', { preserveScroll: true }),
            });
        } else {
            form.post('/my-transporter-profile', {
                onSuccess: () => router.visit('/my-transporter-profile', { preserveScroll: true }),
            });
        }
    };

    const toggleTruckType = (id: number) => {
        const current = form.data.truck_type_ids;
        const next = current.includes(id) ? current.filter((t: number) => t !== id) : [...current, id];
        form.setData('truck_type_ids', next);
    };

    const addRoute = () => {
        form.setData('routes', [...form.data.routes, { origin_location_id: 0, destination_location_id: 0 }]);
    };

    const removeRoute = (idx: number) => {
        form.setData('routes', form.data.routes.filter((_: unknown, i: number) => i !== idx));
    };

    const updateRoute = (idx: number, field: string, value: number) => {
        const routes = [...form.data.routes];
        routes[idx] = { ...routes[idx], [field]: value };
        form.setData('routes', routes);
    };

    return (
        <AdminLayout>
            <Head title="My Transporter Profile" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">Transporter Profile</h2>
                        <p className="text-sm text-muted-foreground">
                            {profile ? 'Manage your transporter profile' : 'Set up your transporter profile'}
                        </p>
                    </div>
                    <CompletionScore score={completionScore} />
                </div>

                <div className="flex items-center gap-2">
                    {STEPS.map((s, i) => (
                        <Fragment key={s}>
                            <button
                                type="button"
                                onClick={() => setStep(i)}
                                className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                                    i === step
                                        ? 'bg-primary text-primary-foreground'
                                        : i < step
                                          ? 'bg-muted text-muted-foreground hover:bg-muted'
                                          : 'bg-card text-muted-foreground hover:bg-muted'
                                }`}
                            >
                                {i < step ? <Check className="size-3.5" /> : <span className="size-3.5" />}
                                {s}
                            </button>
                            {i < STEPS.length - 1 && (
                                <ChevronRight className="size-4 text-muted-foreground" />
                            )}
                        </Fragment>
                    ))}
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {step === 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="size-5" />
                                    Basic Information
                                </CardTitle>
                                <CardDescription>Your public-facing business details</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label>Display Name *</Label>
                                        <Input
                                            value={form.data.display_name}
                                            onChange={(e) => form.setData('display_name', e.target.value)}
                                            placeholder="e.g. Nairobi Freight Solutions"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label>Legal Name</Label>
                                        <Input
                                            value={form.data.legal_name}
                                            onChange={(e) => form.setData('legal_name', e.target.value)}
                                            placeholder="Legal business name"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label>Business Description</Label>
                                    <textarea
                                        value={form.data.business_description}
                                        onChange={(e) => form.setData('business_description', e.target.value)}
                                        placeholder="Describe your business..."
                                        rows={3}
                                        className="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label>Phone</Label>
                                        <Input
                                            value={form.data.phone}
                                            onChange={(e) => form.setData('phone', e.target.value)}
                                            placeholder="+254..."
                                        />
                                    </div>
                                    <div>
                                        <Label>WhatsApp</Label>
                                        <Input
                                            value={form.data.whatsapp}
                                            onChange={(e) => form.setData('whatsapp', e.target.value)}
                                            placeholder="+254..."
                                        />
                                    </div>
                                </div>
                                <div>
                                    <Label>Email</Label>
                                    <Input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) => form.setData('email', e.target.value)}
                                        placeholder="email@example.com"
                                    />
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.phone_visible}
                                            onChange={(e) => form.setData('phone_visible', e.target.checked)}
                                            className="rounded"
                                        />
                                        Show phone publicly
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.whatsapp_visible}
                                            onChange={(e) => form.setData('whatsapp_visible', e.target.checked)}
                                            className="rounded"
                                        />
                                        Show WhatsApp publicly
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.email_visible}
                                            onChange={(e) => form.setData('email_visible', e.target.checked)}
                                            className="rounded"
                                        />
                                        Show email publicly
                                    </label>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {step === 1 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Truck className="size-5" />
                                    Fleet & Routes
                                </CardTitle>
                                <CardDescription>Select truck types and define your routes</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label>Truck Types</Label>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {truckTypes.map((t) => (
                                            <button
                                                key={t.id}
                                                type="button"
                                                onClick={() => toggleTruckType(t.id)}
                                                className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                                    form.data.truck_type_ids.includes(t.id)
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-input bg-background hover:bg-muted'
                                                }`}
                                            >
                                                {t.icon && <span className="mr-1">{t.icon}</span>}
                                                {t.name}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <Label className="flex items-center gap-1">
                                        <MapPin className="size-4" />
                                        Routes
                                    </Label>
                                    {form.data.routes.map((route, idx) => (
                                        <div key={idx} className="mt-2 flex items-center gap-2">
                                            <select
                                                value={route.origin_location_id}
                                                onChange={(e) => updateRoute(idx, 'origin_location_id', Number(e.target.value))}
                                                className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            >
                                                <option value={0}>Origin location</option>
                                                {locations.map((l) => (
                                                    <option key={l.id} value={l.id}>{l.name}, {l.region_name}</option>
                                                ))}
                                            </select>
                                            <ChevronRight className="size-4 text-muted-foreground shrink-0" />
                                            <select
                                                value={route.destination_location_id}
                                                onChange={(e) => updateRoute(idx, 'destination_location_id', Number(e.target.value))}
                                                className="flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            >
                                                <option value={0}>Destination</option>
                                                {locations.map((l) => (
                                                    <option key={l.id} value={l.id}>{l.name}, {l.region_name}</option>
                                                ))}
                                            </select>
                                            {form.data.routes.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeRoute(idx)}
                                                    className="text-destructive"
                                                >
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                    <Button type="button" variant="outline" size="sm" onClick={addRoute} className="mt-2">
                                        + Add Route
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {step === 2 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Upload className="size-5" />
                                    Documents & Submission
                                </CardTitle>
                                <CardDescription>Upload required documents and submit for review</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label>Required Documents</Label>
                                    <div className="mt-2 space-y-2 rounded-md border bg-card p-4">
                                        <p className="text-sm text-muted-foreground">
                                            Upload documents such as business license, tax certificate, insurance policy, and vehicle documents.
                                        </p>
                                        <Input type="file" multiple onChange={() => {}} className="mt-2" />
                                    </div>
                                </div>
                                <div className="rounded-md border bg-muted/50 p-4">
                                    <h3 className="mb-2 text-sm font-medium">Profile Summary</h3>
                                    <div className="space-y-1 text-sm">
                                        <p><span className="font-medium">Name:</span> {form.data.display_name}</p>
                                        <p><span className="font-medium">Legal:</span> {form.data.legal_name || '—'}</p>
                                        <p><span className="font-medium">Location:</span> {form.data.primary_location_id ? locations.find((l) => l.id === form.data.primary_location_id)?.name ?? '—' : '—'}</p>
                                        <p><span className="font-medium">Truck Types:</span> {form.data.truck_type_ids.length} selected</p>
                                        <p><span className="font-medium">Routes:</span> {form.data.routes.length} defined</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep(step - 1)}
                            disabled={step === 0}
                        >
                            Back
                        </Button>
                        <div className="flex items-center gap-2">
                            {profile && (
                                <Badge variant="outline" className="text-xs">
                                    {profile.id ? 'Draft' : 'New'}
                                </Badge>
                            )}
                            {step < STEPS.length - 1 ? (
                                <Button onClick={handleSubmit} disabled={form.processing}>
                                    Next Step
                                    <ChevronRight className="ml-1 size-4" />
                                </Button>
                            ) : (
                                <Button type="submit" disabled={form.processing}>
                                    {profile ? 'Update Profile' : 'Submit for Review'}
                                </Button>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}