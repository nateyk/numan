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
                <form onSubmit={submit} className="space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base"><Calendar className="size-4" /> Pickup Schedule</CardTitle>
                                <CardDescription>When should the cargo be ready?</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <Label>Pickup Date *</Label>
                                        <Input type="date" value={form.data.pickup_date} onChange={(e) => form.setData('pickup_date', e.target.value)} className="mt-1" />
                                        {form.errors.pickup_date && <p className="text-xs text-destructive mt-1">{form.errors.pickup_date}</p>}
                                    </div>
                                    <div>
                                        <Label>Pickup Time</Label>
                                        <Input type="time" value={form.data.pickup_time} onChange={(e) => form.setData('pickup_time', e.target.value)} className="mt-1" />
                                    </div>
                                </div>
                                <div>
                                    <Label className="flex items-center gap-2"><Clock className="size-3.5" /> Flexibility (± days)</Label>
                                    <Input type="number" value={form.data.pickup_date_flexibility_days} onChange={(e) => form.setData('pickup_date_flexibility_days', Number(e.target.value))} min={0} max={30} className="mt-1 w-32" />
                                    <p className="text-xs text-muted-foreground mt-1">How many days flexible around pickup date.</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="flex items-center gap-2 text-base"><Repeat className="size-4" /> Schedule Type</CardTitle>
                                <CardDescription>One-time or recurring tender?</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-3">
                                    {[
                                        { value: 'one_time', title: 'Direct Offer', desc: 'One-time freight, first suitable transporter gets it.', icon: Calendar },
                                        { value: 'recurring', title: 'Tender', desc: 'Recurring need, collect multiple offers before choosing.', icon: Repeat },
                                    ].map((o) => {
                                        const Icon = o.icon;
                                        return (
                                            <button key={o.value} type="button" onClick={() => form.setData('schedule_type', o.value)} className={`flex items-start gap-3 rounded-lg border p-4 text-left transition-colors ${form.data.schedule_type === o.value ? 'border-primary bg-primary/5 shadow-sm' : 'border-input hover:bg-muted/50'}`}>
                                                <Icon className={`mt-0.5 size-4 ${form.data.schedule_type === o.value ? 'text-primary' : 'text-muted-foreground'}`} />
                                                <div>
                                                    <div className="font-medium text-sm">{o.title}</div>
                                                    <div className="text-xs text-muted-foreground">{o.desc}</div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={form.processing} size="lg">Continue →</Button>
                    </div>
                </form>
            </FreightWizardShell>
        </AppLayout>
    );
}
