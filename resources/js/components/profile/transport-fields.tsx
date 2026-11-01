import InputError from '@/components/input-error';
import LocationSearchInput from '@/components/maps/location-search-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';
import { router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Download,
    FileText,
    Plus,
    Route as RouteIcon,
    ShieldCheck,
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
        <div className="flex flex-col gap-6">
            <Card id="carrier" className="scroll-mt-24">
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Truck
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Carrier listing
                            </CardTitle>
                            <CardDescription>
                                Vehicles and lanes shippers use to find you.
                            </CardDescription>
                        </div>

                        <Badge className={listing.className}>
                            {listing.label}
                        </Badge>
                    </div>

                    <p className="text-sm leading-relaxed text-muted-foreground">
                        {listing.description}
                    </p>

                    {status === 'rejected' && rejectionReason && (
                        <Alert className="border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                            <AlertTriangle
                                className="text-red-600 dark:text-red-400"
                                aria-hidden="true"
                            />
                            <AlertTitle>Reviewer&apos;s reason</AlertTitle>
                            <AlertDescription className="text-red-800 dark:text-red-300">
                                {rejectionReason}
                            </AlertDescription>
                        </Alert>
                    )}

                    <InputError message={errors.transport} />
                </CardHeader>

                <CardContent>
                    <form onSubmit={submitTransport} className="space-y-7">
                        <section className="space-y-3">
                            <div className="space-y-1">
                                <h3 className="text-sm font-semibold">
                                    Truck types
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    Pick every type you can put on the road.
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {truckTypes.map((type) => {
                                    const selected =
                                        form.data.truck_type_ids.includes(
                                            type.id,
                                        );

                                    return (
                                        <Toggle
                                            key={type.id}
                                            type="button"
                                            pressed={selected}
                                            onPressedChange={() =>
                                                toggleTruckType(type.id)
                                            }
                                            variant="outline"
                                            size="sm"
                                            className={cn(
                                                'h-8 px-3',
                                                selected &&
                                                    'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
                                            )}
                                        >
                                            {type.name}
                                        </Toggle>
                                    );
                                })}
                            </div>
                            <InputError message={errors.truck_type_ids} />
                        </section>

                        <Separator />

                        <section className="space-y-3">
                            <div className="flex flex-wrap items-end justify-between gap-2">
                                <div className="space-y-1">
                                    <h3 className="text-sm font-semibold">
                                        Routes
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Up to five regular runs. Shippers use
                                        these to find a carrier already heading
                                        their way.
                                    </p>
                                </div>
                                <Badge variant="outline">
                                    {form.data.routes.length} of 5
                                </Badge>
                            </div>

                            {form.data.routes.length === 0 ? (
                                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center">
                                    <RouteIcon
                                        className="size-5 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <p className="text-sm font-medium">
                                        No routes yet
                                    </p>
                                    <p className="max-w-sm text-xs leading-relaxed text-muted-foreground">
                                        Add the runs you make most often so
                                        shippers can find you.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="mt-1"
                                        onClick={addRoute}
                                    >
                                        <Plus aria-hidden="true" />
                                        Add your first route
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    {form.data.routes.map((route, index) => (
                                        <div
                                            key={route.key}
                                            className="grid gap-2 rounded-lg border bg-card p-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] sm:items-start"
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
                                                            id === 0
                                                                ? null
                                                                : id,
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

                                            <ArrowRight
                                                className="mt-2.5 hidden size-4 text-muted-foreground sm:block"
                                                aria-hidden="true"
                                            />

                                            <div>
                                                <LocationSearchInput
                                                    initialName={locationName(
                                                        route.destination_location_id,
                                                    )}
                                                    onChange={(id) =>
                                                        updateRoute(
                                                            route.key,
                                                            'destination_location_id',
                                                            id === 0
                                                                ? null
                                                                : id,
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
                                                onClick={() =>
                                                    removeRoute(route.key)
                                                }
                                                aria-label={`Remove route ${index + 1}`}
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    ))}

                                    <div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={addRoute}
                                            disabled={
                                                form.data.routes.length >= 5
                                            }
                                        >
                                            <Plus aria-hidden="true" />
                                            Add a route
                                        </Button>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {form.data.routes.length} of 5
                                            routes used.
                                        </p>
                                    </div>
                                </>
                            )}

                            <InputError message={errors.routes} />
                        </section>

                        <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                An approved listing stays approved when you edit
                                it.
                            </p>
                            <Button
                                type="submit"
                                disabled={form.processing}
                                data-test="carrier-save"
                            >
                                {form.processing && <Spinner />}
                                Save and submit for review
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card id="documents" className="scroll-mt-24">
                <CardHeader>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <FileText
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Documents
                            </CardTitle>
                            <CardDescription>
                                Private to you and the review team. PDF, JPG, or
                                PNG up to 5MB.
                            </CardDescription>
                        </div>

                        <Badge variant="outline">
                            {documents.length}{' '}
                            {documents.length === 1 ? 'file' : 'files'}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent className="space-y-5">
                    {documents.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed px-4 py-8 text-center">
                            <FileText
                                className="size-5 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="text-sm font-medium">
                                No documents yet
                            </p>
                            <p className="max-w-sm text-xs leading-relaxed text-muted-foreground">
                                Upload a licence, insurance policy, or
                                registration to support your listing.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y rounded-lg border">
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
                                        className="flex flex-wrap items-center gap-3 px-3 py-3"
                                    >
                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md border bg-muted/50 text-muted-foreground">
                                            <FileText
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </span>

                                        <div className="min-w-0 flex-1">
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

                                        <div className="flex items-center gap-1">
                                            <Badge
                                                className={
                                                    documentStatus.className
                                                }
                                            >
                                                {documentStatus.label}
                                            </Badge>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                            >
                                                <a
                                                    href={`/public-profile/documents/${document.id}/download`}
                                                    download
                                                    aria-label={`Download ${document.name}`}
                                                >
                                                    <Download aria-hidden="true" />
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
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <div className="rounded-lg border bg-muted/20 p-4">
                        <div className="mb-3 space-y-1">
                            <h3 className="text-sm font-semibold">
                                Add a document
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Uploading one adds 10% to your completion score.
                            </p>
                        </div>

                        <form
                            onSubmit={submitDocument}
                            className="grid gap-3 sm:grid-cols-[minmax(0,200px)_minmax(0,1fr)_auto] sm:items-end"
                        >
                            <div>
                                <Label htmlFor="document_type">
                                    Document type
                                </Label>
                                <Select
                                    value={documentForm.data.type}
                                    onValueChange={(value) =>
                                        documentForm.setData('type', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="document_type"
                                        className="mt-1 w-full"
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

                            <div>
                                <Label htmlFor="document_file">File</Label>
                                <Input
                                    id="document_file"
                                    type="file"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    className="mt-1 cursor-pointer"
                                    data-test="document-input"
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
                                className="w-full sm:w-auto"
                                disabled={
                                    documentForm.processing ||
                                    documentForm.data.file === null
                                }
                                data-test="document-upload"
                            >
                                {documentForm.processing ? (
                                    <Spinner />
                                ) : (
                                    <Upload aria-hidden="true" />
                                )}
                                Upload
                            </Button>
                        </form>
                    </div>

                    <p className="flex items-start gap-1.5 text-xs leading-relaxed text-muted-foreground">
                        <ShieldCheck
                            className="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        Only you and a superadmin can download these files.
                        Their names and types never appear on your public page.
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
