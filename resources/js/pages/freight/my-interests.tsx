import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface Interest {
    id: number;
    status: string;
    freightPost: { ulid: string; originLocation?: { name: string }; destinationLocation?: { name: string } };
}

interface Props {
    interests: { data: Interest[] };
}

export default function MyInterests({ interests }: Props) {
    return (
        <AppLayout>
            <Head title="My Interests" />
            <div className="mx-auto max-w-4xl space-y-4 p-6">
                <h1 className="text-xl font-bold">My Interests</h1>
                {interests.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">You have not expressed interest in any freight yet.</p>
                ) : (
                    <div className="space-y-2">
                        {interests.data.map((i) => (
                            <div key={i.id} className="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <div className="font-medium">{i.freightPost.originLocation?.name ?? '—'} → {i.freightPost.destinationLocation?.name ?? '—'}</div>
                                    <div className="text-sm text-muted-foreground">{i.freightPost.ulid}</div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge>{i.status}</Badge>
                                    <Button size="sm" variant="outline" onClick={() => router.post(`/interests/${i.id}/withdraw`)}>Withdraw</Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
