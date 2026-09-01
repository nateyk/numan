import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    post: { ulid: string; pickup_date: string | null; pickup_time: string | null; pickup_date_flexibility_days: number | null; schedule_type: string | null };
}

export default function FreightSchedule({ post }: Props) {
    const form = useForm({
        pickup_date: post.pickup_date ? String(post.pickup_date).slice(0, 10) : '',
        pickup_time: post.pickup_time ?? '',
        pickup_date_flexibility_days: post.pickup_date_flexibility_days ?? 0,
        schedule_type: post.schedule_type ?? 'one_time',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(`/my-freight/${post.ulid}/schedule`);
    };

    return (
        <AppLayout>
            <Head title="Post Details" />
            <FreightWizardShell ulid={post.ulid} currentStep={2}>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label>Pickup Date</Label>
                            <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} />
                            {form.errors.pickup_date && <p className="text-xs text-destructive">{form.errors.pickup_date}</p>}
                        </div>
                        <div>
                            <Label>Pickup Time</Label>
                            <Input type="time" value={form.data.pickup_time} onChange={(e) => form.setData('pickup_time', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <Label>Flexibility (± days)</Label>
                        <Input type="number" value={form.data.pickup_date_flexibility_days} onChange={(e) => form.setData('pickup_date_flexibility_days', Number(e.target.value))} min={0} max={30} />
                    </div>
                    <div>
                        <Label>Schedule Type</Label>
                        <div className="mt-1 grid gap-2 md:grid-cols-2">
                            {[
                                { value: 'one_time', title: 'Direct Offer', desc: 'One-time freight, first suitable transporter.' },
                                { value: 'recurring', title: 'Tender', desc: 'Recurring need, collect multiple offers.' },
                            ].map((o) => (
                                <button key={o.value} type="button" onClick={() => form.setData('schedule_type', o.value)} className={`rounded-md border p-4 text-left ${form.data.schedule_type === o.value ? 'border-primary bg-primary/5' : 'border-input'}`}>
                                    <div className="font-medium">{o.title}</div>
                                    <div className="text-xs text-muted-foreground">{o.desc}</div>
                                </button>
                            ))}
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
