import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Package, Truck } from 'lucide-react';

interface Props {
    post: {
        ulid: string;
        cargo_type_id: number | null;
        cargo_description: string | null;
        item_name: string | null;
        actual_weight: string | null;
        weight_unit: string | null;
        tariff_weight: string | null;
        tariff_weight_unit: string | null;
        tariff_amount: string | null;
        tariff_currency: string | null;
        required_truck_type_id: number | null;
        truck_count: number | null;
    };
    cargoTypes: Array<{ id: number; name: string; slug: string }>;
    truckTypes: Array<{ id: number; name: string; slug: string }>;
}

export default function FreightCargo({ post, cargoTypes, truckTypes }: Props) {
    const form = useForm({
        cargo_type_id: post.cargo_type_id ? String(post.cargo_type_id) : '',
        item_name: post.item_name ?? '',
        actual_weight: post.actual_weight ? String(post.actual_weight) : '',
        weight_unit: post.weight_unit ?? 'ton',
        tariff_weight: post.tariff_weight ? String(post.tariff_weight) : '',
        tariff_weight_unit: post.tariff_weight_unit ?? 'ton',
        tariff_amount: post.tariff_amount ? String(post.tariff_amount) : '',
        tariff_currency: post.tariff_currency ?? 'ETB',
        required_truck_type_id: post.required_truck_type_id ? String(post.required_truck_type_id) : '',
        truck_count: post.truck_count ?? 1,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/my-freight/${post.ulid}/cargo`);
    };

    return (
        <AppLayout>
            <Head title="Cargo & Truck" />
            <FreightWizardShell ulid={post.ulid} currentStep={1}>
                <form onSubmit={submit} className="space-y-4">
                    <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
                        <div className="grid gap-0 md:grid-cols-2">
                            <div className="space-y-4 p-6">
                                <div>
                                    <Label>Cargo Type *</Label>
                                    <Select value={form.data.cargo_type_id} onValueChange={(v) => form.setData('cargo_type_id', v)}>
                                        <SelectTrigger className="mt-1"><SelectValue placeholder="Select cargo type" /></SelectTrigger>
                                        <SelectContent>
                                            {cargoTypes.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.cargo_type_id && <p className="text-xs text-destructive mt-1">{form.errors.cargo_type_id}</p>}
                                </div>
                                <div>
                                    <Label>Item to be Loaded *</Label>
                                    <Textarea value={form.data.item_name} onChange={(e) => form.setData('item_name', e.target.value)} placeholder="e.g., 20 tons teff in 40kg bags, palletized, fragile" rows={3} className="mt-1" />
                                    {form.errors.item_name && <p className="text-xs text-destructive mt-1">{form.errors.item_name}</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label>Actual Weight</Label>
                                        <Input type="number" step="0.01" value={form.data.actual_weight} onChange={(e) => form.setData('actual_weight', e.target.value)} placeholder="e.g., 380" className="mt-1" />
                                        {form.errors.actual_weight && <p className="text-xs text-destructive mt-1">{form.errors.actual_weight}</p>}
                                    </div>
                                    <div>
                                        <Label>Unit</Label>
                                        <Select value={form.data.weight_unit} onValueChange={(v) => form.setData('weight_unit', v)}>
                                            <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="ton">Ton</SelectItem>
                                                <SelectItem value="quintal">Quintal</SelectItem>
                                                <SelectItem value="kg">Kg</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    <div>
                                        <Label>Tariff Weight</Label>
                                        <Input type="number" step="0.01" value={form.data.tariff_weight} onChange={(e) => form.setData('tariff_weight', e.target.value)} placeholder="e.g., 400" className="mt-1" />
                                        {form.errors.tariff_weight && <p className="text-xs text-destructive mt-1">{form.errors.tariff_weight}</p>}
                                    </div>
                                    <div>
                                        <Label>Unit</Label>
                                        <Select value={form.data.tariff_weight_unit} onValueChange={(v) => form.setData('tariff_weight_unit', v)}>
                                            <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="ton">Ton</SelectItem>
                                                <SelectItem value="quintal">Quintal</SelectItem>
                                                <SelectItem value="kg">Kg</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div>
                                    <Label>Tariff Rate</Label>
                                    <div className="mt-1 grid grid-cols-3 gap-2">
                                        <div className="col-span-2">
                                            <Input type="number" step="0.01" value={form.data.tariff_amount} onChange={(e) => form.setData('tariff_amount', e.target.value)} placeholder="e.g., 15000" />
                                            {form.errors.tariff_amount && <p className="text-xs text-destructive mt-1">{form.errors.tariff_amount}</p>}
                                        </div>
                                        <Select value={form.data.tariff_currency} onValueChange={(v) => form.setData('tariff_currency', v)}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="ETB">ETB</SelectItem>
                                                <SelectItem value="USD">USD</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-4 border-t p-6 md:border-l md:border-t-0">
                                <div>
                                    <Label>Required Truck Type *</Label>
                                    <Select value={form.data.required_truck_type_id} onValueChange={(v) => form.setData('required_truck_type_id', v)}>
                                        <SelectTrigger className="mt-1"><SelectValue placeholder="Select truck type" /></SelectTrigger>
                                        <SelectContent>
                                            {truckTypes.map((t) => (
                                                <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {form.errors.required_truck_type_id && <p className="text-xs text-destructive mt-1">{form.errors.required_truck_type_id}</p>}
                                </div>
                                <div>
                                    <Label>Truck Count</Label>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Button type="button" variant="outline" size="icon" onClick={() => form.setData('truck_count', Math.max(1, Number(form.data.truck_count) - 1))}>-</Button>
                                        <Input type="number" value={form.data.truck_count} onChange={(e) => form.setData('truck_count', Number(e.target.value))} className="w-24 text-center" min={1} max={50} />
                                        <Button type="button" variant="outline" size="icon" onClick={() => form.setData('truck_count', Math.min(50, Number(form.data.truck_count) + 1))}>+</Button>
                                        <span className="text-sm text-muted-foreground">trucks</span>
                                    </div>
                                    {form.errors.truck_count && <p className="text-xs text-destructive mt-1">{form.errors.truck_count}</p>}
                                </div>
                            </div>
                        </div>
                        <div className="border-t bg-muted/10 p-4 flex justify-end">
                            <Button type="submit" disabled={form.processing}>Continue →</Button>
                        </div>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
