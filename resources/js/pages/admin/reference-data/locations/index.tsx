import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    MapPin,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface LocationItem {
    id: number;
    name: string;
    region_name: string;
    type: string;
    latitude: string | null;
    longitude: string | null;
    is_active: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    locations: {
        data: LocationItem[];
        links: PaginationLink[];
        total: number;
        last_page: number;
    };
    filters: { search: string };
}

const TYPE_COLORS: Record<string, string> = {
    city: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    town:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    woreda:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
};

/** Decode HTML entities in Laravel paginator labels (e.g. &laquo;, &raquo;). */
function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&');
}

export default function AdminLocations({ locations, filters }: Props) {
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        name: '',
        region_name: '',
        type: 'city',
        latitude: '',
        longitude: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/reference-data/locations', {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
            preserveScroll: true,
        });
    };

    const toggleLocation = (id: number) => {
        router.patch(
            `/admin/reference-data/locations/${id}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const deleteLocation = (location: LocationItem) => {
        if (confirm(`Delete location "${location.name}"?`)) {
            router.delete(`/admin/reference-data/locations/${location.id}`, {
                preserveScroll: true,
            });
        }
    };

    const handleSearchChange = (value: string) => {
        router.get(
            '/admin/reference-data/locations',
            value !== '' ? { search: value } : {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AdminLayout>
            <Head title="Locations" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <MapPin className="h-6 w-6" />
                            Locations
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Manage reference locations across regions
                        </p>
                    </div>
                    <Button onClick={() => setShowForm(!showForm)} size="sm">
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Location
                    </Button>
                </div>

                {/* Search */}
                <div className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search locations…"
                        defaultValue={filters.search}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        className="pl-9"
                    />
                </div>

                {/* Create Form */}
                {showForm && (
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-4 rounded-md border bg-card p-6"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">
                                    Name *
                                </label>
                                <Input
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Addis Ababa"
                                    className="mt-1"
                                />
                                {form.errors.name && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium">
                                    Region *
                                </label>
                                <Input
                                    value={form.data.region_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'region_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Oromia"
                                    className="mt-1"
                                />
                                {form.errors.region_name && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.region_name}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">
                                    Type *
                                </label>
                                <select
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                    className="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option value="city">City</option>
                                    <option value="town">Town</option>
                                    <option value="woreda">Woreda</option>
                                </select>
                                {form.errors.type && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.type}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium">
                                    Latitude
                                </label>
                                <Input
                                    type="number"
                                    step="any"
                                    value={form.data.latitude ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'latitude',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 9.025"
                                    className="mt-1"
                                />
                                {form.errors.latitude && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.latitude}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">
                                    Longitude
                                </label>
                                <Input
                                    type="number"
                                    step="any"
                                    value={form.data.longitude ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'longitude',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 38.7469"
                                    className="mt-1"
                                />
                                {form.errors.longitude && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.longitude}
                                    </p>
                                )}
                            </div>
                            <div className="flex items-center gap-6">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded"
                                    />
                                    Active
                                </label>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Creating...'
                                    : 'Create Location'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowForm(false)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                )}

                {/* Locations List */}
                <div className="space-y-3">
                    {locations.data.length === 0 ? (
                        <div className="rounded-md border bg-card p-12 text-center text-muted-foreground">
                            {filters.search === ''
                                ? 'No locations yet. Create one to get started.'
                                : `No locations matching "${filters.search}".`}
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Region</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Coordinates</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {locations.data.map((location) => (
                                    <TableRow key={location.id}>
                                        <TableCell className="font-medium">
                                            {location.name}
                                        </TableCell>
                                        <TableCell>
                                            {location.region_name}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={TYPE_COLORS[location.type] || 'bg-muted text-muted-foreground'}
                                            >
                                                {location.type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {location.latitude &&
                                            location.longitude
                                                ? `${location.latitude}, ${location.longitude}`
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {location.is_active ? (
                                                <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-muted text-muted-foreground">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(
                                                location.created_at,
                                            ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        toggleLocation(
                                                            location.id,
                                                        )
                                                    }
                                                    title={
                                                        location.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'
                                                    }
                                                >
                                                    {location.is_active ? (
                                                        <ToggleRight className="h-4 w-4 text-emerald-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        deleteLocation(
                                                            location,
                                                        )
                                                    }
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                {/* Pagination */}
                {locations.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {locations.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() =>
                                    link.url &&
                                    router.get(
                                        link.url,
                                        {},
                                        { preserveState: true },
                                    )
                                }
                            >
                                {decodePaginationLabel(link.label)}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}