import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';

interface Props {
    posts: { data: Array<{ ulid: string; status: string; originLocation?: { name: string }; destinationLocation?: { name: string }; cargo_description: string | null; pickup_date: string | null; completion_score: number }>; links: Array<{ url: string | null; label: string; active: boolean }> };
}

export default function FreightIndex({ posts }: Props) {
    const createDraft = () => {
        router.post('/my-freight');
    };

    return (
        <AppLayout>
            <Head title="My Freight" />
            <div className="mx-auto max-w-5xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">My Freight</h1>
                    <Button onClick={createDraft}>Post Freight</Button>
                </div>
                {posts.data.length === 0 ? (
                    <div className="rounded-md border p-12 text-center text-muted-foreground">No freight yet. Create your first post.</div>
                ) : (
                    <div className="space-y-3">
                        {posts.data.map((p) => (
                            <Link key={p.ulid} href={`/my-freight/${p.ulid}/route`} className="block rounded-md border p-4 hover:bg-muted/50">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">{p.originLocation?.name ?? '—'} → {p.destinationLocation?.name ?? '—'}</span>
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs">{p.status}</span>
                                </div>
                                <p className="text-sm text-muted-foreground">{p.cargo_description ?? '—'} — {p.pickup_date ? String(p.pickup_date).slice(0, 10) : '—'} — {p.completion_score}%</p>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
