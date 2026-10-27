import InputError from '@/components/input-error';
import LocationSearchInput from '@/components/maps/location-search-input';
import CompletionScore from '@/components/transporter/completion-score';
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
import { router, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    FileText,
    Plus,
    Trash2,
    Truck,
    Upload,
} from 'lucide-react';
import { useRef } from 'react';

interface TransportRoute {
    id?: number;
    origin_location_id: number | null;
    destination_location_id: number | null;
}

interface EditableRoute extends TransportRoute {
    key: string;
}

interface ProfileDocument {
    id: number;
    type: string;
    name: string;
    status: string;
    uploaded_at: string | null;
}

interface Props {
    status: string | null;
    rejectionReason: string | null;
    completionScore: number;
    truckTypes: { id: number; name: string }[];
    locations: { id: number; name: string; region_name: string | null }[];
    truckTypeIds: number[];
    routes: TransportRoute[];
    documents: ProfileDocument[];
}

const DOCUMENT_TYPES: { value: string; label: string }[] = [
    { value: 'license', label: 'Transport licence' },
    { value: 'insurance', label: 'Insurance policy' },
    { value: 'business_registration', label: 'Business registration' },
    { value: 'id', label: 'Identity document' },
    { value: 'other', label: 'Other document' },
];

const DOCUMENT_TYPE_LABELS: Record<string, string> = Object.fromEntries(
    DOCUMENT_TYPES.map((type) => [type.value, type.label]),
);

export const LISTING_STATUS: Record<
    string,
    { label: string; description: string; className: string }
> = {
    draft: {
        label: 'Draft',
        description:
            'Not submitted yet. Save your carrier details to send the listing for review.',
        className:
            'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-300',
    },
    pending: {
        label: 'Pending review',
        description:
            'An administrator is checking your listing. It is not public while it waits.',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    approved: {
        label: 'Approved and listed',
        description:
            'Your carrier profile appears in the public directory. Editing details keeps this approval.',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    rejected: {
        label: 'Rejected',
        description:
            'Your listing was not approved. Fix the reason below and save again to resubmit.',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

const DOCUMENT_STATUS: Record<string, { label: string; className: string }> = {
    pending: {
        label: 'Pending review',
        className:
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    },
    approved: {
        label: 'Reviewed',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    rejected: {
        label: 'Rejected',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    },
};

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('en', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

/**
 * The carrier half of the profile: fleet, routes, documents, review status.
 *
 * Rendered only after `provides_transport` has been saved, because the server
 * refuses transport rows for a profile that does not carry freight. The
 * documents section has its own form: an upload is a file request, not part of
 * the JSON save.
 */
export default function TransportFields({
    status,
    rejectionReason,
    completionScore,
    truckTypes,
    locations,
    truckTypeIds,
    routes,
    documents,
}: Props) {
    const nextKey = useRef(0);

    const form = useForm<{
        truck_type_ids: number[];
        routes: EditableRoute[];
    }>({
        truck_type_ids: truckTypeIds,
        routes: routes.map((route, index) => ({
            key: route.id ? `saved-${route.id}` : `saved-${index}`,
            origin_location_id: route.origin_location_id,
            destination_location_id: route.destination_location_id,
        })),
    });

    const documentForm = useForm<{ type: string; file: File | null }>({
        type: 'license',
        file: null,
    });

    const errors = form.errors as Record<string, string | undefined>;
    const documentErrors = documentForm.errors as Record<
        string,
        string | undefined
    >;

    const listing = LISTING_STATUS[status ?? 'draft'] ?? LISTING_STATUS.draft;

    const locationName = (id: number | null): string | undefined => {
        if (id === null) {
            return undefined;
        }

        return locations.find((location) => location.id === id)?.name;
    };

    const updateRoute = (
        key: string,
        field: 'origin_location_id' | 'destination_location_id',
        value: number | null,
    ) => {
        form.setData(
            'routes',
            form.data.routes.map((route) =>
                route.key === key ? { ...route, [field]: value } : route,
            ),
        );
    };

    const addRoute = () => {
        if (form.data.routes.length >= 5) {
            return;
        }

        form.setData('routes', [
            ...form.data.routes,
            {
                key: `new-${nextKey.current++}`,
                origin_location_id: null,
                destination_location_id: null,
            },
        ]);
    };

    const removeRoute = (key: string) => {
        form.setData(
            'routes',
            form.data.routes.filter((route) => route.key !== key),
        );
    };

    const toggleTruckType = (id: number) => {
        const selected = form.data.truck_type_ids;

        form.setData(
            'truck_type_ids',
            selected.includes(id)
                ? selected.filter((typeId) => typeId !== id)
                : [...selected, id],
        );
    };

    const submitTransport = (event: React.FormEvent) => {
        event.preventDefault();

        form.transform((data) => ({
            truck_type_ids: data.truck_type_ids,
            routes: data.routes.map(
                ({ origin_location_id, destination_location_id }) => ({
                    origin_location_id,
                    destination_location_id,
                }),
            ),
        }));

        form.put('/public-profile/transport', { preserveScroll: true });
    };

    const submitDocument = (event: React.FormEvent) => {
        event.preventDefault();

        documentForm.post('/public-profile/documents', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => documentForm.reset('file'),
        });
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Truck className="size-4" />
                                Carrier details
                            </CardTitle>
                            <CardDescription>
                                What shippers see in the carrier directory, and
                                what reviewers check.
                            </CardDescription>
                        </div>
                        <div className="flex items-center gap-3">
                            <CompletionScore score={completionScore} />
                            <div className="text-xs text-muted-foreground">
                                Profile
                                <br />
                                completeness
                            </div>
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <Badge className={listing.className}>
                            {listing.label}
                        </Badge>
                        <p className="text-xs text-muted-foreground">
                            {listing.description}
                        </p>
                    </div>

                    {status === 'rejected' && rejectionReason && (
                        <div className="mt-2 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                            <span className="font-medium">
                                Reviewer&apos;s reason:
                            </span>{' '}
                            {rejectionReason}
                        </div>
                    )}

                    <InputError message={errors.transport} />
                </CardHeader>

                <CardContent>
                    <form onSubmit={submitTransport} className="space-y-6">
                        <div className="grid gap-2">
                            <Label>Truck types</Label>
                            <p className="text-xs text-muted-foreground">
                                Pick every type you can put on the road.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {truckTypes.map((type) => {
                                    const selected =
                                        form.data.truck_type_ids.includes(
                                            type.id,
                                        );

                                    return (
                                        <button
                                            key={type.id}
                                            type="button"
                                            aria-pressed={selected}
                                            onClick={() =>
                                                toggleTruckType(type.id)
                                            }
                                            className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                                selected
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-input bg-background hover:bg-muted'
                                            }`}
                                        >
                                            {type.name}
                                        </button>
                                    );
                                })}
                            </div>
                            <InputError message={errors.truck_type_ids} />
                        </div>

                        <div className="grid gap-3">
                            <div>
                                <Label>Routes</Label>
                                <p className="text-xs text-muted-foreground">
                                    Up to five regular routes. Shippers use
                                    these to find a carrier already heading
                                    their way.
                                </p>
                            </div>

                            {form.data.routes.length === 0 && (
                                <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                                    No routes yet. Add the runs you make most
                                    often.
                                </p>
                            )}

                            {form.data.routes.map((route, index) => (
                                <div
                                    key={route.key}
                                    className="grid gap-2 rounded-md border p-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] sm:items-start"
                                >
                                    <div>
                                        <LocationSearchInput
                                            initialName={locationName(
                                                route.origin_location_id,
                                            )}
                                            onChange={(id) =>
                                                updateRoute(
                                                    route.key,
                                                    'origin_location_id',
                                                    id === 0 ? null : id,
                                                )
                                            }
                                            localOnly
                                            type="city"
                                            placeholder="Origin"
                                            ariaLabel={`Origin for route ${index + 1}`}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `routes.${index}.origin_location_id`
                                                ]
                                            }
                                        />
                                    </div>

                                    <ArrowRight className="mt-2.5 hidden size-4 text-muted-foreground sm:block" />

                                    <div>
                                        <LocationSearchInput
                                            initialName={locationName(
                                                route.destination_location_id,
                                            )}
                                            onChange={(id) =>
                                                updateRoute(
                                                    route.key,
                                                    'destination_location_id',
                                                    id === 0 ? null : id,
                                                )
                                            }
                                            localOnly
                                            type="city"
                                            placeholder="Destination"
                                            ariaLabel={`Destination for route ${index + 1}`}
                                        />
                                        <InputError
                                            message={
                                                errors[
                                                    `routes.${index}.destination_location_id`
                                                ]
                                            }
                                        />
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="justify-self-start text-muted-foreground hover:text-destructive sm:mt-0.5"
                                        onClick={() => removeRoute(route.key)}
                                        aria-label={`Remove route ${index + 1}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}

                            <InputError message={errors.routes} />

                            <div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addRoute}
                                    disabled={form.data.routes.length >= 5}
                                >
                                    <Plus className="mr-1.5 size-4" />
                                    Add a route
                                </Button>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {form.data.routes.length} of 5 routes used.
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-3 border-t pt-4">
                            <Button type="submit" disabled={form.processing}>
                                Save and submit for review
                            </Button>
                            <p className="text-xs text-muted-foreground">
                                An approved listing stays approved when you edit
                                it.
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <FileText className="size-4" />
                        Documents
                    </CardTitle>
                    <CardDescription>
                        Private to you and the review team. Uploading one adds
                        10% to your completion score. PDF, JPG, or PNG up to
                        5MB.
                    </CardDescription>
                </CardHeader>

                <CardContent className="space-y-6">
                    {documents.length === 0 ? (
                        <p className="rounded-md border border-dashed px-3 py-6 text-center text-sm text-muted-foreground">
                            No documents uploaded yet.
                        </p>
                    ) : (
                        <ul className="divide-y rounded-md border">
                            {documents.map((document) => {
                                const documentStatus = DOCUMENT_STATUS[
                                    document.status
                                ] ?? {
                                    label: document.status,
                                    className:
                                        'bg-slate-100 text-slate-800 dark:bg-slate-900/40 dark:text-slate-300',
                                };

                                const uploaded = formatDate(
                                    document.uploaded_at,
                                );

                                return (
                                    <li
                                        key={document.id}
                                        className="flex flex-wrap items-center justify-between gap-3 px-3 py-3"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {document.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {DOCUMENT_TYPE_LABELS[
                                                    document.type
                                                ] ?? document.type}
                                                {uploaded && ` · ${uploaded}`}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            <Badge
                                                className={
                                                    documentStatus.className
                                                }
                                            >
                                                {documentStatus.label}
                                            </Badge>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={`/public-profile/documents/${document.id}/download`}
                                                    download
                                                >
                                                    Download
                                                </a>
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-destructive"
                                                aria-label={`Remove ${document.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        `/public-profile/documents/${document.id}`,
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <form
                        onSubmit={submitDocument}
                        className="flex flex-col gap-3 rounded-md border bg-muted/30 p-4 sm:flex-row sm:items-end"
                    >
                        <div className="w-full sm:w-56">
                            <Label htmlFor="document_type">Document type</Label>
                            <Select
                                value={documentForm.data.type}
                                onValueChange={(value) =>
                                    documentForm.setData('type', value)
                                }
                            >
                                <SelectTrigger
                                    id="document_type"
                                    className="mt-1"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {DOCUMENT_TYPES.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={documentErrors.type} />
                        </div>

                        <div className="w-full flex-1">
                            <Label htmlFor="document_file">File</Label>
                            <Input
                                id="document_file"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                className="mt-1"
                                onChange={(event) =>
                                    documentForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <InputError message={documentErrors.file} />
                        </div>

                        <Button
                            type="submit"
                            variant="outline"
                            disabled={
                                documentForm.processing ||
                                documentForm.data.file === null
                            }
                        >
                            <Upload className="mr-1.5 size-4" />
                            Upload
                        </Button>
                    </form>

                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <CheckCircle2 className="size-3.5" />
                        Only you and a superadmin can download these files.
                        Their names and types never appear on your public page.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
