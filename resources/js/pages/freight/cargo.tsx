import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    post: { ulid: string; cargo_type_id: number | null; cargo_description: string | null; required_truck_type_id: number | null; truck_count: number | null };
    cargoTypes: Array<{ id: number; name: string; slug: string }>;
    truckTypes: Array<{ id: number; name: string; slug: string }>;
}

export default function FreightCargo({ post, cargoTypes, truckTypes }: Props) {
    const form = useForm({
        cargo_type_id: post.cargo_type_id ?? '',
        cargo_description: post.cargo_description ?? '',
        required_truck_type_id: post.required_truck_type_id ?? '',
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
                    <div>
                        <Label>Cargo Type</Label>
                        <select value={form.data.cargo_type_id} onChange={(e) => form.setData('cargo_type_id', e.target.value ? Number(e.target.value) : '')} className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm">
                            <option value="">Select cargo type</option>
                            {cargoTypes.map((c) => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label>Cargo Description</Label>
                        <Input value={form.data.cargo_description} onChange={(e) => form.setData('cargo_description', e.target.value)} placeholder="Describe cargo" />
                        {form.errors.cargo_description && <p className="text-xs text-destructive">{form.errors.cargo_description}</p>}
                    </div>
                    <div>
                        <Label>Required Truck Type</Label>
                        <select value={form.data.required_truck_type_id} onChange={(e) => form.setData('required_truck_type_id', e.target.value ? Number(e.target.value) : '')} className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm">
                            <option value="">Select truck type</option>
                            {truckTypes.map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <Label>Truck Count</Label>
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" onClick={() => form.setData('truck_count', Math.max(1, Number(form.data.truck_count) - 1))}>-</Button>
                            <Input type="number" value={form.data.truck_count} onChange={(e) => form.setData('truck_count', Number(e.target.value))} className="w-20 text-center" min={1} max={50} />
                            <Button type="button" variant="outline" onClick={() => form.setData('truck_count', Math.min(50, Number(form.data.truck_count) + 1))}>+</Button>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>Continue →</Button>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
