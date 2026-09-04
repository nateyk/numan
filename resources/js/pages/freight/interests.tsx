import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface Interest {
    id: number;
    status: string;
    message: string | null;
    user: { name: string };
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    bank_account_name: string | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_iban: string | null;
    bank_swift: string | null;
}

interface Props {
    interests: { data: Interest[] };
    post: { ulid: string };
}

export default function FreightInterests({ interests, post }: Props) {
    return (
        <AppLayout>
            <Head title="Interests" />
            <div className="mx-auto max-w-4xl space-y-4 p-6">
                <h1 className="text-xl font-bold">Interests for {post.ulid.slice(0, 8)}</h1>
                {interests.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No interests yet.</p>
                ) : (
                    <div className="space-y-2">
                        {interests.data.map((i) => (
                            <div key={i.id} className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <div className="font-medium">{i.user.name}</div>
                                    <div className="text-sm text-muted-foreground">{i.message ?? '—'}</div>
                                    {(i.status === 'shortlisted' || i.status === 'selected') && (
                                        <div className="mt-3 space-y-1 border-t pt-3">
                                            <div className="text-xs font-medium text-muted-foreground">Contact Details</div>
                                            <div className="text-sm">
                                                <div className="font-medium">{i.contact_name}</div>
                                                <div className="text-muted-foreground">{i.contact_phone} • {i.contact_email}</div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge>{i.status}</Badge>
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/interests/${i.id}/shortlist`)}>Shortlist</Button>
                                    <Button size="sm" onClick={() => router.post(`/interests/${i.id}/accept`)}>Accept</Button>
                                    <Button size="sm" variant="destructive" onClick={() => router.post(`/interests/${i.id}/reject`)}>Reject</Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
