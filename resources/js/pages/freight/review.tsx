import FreightWizardShell from '@/components/freight/FreightWizardShell';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle,
    MapPin,
    Package,
    Route,
    Truck,
} from 'lucide-react';
import { useState } from 'react';

interface Props {
    post: {
        ulid: string;
        origin_location_id: number | null;
        destination_location_id: number | null;
        cargo_description: string | null;
        item_name: string | null;
        truck_count: number | null;
        pickup_date: string | null;
        origin_exact_lat: string | null;
        origin_exact_lng: string | null;
        destination_exact_lat: string | null;
        destination_exact_lng: string | null;
        originLocation?: { name: string; region_name: string };
        destinationLocation?: { name: string; region_name: string };
        origin_location?: { name: string; region_name: string };
        destination_location?: { name: string; region_name: string };
        cargoType?: { name: string };
        cargo_type?: { name: string };
        truckType?: { name: string };
        truck_type?: { name: string };
        routeSnapshot?: {
            distance_meters: number;
            duration_seconds: number;
            geometry: unknown;
        } | null;
        route_snapshot?: {
            distance_meters: number;
            duration_seconds: number;
            geometry: unknown;
        } | null;
    };
    completionScore: number;
}

export default function FreightReview({ post, completionScore }: Props) {
    const [confirmed, setConfirmed] = useState(false);
    const [showSuccessModal, setShowSuccessModal] = useState(false);
    const form = useForm<{ confirm?: string }>({});

    const origin =
        ((post as unknown as Record<string, unknown>).originLocation as
            { name: string; region_name: string } | undefined) ??
        ((post as unknown as Record<string, unknown>).origin_location as
            { name: string; region_name: string } | undefined);
    const destination =
        ((post as unknown as Record<string, unknown>).destinationLocation as
            { name: string; region_name: string } | undefined) ??
        ((post as unknown as Record<string, unknown>).destination_location as
            { name: string; region_name: string } | undefined);
    const cargoType =
        ((post as unknown as Record<string, unknown>).cargoType as
            { name: string } | undefined) ??
        ((post as unknown as Record<string, unknown>).cargo_type as
            { name: string } | undefined);
    const truckType =
        ((post as unknown as Record<string, unknown>).truckType as
            { name: string } | undefined) ??
        ((post as unknown as Record<string, unknown>).truck_type as
            { name: string } | undefined);
    const routeSnapshot =
        ((post as unknown as Record<string, unknown>).routeSnapshot as
            | {
                  distance_meters: number;
                  duration_seconds: number;
                  geometry: unknown;
              }
            | undefined) ??
        ((post as unknown as Record<string, unknown>).route_snapshot as
            | {
                  distance_meters: number;
                  duration_seconds: number;
                  geometry: unknown;
              }
            | undefined);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirmed) return;
        form.post(`/my-freight/${post.ulid}/submit`, {
            onSuccess: () => {
                setShowSuccessModal(true);
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Review & Submit" />
            <FreightWizardShell
                ulid={post.ulid}
                currentStep={3}
                completionScore={completionScore}
            >
                <div className="overflow-hidden rounded-lg border bg-card shadow-sm">
                    <div className="grid gap-0 lg:grid-cols-12">
                        <div className="lg:col-span-7">
                            <RoutePreviewMap
                                fromLat={
                                    post.origin_exact_lat
                                        ? Number(post.origin_exact_lat)
                                        : null
                                }
                                fromLng={
                                    post.origin_exact_lng
                                        ? Number(post.origin_exact_lng)
                                        : null
                                }
                                toLat={
                                    post.destination_exact_lat
                                        ? Number(post.destination_exact_lat)
                                        : null
                                }
                                toLng={
                                    post.destination_exact_lng
                                        ? Number(post.destination_exact_lng)
                                        : null
                                }
                                geometry={routeSnapshot?.geometry}
                                className="h-[360px] rounded-none border-0"
                            />
                        </div>
                        <div className="space-y-4 p-6 lg:col-span-5">
                            <div>
                                <h3 className="font-medium">Freight Summary</h3>
                                <div className="mt-2 space-y-1 text-sm">
                                    <p>
                                        {origin?.name ?? 'Origin'} →{' '}
                                        {destination?.name ?? 'Destination'}
                                    </p>
                                    <p className="text-muted-foreground">
                                        {origin?.region_name ?? ''} →{' '}
                                        {destination?.region_name ?? ''}
                                    </p>
                                    <p className="mt-2 flex items-center gap-2">
                                        <Package className="size-3.5" />{' '}
                                        {cargoType?.name ?? '—'} —{' '}
                                        {post.item_name ?? '—'}
                                    </p>
                                    <p className="flex items-center gap-2">
                                        <Truck className="size-3.5" />{' '}
                                        {truckType?.name ?? '—'} ×{' '}
                                        {post.truck_count ?? '—'}
                                    </p>
                                    <p className="flex items-center gap-2">
                                        <Calendar className="size-3.5" />{' '}
                                        {post.pickup_date
                                            ? String(post.pickup_date).slice(
                                                  0,
                                                  10,
                                              )
                                            : '—'}
                                    </p>
                                    {routeSnapshot && (
                                        <p className="flex items-center gap-2">
                                            <Route className="size-3.5" />{' '}
                                            {(
                                                routeSnapshot.distance_meters /
                                                1000
                                            ).toFixed(1)}{' '}
                                            km •{' '}
                                            {Math.round(
                                                routeSnapshot.duration_seconds /
                                                    60,
                                            )}{' '}
                                            min
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="flex gap-2 text-xs">
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={`/my-freight/${post.ulid}/route`}
                                    >
                                        Edit Route
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={`/my-freight/${post.ulid}/cargo`}
                                    >
                                        Edit Cargo
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="sm" asChild>
                                    <Link
                                        href={`/my-freight/${post.ulid}/schedule`}
                                    >
                                        Edit Schedule
                                    </Link>
                                </Button>
                            </div>
                            <div className="flex items-center justify-between border-t pt-4">
                                <span className="text-sm font-medium">
                                    Completion
                                </span>
                                <Badge variant="secondary">
                                    {completionScore}%
                                </Badge>
                            </div>
                            <div className="h-1.5 w-full rounded-full bg-muted">
                                <div
                                    className="h-1.5 rounded-full bg-primary"
                                    style={{ width: `${completionScore}%` }}
                                />
                            </div>
                            <form
                                onSubmit={submit}
                                className="space-y-3 border-t pt-4"
                            >
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={confirmed}
                                        onChange={(e) =>
                                            setConfirmed(e.target.checked)
                                        }
                                        className="rounded"
                                    />
                                    I confirm accurate and ready for review.
                                </label>
                                <div className="flex gap-2">
                                    <Button
                                        type="submit"
                                        disabled={!confirmed || form.processing}
                                        className="flex-1"
                                    >
                                        Submit for review
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => window.history.back()}
                                        className="flex-1"
                                    >
                                        Save draft
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </FreightWizardShell>

            {/* Success Modal */}
            <Dialog open={showSuccessModal} onOpenChange={setShowSuccessModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-950">
                                <CheckCircle className="size-6 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <DialogTitle className="text-lg">
                                    Freight Post Submitted!
                                </DialogTitle>
                                <DialogDescription>
                                    Your freight post has been submitted for
                                    review and is now visible to transporters.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <div className="space-y-3 rounded-lg bg-muted/50 p-4">
                        <div className="flex items-center gap-2 text-sm">
                            <MapPin className="size-4 text-muted-foreground" />
                            <span className="font-medium">
                                {origin?.name ?? 'Origin'}
                            </span>
                            <span className="text-muted-foreground">→</span>
                            <span className="font-medium">
                                {destination?.name ?? 'Destination'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 text-sm">
                            <Package className="size-4 text-muted-foreground" />
                            <span>{cargoType?.name ?? '—'}</span>
                        </div>
                        <div className="flex items-center gap-2 text-sm">
                            <Truck className="size-4 text-muted-foreground" />
                            <span>
                                {truckType?.name ?? '—'} ×{' '}
                                {post.truck_count ?? '—'}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 text-sm">
                            <Calendar className="size-4 text-muted-foreground" />
                            <span>
                                {post.pickup_date
                                    ? String(post.pickup_date).slice(0, 10)
                                    : '—'}
                            </span>
                        </div>
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setShowSuccessModal(false)}
                        >
                            Close
                        </Button>
                        <Button asChild>
                            <Link href={`/my-freight/${post.ulid}`}>
                                View Post
                            </Link>
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
