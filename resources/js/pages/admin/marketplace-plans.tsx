import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Edit2,
    Eye,
    Package,
    Plus,
    RotateCcw,
    SlidersHorizontal,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

interface PlanItem {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price_weekly_etb: string;
    price_monthly_etb: string;
    price_yearly_etb: string;
    is_active: boolean;
    is_popular: boolean;
    sort_order: number;
    limits: Record<string, number> | null;
    features: string[] | null;
    faq: Array<{ q: string; a: string }> | null;
    deleted_at: string | null;
    subscription_count: number;
}

interface FreeLimits {
    free_active_posts: number;
    free_interests_per_week: number;
    free_contact_reveals: number;
}

interface Props {
    plans: PlanItem[];
    freeLimits: FreeLimits;
}

const DEFAULT_PLAN_LIMITS = {
    active_freight_posts: -1,
    interests_per_week: -1,
    contact_reveals: -1,
} as Record<string, number>;

export default function AdminMarketplacePlans({ plans, freeLimits }: Props) {
    const [editing, setEditing] = useState<PlanItem | null>(null);
    const [showForm, setShowForm] = useState(false);

    const freeForm = useForm({
        free_active_posts: String(freeLimits.free_active_posts ?? 1),
        free_interests_per_week: String(
            freeLimits.free_interests_per_week ?? 3,
        ),
        free_contact_reveals: String(freeLimits.free_contact_reveals ?? 5),
    });

    const form = useForm({
        slug: '',
        name: '',
        description: '',
        price_weekly_etb: '0',
        price_monthly_etb: '0',
        price_yearly_etb: '0',
        is_active: true as boolean,
        is_popular: false as boolean,
        sort_order: 0,
        limits: { ...DEFAULT_PLAN_LIMITS } as Record<string, number>,
        features: '' as string, // comma separated for UI
        faq: '' as string, // JSON string for simplicity
    });

    const openCreate = () => {
        setEditing(null);
        form.setData({
            slug: '',
            name: '',
            description: '',
            price_weekly_etb: '0',
            price_monthly_etb: '0',
            price_yearly_etb: '0',
            is_active: true,
            is_popular: false,
            sort_order: plans.length + 1,
            limits: { ...DEFAULT_PLAN_LIMITS },
            features: '',
            faq: '',
        });
        form.clearErrors();
        setShowForm(true);
    };

    const openEdit = (plan: PlanItem) => {
        setEditing(plan);
        form.setData({
            slug: plan.slug,
            name: plan.name,
            description: plan.description ?? '',
            price_weekly_etb: String(plan.price_weekly_etb ?? '0'),
            price_monthly_etb: String(plan.price_monthly_etb),
            price_yearly_etb: String(plan.price_yearly_etb),
            is_active: plan.is_active,
            is_popular: plan.is_popular,
            sort_order: plan.sort_order,
            limits: plan.limits ?? { ...DEFAULT_PLAN_LIMITS },
            features: (plan.features ?? []).join(', '),
            faq: plan.faq ? JSON.stringify(plan.faq, null, 2) : '',
        });
        form.clearErrors();
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const payload: Record<string, unknown> = {
            slug: form.data.slug,
            name: form.data.name,
            description: form.data.description || null,
            price_weekly_etb: form.data.price_weekly_etb,
            price_monthly_etb: form.data.price_monthly_etb,
            price_yearly_etb: form.data.price_yearly_etb,
            is_active: form.data.is_active,
            is_popular: form.data.is_popular,
            sort_order: form.data.sort_order,
            limits: form.data.limits,
            features: form.data.features
                ? String(form.data.features)
                      .split(',')
                      .map((s) => s.trim())
                      .filter(Boolean)
                : [],
            faq: form.data.faq ? JSON.parse(String(form.data.faq)) : null,
        };

        if (editing) {
            router.put(
                `/admin/marketplace-plans/${editing.slug}`,
                payload as never,
                {
                    onSuccess: () => closeForm(),
                    preserveScroll: true,
                },
            );
        } else {
            router.post('/admin/marketplace-plans', payload as never, {
                onSuccess: () => closeForm(),
                preserveScroll: true,
            });
        }
    };

    /**
     * Archive (toggle is_active) rather than delete. Deleting a plan that
     * subscribers still reference would strip their entitlements, so the
     * server refuses it; the toggle is the supported action.
     */
    const toggleActive = (plan: PlanItem) => {
        router.patch(
            `/admin/marketplace-plans/${plan.slug}/toggle`,
            {},
            { preserveScroll: true },
        );
    };

    const destroy = (plan: PlanItem) => {
        const warning = plan.subscription_count
            ? `"${plan.name}" has ${plan.subscription_count} subscription(s). The server will refuse to delete it — archive it instead.`
            : `Delete plan "${plan.name}"?`;

        if (confirm(warning)) {
            router.delete(`/admin/marketplace-plans/${plan.slug}`, {
                preserveScroll: true,
            });
        }
    };

    const submitFreeLimits = (e: React.FormEvent) => {
        e.preventDefault();
        freeForm.put('/admin/marketplace-settings', {
            preserveScroll: true,
            onSuccess: () => freeForm.clearErrors(),
        });
    };

    const restore = (id: number) => {
        router.post(
            `/admin/marketplace-plans/${id}/restore`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <AdminLayout>
            <Head title="Marketplace Plans" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight">
                            <Package className="h-6 w-6 text-primary" />
                            Marketplace Plans (ETB)
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Chapa ETB plans — manage limits, prices, features,
                            Q&A future.
                        </p>
                    </div>
                    <Button onClick={openCreate} size="sm">
                        <Plus className="mr-1.5 h-4 w-4" /> New Plan
                    </Button>
                </div>

                {showForm && (
                    <Card className="gap-0 py-0">
                        <CardHeader className="px-5 py-4">
                            <CardTitle className="text-base">
                                {editing ? 'Edit Plan' : 'Create Plan'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 px-5 pb-5">
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="slug">Slug *</Label>
                                        <Input
                                            id="slug"
                                            value={form.data.slug}
                                            onChange={(e) =>
                                                form.setData(
                                                    'slug',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="pro"
                                        />
                                        {form.errors.slug && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.slug}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="name">Name *</Label>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(e) =>
                                                form.setData(
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Pro"
                                        />
                                        {form.errors.name && (
                                            <p className="text-xs text-destructive">
                                                {form.errors.name}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="For growing teams"
                                    />
                                </div>
                                <div className="grid gap-4 md:grid-cols-4">
                                    <div className="space-y-1.5">
                                        <Label>Weekly ETB</Label>
                                        <Input
                                            type="number"
                                            value={form.data.price_weekly_etb}
                                            onChange={(e) =>
                                                form.setData(
                                                    'price_weekly_etb',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="349"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Monthly ETB *</Label>
                                        <Input
                                            type="number"
                                            value={form.data.price_monthly_etb}
                                            onChange={(e) =>
                                                form.setData(
                                                    'price_monthly_etb',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Yearly ETB *</Label>
                                        <Input
                                            type="number"
                                            value={form.data.price_yearly_etb}
                                            onChange={(e) =>
                                                form.setData(
                                                    'price_yearly_etb',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Sort Order</Label>
                                        <Input
                                            type="number"
                                            value={String(form.data.sort_order)}
                                            onChange={(e) =>
                                                form.setData(
                                                    'sort_order',
                                                    parseInt(
                                                        e.target.value || '0',
                                                        10,
                                                    ),
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-1.5">
                                        <Label>
                                            Live Freight Posts (-1 unlimited)
                                        </Label>
                                        <Input
                                            type="number"
                                            value={String(
                                                form.data.limits
                                                    .active_freight_posts ?? -1,
                                            )}
                                            onChange={(e) =>
                                                form.setData('limits', {
                                                    ...form.data.limits,
                                                    active_freight_posts:
                                                        parseInt(
                                                            e.target.value ||
                                                                '-1',
                                                            10,
                                                        ),
                                                })
                                            }
                                        />
                                        {form.errors[
                                            'limits.active_freight_posts'
                                        ] && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    form.errors[
                                                        'limits.active_freight_posts'
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Interests / week (-1 unlimited)
                                        </Label>
                                        <Input
                                            type="number"
                                            value={String(
                                                form.data.limits
                                                    .interests_per_week ?? -1,
                                            )}
                                            onChange={(e) =>
                                                form.setData('limits', {
                                                    ...form.data.limits,
                                                    interests_per_week:
                                                        parseInt(
                                                            e.target.value ||
                                                                '-1',
                                                            10,
                                                        ),
                                                })
                                            }
                                        />
                                        {form.errors[
                                            'limits.interests_per_week'
                                        ] && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    form.errors[
                                                        'limits.interests_per_week'
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>
                                            Contact Reveals (-1 unlimited)
                                        </Label>
                                        <Input
                                            type="number"
                                            value={String(
                                                form.data.limits
                                                    .contact_reveals ?? -1,
                                            )}
                                            onChange={(e) =>
                                                form.setData('limits', {
                                                    ...form.data.limits,
                                                    contact_reveals: parseInt(
                                                        e.target.value || '-1',
                                                        10,
                                                    ),
                                                })
                                            }
                                        />
                                        {form.errors[
                                            'limits.contact_reveals'
                                        ] && (
                                            <p className="text-xs text-destructive">
                                                {
                                                    form.errors[
                                                        'limits.contact_reveals'
                                                    ]
                                                }
                                            </p>
                                        )}
                                    </div>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>Features (comma separated)</Label>
                                    <Input
                                        value={form.data.features}
                                        onChange={(e) =>
                                            form.setData(
                                                'features',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Unlimited, Priority Support"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>FAQ JSON (optional)</Label>
                                    <textarea
                                        value={form.data.faq}
                                        onChange={(e) =>
                                            form.setData('faq', e.target.value)
                                        }
                                        rows={3}
                                        className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder='[{"q":"...","a":"..."}]'
                                    />
                                </div>
                                <div className="flex items-center gap-4">
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
                                        />{' '}
                                        Active
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.is_popular}
                                            onChange={(e) =>
                                                form.setData(
                                                    'is_popular',
                                                    e.target.checked,
                                                )
                                            }
                                        />{' '}
                                        Popular
                                    </label>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={closeForm}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {editing ? 'Save' : 'Create'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card className="gap-0 py-0">
                    <CardHeader className="px-5 py-4">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <SlidersHorizontal className="h-4 w-4 text-primary" />
                            Free plan limits
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Applies to workspaces with no paid subscription. -1
                            means unlimited. Changes take effect immediately.
                        </p>
                    </CardHeader>
                    <CardContent className="px-5 pb-5">
                        <form
                            onSubmit={submitFreeLimits}
                            className="grid gap-4 md:grid-cols-3"
                        >
                            <div className="space-y-1.5">
                                <Label htmlFor="free_active_posts">
                                    Active freight posts
                                </Label>
                                <Input
                                    id="free_active_posts"
                                    type="number"
                                    value={freeForm.data.free_active_posts}
                                    onChange={(e) =>
                                        freeForm.setData(
                                            'free_active_posts',
                                            e.target.value,
                                        )
                                    }
                                />
                                {freeForm.errors.free_active_posts && (
                                    <p className="text-xs text-destructive">
                                        {freeForm.errors.free_active_posts}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="free_interests_per_week">
                                    Interests per week
                                </Label>
                                <Input
                                    id="free_interests_per_week"
                                    type="number"
                                    value={
                                        freeForm.data.free_interests_per_week
                                    }
                                    onChange={(e) =>
                                        freeForm.setData(
                                            'free_interests_per_week',
                                            e.target.value,
                                        )
                                    }
                                />
                                {freeForm.errors.free_interests_per_week && (
                                    <p className="text-xs text-destructive">
                                        {
                                            freeForm.errors
                                                .free_interests_per_week
                                        }
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="free_contact_reveals">
                                    Contact reveals (one-time free allowance)
                                </Label>
                                <Input
                                    id="free_contact_reveals"
                                    type="number"
                                    value={freeForm.data.free_contact_reveals}
                                    onChange={(e) =>
                                        freeForm.setData(
                                            'free_contact_reveals',
                                            e.target.value,
                                        )
                                    }
                                />
                                {freeForm.errors.free_contact_reveals && (
                                    <p className="text-xs text-destructive">
                                        {freeForm.errors.free_contact_reveals}
                                    </p>
                                )}
                            </div>
                            <div className="md:col-span-3">
                                <Button
                                    type="submit"
                                    disabled={freeForm.processing}
                                >
                                    {freeForm.processing
                                        ? 'Saving...'
                                        : 'Save free limits'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {plans.map((plan) => (
                        <div
                            key={plan.id}
                            className={`flex items-center gap-4 rounded-md border bg-card p-4 ${plan.deleted_at ? 'opacity-60' : 'hover:bg-muted/30'}`}
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <span className="font-semibold">
                                        {plan.name}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="font-mono text-[10px]"
                                    >
                                        {plan.slug}
                                    </Badge>
                                    {plan.is_popular && (
                                        <Badge className="text-[10px]">
                                            Popular
                                        </Badge>
                                    )}
                                    {!plan.is_active && (
                                        <Badge
                                            variant="secondary"
                                            className="text-[10px]"
                                        >
                                            Inactive
                                        </Badge>
                                    )}
                                    {plan.deleted_at && (
                                        <Badge
                                            variant="destructive"
                                            className="text-[10px]"
                                        >
                                            Archived
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {plan.description}
                                </p>
                                <div className="mt-1 flex flex-wrap gap-2 text-xs">
                                    {Number(plan.price_weekly_etb) > 0 && (
                                        <span>
                                            {Number(
                                                plan.price_weekly_etb,
                                            ).toLocaleString()}{' '}
                                            ETB / wk
                                        </span>
                                    )}
                                    <span>
                                        {Number(
                                            plan.price_monthly_etb,
                                        ).toLocaleString()}{' '}
                                        ETB / mo
                                    </span>
                                    <span className="text-muted-foreground">
                                        •{' '}
                                        {Number(
                                            plan.price_yearly_etb,
                                        ).toLocaleString()}{' '}
                                        ETB / yr
                                    </span>
                                    <span className="text-muted-foreground">
                                        • Live posts:{' '}
                                        {plan.limits?.active_freight_posts ??
                                            '-'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        • Interests/wk:{' '}
                                        {plan.limits?.interests_per_week ?? '-'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        • Reveals:{' '}
                                        {plan.limits?.contact_reveals ?? '-'}
                                    </span>
                                    <span className="text-muted-foreground">
                                        • {plan.subscription_count}{' '}
                                        subscription(s)
                                    </span>
                                </div>
                                {plan.features && (
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {plan.features.map((f, i) => (
                                            <Badge
                                                key={i}
                                                variant="secondary"
                                                className="text-[10px]"
                                            >
                                                {f}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </div>
                            <div className="flex shrink-0 gap-1">
                                {plan.deleted_at ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => restore(plan.id)}
                                    >
                                        <RotateCcw className="h-4 w-4" />{' '}
                                        Restore
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => openEdit(plan)}
                                        >
                                            <Edit2 className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => toggleActive(plan)}
                                            title={
                                                plan.is_active
                                                    ? 'Archive this plan'
                                                    : 'Offer this plan'
                                            }
                                        >
                                            <Eye
                                                className={
                                                    plan.is_active
                                                        ? 'h-4 w-4'
                                                        : 'h-4 w-4 opacity-40'
                                                }
                                            />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => destroy(plan)}
                                            className="text-destructive hover:text-destructive"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                    {plans.length === 0 && (
                        <div className="rounded-md border bg-card p-12 text-center text-muted-foreground">
                            No plans yet.
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
