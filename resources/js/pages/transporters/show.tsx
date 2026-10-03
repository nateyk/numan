import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import {
    Mail,
    MapPin,
    Package,
    Phone,
    ShieldCheck,
    Truck,
} from 'lucide-react';

interface TruckType {
    id: number;
    name: string;
    slug: string;
}

interface Route {
    origin_location: { name: string; region_name: string };
    destination_location: { name: string; region_name: string };
}

interface Document {
    id: number;
    name: string;
    url: string;
    type: string;
    uploaded_at: string;
}

interface PrimaryLocation {
    name: string;
    region_name: string;
    address: string;
}

interface Profile {
    id: number;
    slug: string;
    display_name: string;
    business_description: string;
    is_company: boolean;
    status: string;
    verification_level: string;
    is_verified: boolean;
    completion_score: number;
    rating: number | null;
    total_routes: number;
    // No phone, whatsapp, email, legal_name or *_visible flags. Contact
    // details are released only through the reveal action, which requires an
    // existing interest or connection.
    primary_location: PrimaryLocation | null;
    truck_types: TruckType[];
    routes: Route[];
    // Name and type only. No file URL: a public page must not offer a direct
    // download link to somebody's licence or ID.
    documents: Array<{
        id: number;
        name: string;
        type: string;
        uploaded_at: string | null;
    }>;
}

interface Props {
    profile: Profile;
    truckTypes: TruckType[];
}

export default function TransporterShow({ profile, truckTypes }: Props) {
    const verificationBadge =
        profile.verification_level === 'verified' ? (
            <Badge variant="secondary" className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                <ShieldCheck className="mr-1 h-3 w-3" />
                Verified
            </Badge>
        ) : null;

    return (
        <AdminLayout>
            <Head title={profile.display_name} />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-2xl font-bold tracking-tight">
                                {profile.display_name}
                            </h2>
                            {verificationBadge}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {profile.total_routes} route
                            {profile.total_routes === 1 ? '' : 's'}
                            {profile.rating !== null && ` \u00b7 rated ${profile.rating}`}
                        </p>
                    </div>
                    <Badge variant="outline">{profile.status}</Badge>
                </div>

                {/* Completion Score */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Profile Completion
                        </CardTitle>
                        <CardDescription>
                            {profile.completion_score}% complete
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Progress value={profile.completion_score} />
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Profile Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Business Details
                            </CardTitle>
                            <CardDescription>
                                {profile.is_company
                                    ? 'Company profile'
                                    : 'Individual transporter'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {profile.business_description && (
                                <div>
                                    <h4 className="text-sm font-medium text-muted-foreground">
                                        Description
                                    </h4>
                                    <p className="mt-1 text-sm">
                                        {profile.business_description}
                                    </p>
                                </div>
                            )}

                            <div className="space-y-2 rounded-md border bg-muted/30 p-3">
                                <p className="text-sm text-muted-foreground">
                                    Contact details are never published on this
                                    page. Express interest in a freight post, or
                                    accept an interest, and then reveal them from
                                    your interest list.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Location */}
                    {profile.primary_location && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MapPin className="h-5 w-5" />
                                    Primary Location
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">
                                    {profile.primary_location.name}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {profile.primary_location.region_name}
                                </p>
                                {profile.primary_location.address && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {profile.primary_location.address}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Truck Types */}
                    {profile.truck_types.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Truck className="h-5 w-5" />
                                    Truck Types
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {profile.truck_types.map((t) => (
                                        <Badge
                                            key={t.id}
                                            variant="outline"
                                        >
                                            {t.name}
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
                                    <MapPin className="h-5 w-5" />
                                    Routes
                                </CardTitle>
                                <CardDescription>
                                    {profile.routes.length} route(s) defined
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {profile.routes.map((route, idx) => (
                                    <div
                                        key={idx}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <span className="rounded bg-muted px-2 py-1 text-xs">
                                            {route.origin_location.name}
                                        </span>
                                        <span className="text-muted-foreground">
                                            →
                                        </span>
                                        <span className="rounded bg-muted px-2 py-1 text-xs">
                                            {route.destination_location.name}
                                        </span>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Documents */}
                {profile.documents.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5" />
                                Documents
                            </CardTitle>
                            <CardDescription>
                                {profile.documents.length} document(s) on file
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {profile.documents.map((doc) => (
                                    <div
                                        key={doc.id}
                                        className="flex items-center justify-between rounded-md border p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <FileIcon className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {doc.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {doc.type} •{' '}
                                                    {doc.uploaded_at
                                                        ? new Date(
                                                              doc.uploaded_at,
                                                          ).toLocaleDateString()
                                                        : '—'}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant="outline" className="text-[10px]">
                                            On file
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Actions */}
                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/transporters')}
                    >
                        Back
                    </Button>
                </div>
            </div>
        </AdminLayout>
    );
}

function FileIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={20}
            height={20}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
            <polyline points="14 2 14 8 20 8" />
        </svg>
    );
}