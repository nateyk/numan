import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Interest {
    id: number;
    status: string;
    freightPost?: { ulid: string; originLocation?: { name: string }; destinationLocation?: { name: string } };
    freight_post?: { ulid: string; origin_location?: { name: string }; destination_location?: { name: string } };
}

interface Props {
    interests: { data: Interest[] };
    sort: string;
}

export default function MyInterests({ interests, sort }: Props) {
    const filterBy = (status: string) => router.get('/my-interests', { sort }, { preserveState: true });

    return (
        <AppLayout>
            <Head title="My Interests" />
            <div className="mx-auto max-w-4xl space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">My Interests</h1>
                    <Select value={sort} onValueChange={(v) => router.get('/my-interests', { sort: v }, { preserveState: true })}>
                        <SelectTrigger className="w-48"><SelectValue placeholder="Sort" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="latest">Latest</SelectItem>
                            <SelectItem value="oldest">Oldest</SelectItem>
                            <SelectItem value="status">Status</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {interests.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">You have not expressed interest in any freight yet.</p>
                ) : (
                    <div className="space-y-2">
                        {interests.data.map((i) => {
                            const fp = (i as unknown as Record<string, unknown>).freightPost as Interest['freightPost'] ?? (i as unknown as Record<string, unknown>).freight_post as unknown as Interest['freightPost'];
                            const origin = (fp as unknown as Record<string, unknown>)?.originLocation as { name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.origin_location as { name: string } | undefined;
                            const destination = (fp as unknown as Record<string, unknown>)?.destinationLocation as { name: string } | undefined ?? (fp as unknown as Record<string, unknown>)?.destination_location as { name: string } | undefined;
                            return (
                                <div key={i.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <div className="font-medium">{origin?.name ?? '—'} → {destination?.name ?? '—'}</div>
                                        <div className="text-sm text-muted-foreground">{fp?.ulid ?? '—'}</div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge>{i.status}</Badge>
                                        <Button size="sm" variant="outline" onClick={() => router.post(`/interests/${i.id}/withdraw`)}>Withdraw</Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
