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
    post: { ulid: string; cargo_type_id: number | null; cargo_description: string | null; required_truck_type_id: number | null; truck_count: number | null };
    cargoTypes: Array<{ id: number; name: string; slug: string }>;
    truckTypes: Array<{ id: number; name: string; slug: string }>;
}

export default function FreightCargo({ post, cargoTypes, truckTypes }: Props) {
    const form = useForm({
        cargo_type_id: post.cargo_type_id ? String(post.cargo_type_id) : '',
        cargo_description: post.cargo_description ?? '',
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
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base"><Package className="size-4" /> Cargo Details</CardTitle>
                                <CardDescription>What are you moving? Be precise for better matches.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
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
                                    <Label>Cargo Description *</Label>
                                    <Textarea value={form.data.cargo_description} onChange={(e) => form.setData('cargo_description', e.target.value)} placeholder="e.g., 20 tons teff in 40kg bags, palletized, fragile" rows={4} className="mt-1" />
                                    <p className="text-xs text-muted-foreground mt-1">Detailed description helps transporters prepare.</p>
                                    {form.errors.cargo_description && <p className="text-xs text-destructive">{form.errors.cargo_description}</p>}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base"><Truck className="size-4" /> Truck Requirements</CardTitle>
                                <CardDescription>Specify truck type and count for this load.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
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
                            </CardContent>
                        </Card>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="submit" disabled={form.processing} size="lg">Continue →</Button>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
