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
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Truck,
} from 'lucide-react';
import { useState } from 'react';

interface TruckTypeItem {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    description: string | null;
    is_active: boolean;
    sort_order: number | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    truckTypes: {
        data: TruckTypeItem[];
        links: PaginationLink[];
        total: number;
        last_page: number;
    };
    filters: { search: string };
}

/** Decode HTML entities in Laravel paginator labels (e.g. &laquo;, &raquo;). */
function decodePaginationLabel(label: string): string {
    return label
        .replace(/&laquo;/g, '«')
        .replace(/&raquo;/g, '»')
        .replace(/&amp;/g, '&');
}

export default function AdminTruckTypes({ truckTypes, filters }: Props) {
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        name: '',
        slug: '',
        icon: '',
        description: '',
        is_active: true,
        sort_order: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/reference-data/truck-types', {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
            preserveScroll: true,
        });
    };

    const toggleTruckType = (id: number) => {
        router.patch(
            `/admin/reference-data/truck-types/${id}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const deleteTruckType = (truckType: TruckTypeItem) => {
        if (confirm(`Delete truck type "${truckType.name}"?`)) {
            router.delete(
                `/admin/reference-data/truck-types/${truckType.id}`,
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const handleSearchChange = (value: string) => {
        router.get(
            '/admin/reference-data/truck-types',
            value !== '' ? { search: value } : {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AdminLayout>
            <Head title="Truck Types" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Truck className="h-6 w-6" />
                            Truck Types
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Manage truck type reference data
                        </p>
                    </div>
                    <Button onClick={() => setShowForm(!showForm)} size="sm">
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Truck Type
                    </Button>
                </div>

                {/* Search */}
                <div className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search truck types…"
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
                                    placeholder="e.g. Flatbed"
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
                                    Slug *
                                </label>
                                <Input
                                    value={form.data.slug}
                                    onChange={(e) =>
                                        form.setData('slug', e.target.value)
                                    }
                                    placeholder="e.g. flatbed"
                                    className="mt-1"
                                />
                                {form.errors.slug && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.slug}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="text-sm font-medium">
                                    Icon
                                </label>
                                <Input
                                    value={form.data.icon}
                                    onChange={(e) =>
                                        form.setData('icon', e.target.value)
                                    }
                                    placeholder="e.g. truck-icon"
                                    className="mt-1"
                                />
                                {form.errors.icon && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.icon}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium">
                                    Sort Order
                                </label>
                                <Input
                                    type="number"
                                    value={form.data.sort_order ?? ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'sort_order',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. 1"
                                    className="mt-1"
                                />
                                {form.errors.sort_order && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.sort_order}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium">
                                Description
                            </label>
                            <Input
                                value={form.data.description ?? ''}
                                onChange={(e) =>
                                    form.setData(
                                        'description',
                                        e.target.value,
                                    )
                                }
                                placeholder="e.g. Standard flatbed trailer"
                                className="mt-1"
                            />
                            {form.errors.description && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.description}
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
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Creating...'
                                    : 'Create Truck Type'}
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

                {/* Truck Types List */}
                <div className="space-y-3">
                    {truckTypes.data.length === 0 ? (
                        <div className="rounded-md border bg-card p-12 text-center text-muted-foreground">
                            {filters.search === ''
                                ? 'No truck types yet. Create one to get started.'
                                : `No truck types matching "${filters.search}".`}
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead>Icon</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Sort Order</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {truckTypes.data.map((truckType) => (
                                    <TableRow key={truckType.id}>
                                        <TableCell className="font-medium">
                                            {truckType.name}
                                        </TableCell>
                                        <TableCell>
                                            <code className="text-sm text-muted-foreground">
                                                {truckType.slug}
                                            </code>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {truckType.icon ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground max-w-[200px] truncate">
                                            {truckType.description ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {truckType.is_active ? (
                                                <Badge className="bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-muted text-muted-foreground">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {truckType.sort_order ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(
                                                truckType.created_at,
                                            ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        toggleTruckType(
                                                            truckType.id,
                                                        )
                                                    }
                                                    title={
                                                        truckType.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'
                                                    }
                                                >
                                                    {truckType.is_active ? (
                                                        <ToggleRight className="h-4 w-4 text-emerald-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        deleteTruckType(
                                                            truckType,
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
                {truckTypes.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {truckTypes.links.map((link, i) => (
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