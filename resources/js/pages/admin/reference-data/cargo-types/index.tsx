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
    Package,
    Plus,
    Search,
    ToggleLeft,
    ToggleRight,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface CargoTypeItem {
    id: number;
    name: string;
    slug: string;
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
    cargoTypes: {
        data: CargoTypeItem[];
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

export default function AdminCargoTypes({ cargoTypes, filters }: Props) {
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
        name: '',
        is_active: true,
        sort_order: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/reference-data/cargo-types', {
            onSuccess: () => {
                form.reset();
                setShowForm(false);
            },
            preserveScroll: true,
        });
    };

    const toggleCargoType = (id: number) => {
        router.patch(
            `/admin/reference-data/cargo-types/${id}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const deleteCargoType = (cargoType: CargoTypeItem) => {
        if (confirm(`Delete cargo type "${cargoType.name}"?`)) {
            router.delete(
                `/admin/reference-data/cargo-types/${cargoType.id}`,
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const handleSearchChange = (value: string) => {
        router.get(
            '/admin/reference-data/cargo-types',
            value !== '' ? { search: value } : {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AdminLayout>
            <Head title="Cargo Types" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Package className="h-6 w-6" />
                            Cargo Types
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Manage cargo type reference data
                        </p>
                    </div>
                    <Button onClick={() => setShowForm(!showForm)} size="sm">
                        <Plus className="mr-1.5 h-4 w-4" />
                        New Cargo Type
                    </Button>
                </div>

                {/* Search */}
                <div className="relative max-w-sm">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search cargo types…"
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
                                    placeholder="e.g. Electronics"
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
                                    : 'Create Cargo Type'}
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

                {/* Cargo Types List */}
                <div className="space-y-3">
                    {cargoTypes.data.length === 0 ? (
                        <div className="rounded-md border bg-card p-12 text-center text-muted-foreground">
                            {filters.search === ''
                                ? 'No cargo types yet. Create one to get started.'
                                : `No cargo types matching "${filters.search}".`}
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Sort Order</TableHead>
                                    <TableHead>Created</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cargoTypes.data.map((cargoType) => (
                                    <TableRow key={cargoType.id}>
                                        <TableCell className="font-medium">
                                            {cargoType.name}
                                        </TableCell>
                                        <TableCell>
                                            <code className="text-sm text-muted-foreground">
                                                {cargoType.slug}
                                            </code>
                                        </TableCell>
                                        <TableCell>
                                            {cargoType.is_active ? (
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
                                            {cargoType.sort_order ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {new Date(
                                                cargoType.created_at,
                                            ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        toggleCargoType(
                                                            cargoType.id,
                                                        )
                                                    }
                                                    title={
                                                        cargoType.is_active
                                                            ? 'Deactivate'
                                                            : 'Activate'
                                                    }
                                                >
                                                    {cargoType.is_active ? (
                                                        <ToggleRight className="h-4 w-4 text-emerald-600" />
                                                    ) : (
                                                        <ToggleLeft className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        deleteCargoType(
                                                            cargoType,
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
                {cargoTypes.last_page > 1 && (
                    <div className="flex items-center justify-center gap-1">
                        {cargoTypes.links.map((link, i) => (
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