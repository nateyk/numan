import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import FreightWizardShell from '@/components/freight/FreightWizardShell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, Repeat } from 'lucide-react';

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
                <form onSubmit={submit}>
                    <div className="overflow-hidden rounded-lg border">
                        <div className="grid gap-0 md:grid-cols-2">
                            <div className="space-y-4 p-6">
                                <div>
                                    <Label>Pickup Date *</Label>
                                    <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} className="mt-1" />
                                    {form.errors.pickup_date && <p className="text-xs text-destructive mt-1">{form.errors.pickup_date}</p>}
                                </div>
                                <div>
                                    <Label>Pickup Time</Label>
                                    <Input type="time" value={form.data.pickup_time} onChange={(e) => form.setData('pickup_time', e.target.value)} className="mt-1" />
                                </div>
                                <div>
                                    <Label>Flexibility (± days)</Label>
                                    <Input type="number" value={form.data.pickup_date_flexibility_days} onChange={(e) => form.setData('pickup_date_flexibility_days', Number(e.target.value))} min={0} max={30} className="mt-1 w-32" />
                                </div>
                            </div>
                            <div className="border-t p-6 md:border-l md:border-t-0">
                                <Label>Schedule Type</Label>
                                <div className="mt-2 grid gap-3">
                                    {[
                                        { value: 'one_time', title: 'Direct Offer', desc: 'One-time freight, first suitable transporter gets it.' },
                                        { value: 'recurring', title: 'Tender', desc: 'Recurring need, collect multiple offers.' },
                                    ].map((o) => (
                                        <button key={o.value} type="button" onClick={() => form.setData('schedule_type', o.value)} className={`rounded-lg border p-4 text-left ${form.data.schedule_type === o.value ? 'border-primary bg-primary/5' : ''}`}>
                                            <div className="text-sm font-medium">{o.title}</div>
                                            <div className="text-xs text-muted-foreground">{o.desc}</div>
                                        </button>
                                    ))}
                                </div>
                                <div className="mt-6 flex justify-end">
                                    <Button type="submit" disabled={form.processing}>Continue →</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
