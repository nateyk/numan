import FreightWizardShell from '@/components/freight/FreightWizardShell';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { freightRoute } from '@/lib/freight-route';
import { Head, Link, useForm } from '@inertiajs/react';
import { Calendar, Check, Package, Route, Truck, X } from 'lucide-react';
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
        origin?: { name: string; region_name: string };
        destination?: { name: string; region_name: string };
        origin_location?: { name: string; region_name: string };
        destination_location?: { name: string; region_name: string };
        cargo_type?: { name: string };
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
    requirements: {
        key: string;
        label: string;
        met: boolean;
    }[];
}

export default function FreightReview({
    post,
    completionScore,
    requirements,
}: Props) {
    const [confirmed, setConfirmed] = useState(false);
    const form = useForm<{ confirm?: string }>({});
    // A bounced submit lands here through Inertia's error bag. Name every
    // reason out loud; the old page swallowed all of them.
    const submitErrors = Object.values(form.errors).filter(
        (message): message is string => Boolean(message),
    );

    const { origin, destination, cargoType, truckType } = freightRoute(post);
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
        // Success redirects to My Freight, where the confirmation banner
        // picks it up; a bounce stays on this page and renders above.
        form.post(`/my-freight/${post.ulid}/submit`);
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
                            <div>
                                <h4 className="text-sm font-medium">
                                    Before you submit
                                </h4>
                                <ul className="mt-2 space-y-1.5">
                                    {requirements.map((requirement) => (
                                        <li
                                            key={requirement.key}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            {requirement.met ? (
                                                <>
                                                    <Check
                                                        className="mt-0.5 size-4 shrink-0 text-green-600 dark:text-green-400"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="text-muted-foreground">
                                                        {requirement.label}
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <X
                                                        className="mt-0.5 size-4 shrink-0 text-destructive"
                                                        aria-hidden="true"
                                                    />
                                                    <span>
                                                        {requirement.label}
                                                        <span className="text-muted-foreground">
                                                            {' '}
                                                            — still needed
                                                        </span>
                                                    </span>
                                                </>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                                {completionScore < 60 && (
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        A load needs at least 60% complete
                                        before it can go to review.
                                    </p>
                                )}
                            </div>
                            <form
                                onSubmit={submit}
                                className="space-y-3 border-t pt-4"
                            >
                                {submitErrors.length > 0 && (
                                    <div
                                        role="alert"
                                        className="rounded-md border border-destructive/50 bg-destructive/5 p-3 text-sm"
                                    >
                                        <p className="font-medium text-destructive">
                                            This load can't be submitted yet
                                        </p>
                                        <ul className="mt-1 list-disc space-y-0.5 pl-4 text-muted-foreground">
                                            {submitErrors.map((message) => (
                                                <li key={message}>{message}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
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
        </AppLayout>
    );
}
