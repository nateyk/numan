import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    FileText,
    Mail,
    MapPin,
    Phone,
    Truck,
    User,
    XCircle,
} from 'lucide-react';
import { type SharedData } from '@/types';

interface Location {
    id: number;
    name: string;
    type: string;
    region_name: string;
}

interface TruckType {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
}

interface Route {
    id: number;
    origin: Location | null;
    destination: Location | null;
}

interface Document {
    id: number;
    type: string;
    original_name: string;
    stored_path: string;
    mime: string;
    size: number | null;
    status: string;
    reviewed_by: number | null;
    reviewed_at: string | null;
    created_at: string;
    reviewer: { name: string } | null;
}

interface Moderation {
    id: number;
    from_status: string;
    to_status: string;
    reason: string | null;
    moderator: string | null;
    created_at: string | null;
}

interface UserData {
    id: number;
    name: string;
    email: string;
}

interface Profile {
    id: number;
    slug: string;
    display_name: string;
    legal_name: string | null;
    phone: string | null;
    whatsapp: string | null;
    email: string;
    business_description: string | null;
    is_company: boolean;
    status: string;
    verification_level: string;
    completion_score: number;
    phone_visible: boolean;
    whatsapp_visible: boolean;
    email_visible: boolean;
    primary_location: Location | null;
    truck_types: TruckType[];
    routes: Route[];
    documents: Document[];
    moderations: Moderation[];
    user: UserData;
    created_at: string | null;
}

interface Props {
    profile: Profile;
}

const STATUS_CONFIG: Record<
    string,
    { label: string; className: string }
> = {
    draft: {
        label: 'Draft',
        className: 'bg-muted text-muted-foreground',
    },
    pending: {
        label: 'Pending',
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
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

const VERIFICATION_CONFIG: Record<
    string,
    { label: string; className: string }
> = {
    unverified: {
        label: 'Unverified',
        className: 'bg-muted text-muted-foreground',
    },
    verified: {
        label: 'Verified',
        className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
};

export default function AdminTransporterShow({ profile }: Props) {
    const flash = usePage<SharedData>().props.flash;

    const { data, setData, processing, errors, put } = useForm({
        status: profile.status,
        verification_level: profile.verification_level,
        reason: '',
    });

    const handleApprove = () => {
        if (
            !confirm(
                `Are you sure you want to approve "${profile.display_name}"?`,
            )
        ) {
            return;
        }
        router.patch(
            `/admin/transporters/${profile.slug}/moderate`,
            {
                status: 'approved',
                verification_level:
                    data.verification_level || profile.verification_level,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setData('status', 'approved');
                    setData('verification_level', data.verification_level);
                },
            },
        );
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.reason.trim()) {
            return;
        }
        router.patch(`/admin/transporters/${profile.slug}/moderate`, {
                status: 'rejected',
                reason: data.reason,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setData('status', 'rejected');
                },
            });
    };

    const handleDocumentReview = (
        documentId: number,
        status: string,
    ) => {
        if (!confirm(`Mark document as ${status}?`)) {
            return;
        }
        router.patch(
            `/admin/transporters/documents/${documentId}/review`,
            { status },
            { preserveScroll: true },
        );
    };

    const statusConfig = STATUS_CONFIG[profile.status] || STATUS_CONFIG.pending;
    const verificationConfig =
        VERIFICATION_CONFIG[profile.verification_level] ||
        VERIFICATION_CONFIG.unverified;

    return (
        <AdminLayout>
            <Head title={`Transporter: ${profile.display_name}`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/admin/transporters">
                            <ArrowLeft className="mr-1.5 h-4 w-4" />
                            Back to Profiles
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h2 className="text-2xl font-bold tracking-tight">
                            {profile.display_name}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {profile.email}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span
                            className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ${statusConfig.className}`}
                        >
                            {statusConfig.label}
                        </span>
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${verificationConfig.className}`}
                        >
                            {verificationConfig.label}
                        </span>
                    </div>
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

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Profile Info */}
                    <div className="md:col-span-2 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Profile Information</CardTitle>
                                <CardDescription>
                                    Transporter account details
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Display Name
                                        </p>
                                        <p className="text-sm font-medium">
                                            {profile.display_name}
                                        </p>
                                    </div>
                                    {profile.legal_name && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Legal Name
                                            </p>
                                            <p className="text-sm">
                                                {profile.legal_name}
                                            </p>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Email
                                        </p>
                                        <div className="flex items-center gap-1.5 text-sm">
                                            <Mail className="h-3.5 w-3.5 text-muted-foreground" />
                                            {profile.email}
                                        </div>
                                    </div>
                                    {profile.phone && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Phone
                                            </p>
                                            <div className="flex items-center gap-1.5 text-sm">
                                                <Phone className="h-3.5 w-3.5 text-muted-foreground" />
                                                {profile.phone}
                                            </div>
                                        </div>
                                    )}
                                    {profile.whatsapp && (
                                        <div>
                                            <p className="text-xs font-medium text-muted-foreground">
                                                WhatsApp
                                            </p>
                                            <div className="flex items-center gap-1.5 text-sm">
                                                <Phone className="h-3.5 w-3.5 text-muted-foreground" />
                                                {profile.whatsapp}
                                            </div>
                                        </div>
                                    )}
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Company
                                        </p>
                                        <p className="text-sm">
                                            {profile.is_company
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                </div>
                                {profile.business_description && (
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground mb-1">
                                            Business Description
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {profile.business_description}
                                        </p>
                                    </div>
                                )}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Completion Score
                                        </p>
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-32 rounded-full bg-muted">
                                                <div
                                                    className="h-full rounded-full bg-primary transition-all"
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
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Visibility
                                        </p>
                                        <div className="flex items-center gap-3 text-sm">
                                            <span className={profile.phone_visible ? 'text-emerald-600' : 'text-muted-foreground'}>
                                                Phone: {profile.phone_visible ? 'Visible' : 'Hidden'}
                                            </span>
                                            <span className={profile.whatsapp_visible ? 'text-emerald-600' : 'text-muted-foreground'}>
                                                WhatsApp: {profile.whatsapp_visible ? 'Visible' : 'Hidden'}
                                            </span>
                                            <span className={profile.email_visible ? 'text-emerald-600' : 'text-muted-foreground'}>
                                                Email: {profile.email_visible ? 'Visible' : 'Hidden'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Primary Location */}
                        {profile.primary_location && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-4 w-4" />
                                        Primary Location
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">
                                        {profile.primary_location.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {profile.primary_location.type} ·{' '}
                                        {profile.primary_location.region_name}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {/* Truck Types */}
                        {profile.truck_types.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Truck className="h-4 w-4" />
                                        Truck Types
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {profile.truck_types.map((tt) => (
                                            <Badge key={tt.id} variant="outline">
                                                {tt.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Routes */}
                        {profile.routes.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Truck className="h-4 w-4" />
                                        Routes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {profile.routes.map((route) => (
                                            <div
                                                key={route.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <span className="font-medium">
                                                    {route.origin?.name ?? '—'}
                                                </span>
                                                <span className="text-muted-foreground">→</span>
                                                <span className="font-medium">
                                                    {route.destination?.name ?? '—'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Right Column */}
                    <div className="space-y-6">
                        {/* Moderation Actions */}
                        {(profile.status === 'pending' ||
                            profile.status === 'rejected') && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Moderation</CardTitle>
                                    <CardDescription>
                                        Approve or reject this profile
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {(profile.status === 'pending' ||
                                        profile.status === 'rejected') && (
                                        <>
                                            <div>
                                                <Label>Verification Level</Label>
                                                <Select
                                                    value={data.verification_level}
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'verification_level',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="mt-1">
                                                        <SelectValue placeholder="Select verification level" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="unverified">
                                                            Unverified
                                                        </SelectItem>
                                                        <SelectItem value="verified">
                                                            Verified
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <Button
                                                variant="default"
                                                size="sm"
                                                className="w-full"
                                                onClick={handleApprove}
                                                disabled={processing}
                                            >
                                                <CheckCircle className="mr-1.5 h-4 w-4" />
                                                Approve
                                            </Button>
                                        </>
                                    )}
                                    {(profile.status as string) !== 'approved' && (
                                        <form onSubmit={handleReject}>
                                            <div>
                                                <Label htmlFor="reason">
                                                    Reason
                                                    {profile.status ===
                                                        'rejected' && (
                                                        <span className="text-destructive">
                                                            {' '}
                                                            *
                                                        </span>
                                                    )}
                                                </Label>
                                                <Textarea
                                                    id="reason"
                                                    value={data.reason}
                                                    onChange={(e) =>
                                                        setData(
                                                            'reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Enter reason for rejection..."
                                                    rows={3}
                                                    className="mt-1"
                                                />
                                                {errors.reason && (
                                                    <p className="mt-1 text-xs text-destructive">
                                                        {errors.reason}
                                                    </p>
                                                )}
                                            </div>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                size="sm"
                                                className="w-full"
                                                disabled={processing}
                                            >
                                                <XCircle className="mr-1.5 h-4 w-4" />
                                                Reject
                                            </Button>
                                        </form>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Moderation History */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Moderation History</CardTitle>
                                <CardDescription>
                                    {profile.moderations.length}{' '}
                                    {profile.moderations.length === 1
                                        ? 'action'
                                        : 'actions'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {profile.moderations.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No moderation actions yet.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {profile.moderations.map(
                                            (mod) => (
                                                <div
                                                    key={mod.id}
                                                    className="rounded-md border p-3 text-sm"
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span
                                                            className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CONFIG[mod.to_status]?.className || ''}`}
                                                        >
                                                            {STATUS_CONFIG[mod.to_status]?.label ?? mod.to_status}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {mod.created_at
                                                                ? new Date(
                                                                      mod.created_at,
                                                                  ).toLocaleDateString()
                                                                : '—'}
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {mod.from_status} →{' '}
                                                        {mod.to_status}
                                                        {mod.moderator && (
                                                            <> by{' '}
                                                            <span className="font-medium text-foreground">
                                                                {mod.moderator}
                                                            </span>
                                                            </>
                                                        )}
                                                    </p>
                                                    {mod.reason && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            Reason:{' '}
                                                            {mod.reason}
                                                        </p>
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Documents */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    Documents
                                    <Badge variant="outline">
                                        {profile.documents.length}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {profile.documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded yet.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {profile.documents.map((doc) => (
                                            <div
                                                key={doc.id}
                                                className="flex items-center justify-between rounded-md border p-3"
                                            >
                                                <div className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4 text-muted-foreground" />
                                                    <div>
                                                        <p className="text-sm">
                                                            {doc.original_name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {doc.type} ·{' '}
                                                            {doc.reviewed_at
                                                                ? new Date(
                                                                      doc.reviewed_at,
                                                                  ).toLocaleDateString()
                                                                : 'Not reviewed'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant={
                                                            doc.status === 'approved'
                                                                ? 'default'
                                                                : doc.status === 'rejected'
                                                                    ? 'destructive'
                                                                    : 'outline'
                                                        }
                                                        className="text-[10px]"
                                                    >
                                                        {doc.status}
                                                    </Badge>
                                                    {doc.status === 'pending' && (
                                                        <>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleDocumentReview(
                                                                        doc.id,
                                                                        'approved',
                                                                    )
                                                                }
                                                            >
                                                                <CheckCircle className="h-3 w-3 text-emerald-600" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleDocumentReview(
                                                                        doc.id,
                                                                        'rejected',
                                                                    )
                                                                }
                                                            >
                                                                <XCircle className="h-3 w-3 text-red-600" />
                                                            </Button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}