import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LandingFooter, LandingHeader } from '@/components/landing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Truck, Package, Calendar, MapPin, Route, Clock, User, ShieldCheck, ArrowLeft, ArrowRight, Users, Eye, Weight, Banknote, Boxes } from 'lucide-react';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import type { SharedData } from '@/types';
import { useState } from 'react';

interface Props {
    post: {
        ulid: string;
        status: string;
        cargo_description: string | null;
        item_name?: string | null;
        actual_weight?: string | null;
        weight_unit?: string | null;
        tariff_amount?: string | null;
        tariff_currency?: string | null;
        insurance_required?: boolean | null;
        insurance_value?: string | null;
        depreciation_rate?: string | null;
        cargo_type?: { name: string } | null;
        truck_type?: { name: string } | null;
        truck_count?: number | null;
        schedule_type?: string | null;
        pickup_date: string | null;
        pickup_time?: string | null;
        pickup_date_flexibility_days?: number | null;
        origin_exact_lat: string | null;
        origin_exact_lng: string | null;
        destination_exact_lat: string | null;
        destination_exact_lng: string | null;
        originLocation?: { id: number; name: string; region_name: string } | null;
        destinationLocation?: { id: number; name: string; region_name: string } | null;
        origin_location?: { id: number; name: string; region_name: string } | null;
        destination_location?: { id: number; name: string; region_name: string } | null;
        routeSnapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
        route_snapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
        user?: { id: number; name: string; email: string; avatar_url?: string | null } | null;
        created_at?: string | null;
    };
    hasInterest?: boolean;
    interestStatus?: string | null;
    interestsCount?: number | null;
    canRegister?: boolean;
}

export default function FreightShow({ post, hasInterest, interestStatus, interestsCount, canRegister = true }: Props) {
    const { auth } = usePage<SharedData>().props;
    const [interestOpen, setInterestOpen] = useState(false);

    const originLocation =
        ((post as unknown as Record<string, unknown>).originLocation as { id: number; name: string; region_name: string } | null | undefined) ??
        ((post as unknown as Record<string, unknown>).origin_location as { id: number; name: string; region_name: string } | null | undefined);
    const destinationLocation =
        ((post as unknown as Record<string, unknown>).destinationLocation as { id: number; name: string; region_name: string } | null | undefined) ??
        ((post as unknown as Record<string, unknown>).destination_location as { id: number; name: string; region_name: string } | null | undefined);
    const cargoType = ((post as unknown as Record<string, unknown>).cargoType ?? (post as unknown as Record<string, unknown>).cargo_type) as { name: string } | null | undefined;
    const truckType = ((post as unknown as Record<string, unknown>).truckType ?? (post as unknown as Record<string, unknown>).truck_type) as { name: string } | null | undefined;
    const routeSnapshot = ((post as unknown as Record<string, unknown>).routeSnapshot ?? (post as unknown as Record<string, unknown>).route_snapshot) as {
        distance_meters: number;
        duration_seconds: number;
        geometry: unknown;
    } | null | undefined;

    const isOwnPost = auth?.user?.id === post.user?.id;

    const {
        data: interestData,
        setData: setInterestData,
        post: postInterest,
        processing: interestProcessing,
        errors: interestErrors,
        reset: resetInterest,
    } = useForm({
        message: '',
        contact_name: auth?.user?.name ?? '',
        contact_phone: '',
        contact_email: auth?.user?.email ?? '',
    });

    const handleExpressInterest = () => {
        if (!auth?.user) {
            router.visit('/login');
            return;
        }
        setInterestOpen(true);
    };

    const submitInterest = (e: React.FormEvent) => {
        e.preventDefault();
        postInterest(`/my-freight/${post.ulid}/interests`, {
            preserveScroll: true,
            onSuccess: () => {
                setInterestOpen(false);
                resetInterest();
            },
        });
    };

    return (
        <>
            <Head title={`${originLocation?.name ?? 'Freight'} → ${destinationLocation?.name ?? ''} — NumanFreight`} />
            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main>
                    <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-6">
                        <Button variant="ghost" size="sm" asChild className="-ml-2 h-8 gap-1.5 text-muted-foreground hover:text-foreground">
                            <Link href="/freight">
                                <ArrowLeft className="size-4" /> Back to Freight
                            </Link>
                        </Button>
                    </div>

                    <section className="py-6 pb-10">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="grid gap-6 lg:grid-cols-12 lg:items-start">
                                {/* Left — Route + Map */}
                                <div className="lg:col-span-7 space-y-4">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge className="gap-1.5 capitalize rounded-full px-2.5 py-1 text-xs">
                                            <span className="size-1.5 rounded-full bg-primary-foreground animate-pulse" />
                                            {post.status}
                                        </Badge>
                                        {cargoType?.name && (
                                            <Badge variant="secondary" className="rounded-full border bg-card px-2.5 py-1 text-xs font-medium">
                                                {cargoType.name}
                                            </Badge>
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            ULID {post.ulid.slice(0, 7).toUpperCase()} • {post.created_at ? new Date(post.created_at).toLocaleDateString() : ''}
                                        </span>
                                    </div>

                                    <div>
                                        <h1 className="flex items-center gap-3 text-3xl font-extrabold tracking-tight md:text-[34px]">
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                                                <MapPin className="size-5" />
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {originLocation?.name ?? 'Origin'}
                                                <span className="flex size-6 items-center justify-center rounded-full bg-muted">
                                                    <ArrowRight className="size-3.5 text-muted-foreground" />
                                                </span>
                                                {destinationLocation?.name ?? 'Destination'}
                                            </span>
                                        </h1>
                                        <p className="mt-2 flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                            <span className="font-medium tracking-wide uppercase text-xs">{originLocation?.region_name ?? ''}</span>
                                            <ArrowRight className="size-3 opacity-60" />
                                            <span className="font-medium tracking-wide uppercase text-xs">{destinationLocation?.region_name ?? ''}</span>
                                            <span className="mx-1 size-1 rounded-full bg-border" />
                                            <span className="inline-flex items-center gap-1">
                                                <Truck className="size-3.5" /> {truckType?.name ?? 'Any'} {post.truck_count ? `×${post.truck_count}` : ''}
                                            </span>
                                            <span className="mx-1 size-1 rounded-full bg-border" />
                                            <span>{cargoType?.name ?? 'General cargo'}</span>
                                        </p>
                                    </div>

                                    <div className="overflow-hidden rounded-xl border bg-card shadow-sm">
                                        <RoutePreviewMap
                                            fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null}
                                            fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null}
                                            toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null}
                                            toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null}
                                            geometry={routeSnapshot?.geometry}
                                            className="h-[380px] md:h-[420px] !rounded-none !border-0"
                                        />
                                        {routeSnapshot && (
                                            <div className="flex items-center gap-2 border-t bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
                                                <Route className="size-3.5" />
                                                <span className="font-medium text-foreground">{(routeSnapshot.distance_meters / 1000).toFixed(1)} km</span>
                                                <span className="size-1 rounded-full bg-border" />
                                                <span>{Math.round(routeSnapshot.duration_seconds / 60)} min</span>
                                                <span className="size-1 rounded-full bg-border" />
                                                <span>OSRM routed</span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Right — Poster + Details */}
                                <div className="lg:col-span-5 space-y-4 lg:sticky lg:top-6">
                                    <Card className="rounded-xl border shadow-sm gap-0 py-0 overflow-hidden">
                                        <CardHeader className="gap-0 space-y-0 border-b bg-card px-5 py-4">
                                            <div className="flex items-center gap-2">
                                                <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                                    <User className="size-4 text-primary" />
                                                </span>
                                                <CardTitle className="text-[15px] font-semibold">Consignor</CardTitle>
                                                <Badge variant="secondary" className="ml-auto gap-1 border-green-200 bg-green-50 text-green-700 text-[11px] font-medium">
                                                    <ShieldCheck className="size-3" /> Verified
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4 px-5 py-5">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-12 ring-1 ring-border">
                                                    <AvatarImage src={post.user?.avatar_url ?? undefined} />
                                                    <AvatarFallback className="bg-primary/10 font-semibold text-primary">
                                                        {post.user?.name?.slice(0, 2).toUpperCase() ?? '—'}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div className="min-w-0">
                                                    <p className="truncate font-semibold leading-none">{post.user?.name ?? 'Unknown consignor'}</p>
                                                    <p className="truncate text-sm text-muted-foreground">{post.user?.email ?? ''}</p>
                                                    <p className="mt-1 text-xs text-muted-foreground">NumanFreight consignor</p>
                                                </div>
                                            </div>

                                            <Separator />

                                            <div className="space-y-3 text-sm">
                                                <div className="flex gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                        <Package className="size-4 text-muted-foreground" />
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="text-xs font-medium text-muted-foreground">Cargo</p>
                                                        <p className="font-medium leading-tight">
                                                            {cargoType?.name ?? 'General Cargo'}
                                                            <span className="font-normal text-muted-foreground"> — {post.cargo_description ?? 'No description'}</span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                        <Truck className="size-4 text-muted-foreground" />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-medium text-muted-foreground">Trucks</p>
                                                        <p className="font-medium leading-tight">
                                                            {truckType?.name ?? 'Any truck'} {post.truck_count ? `×${post.truck_count}` : ''}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                        <Calendar className="size-4 text-muted-foreground" />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-medium text-muted-foreground">Pickup</p>
                                                        <p className="font-medium leading-tight">
                                                            {post.pickup_date ? String(post.pickup_date).slice(0, 10) : 'Flexible'}{' '}
                                                            {post.pickup_time ? String(post.pickup_time).slice(0, 5) : ''}
                                                            {post.pickup_date_flexibility_days ? (
                                                                <span className="ml-1 rounded bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground">
                                                                    ±{post.pickup_date_flexibility_days}d
                                                                </span>
                                                            ) : null}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex gap-3">
                                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                                        <Clock className="size-4 text-muted-foreground" />
                                                    </span>
                                                    <div>
                                                        <p className="text-xs font-medium text-muted-foreground">Schedule</p>
                                                        <p className="font-medium capitalize leading-tight">{post.schedule_type ?? 'one_time'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="pt-1 flex flex-col gap-2">
                                                {isOwnPost ? (
                                                    <>
                                                        <div className="flex items-center justify-between rounded-lg border bg-muted/40 px-3 py-3">
                                                            <span className="inline-flex items-center gap-2 text-sm font-medium">
                                                                <span className="flex size-7 items-center justify-center rounded-full bg-card border shadow-xs">
                                                                    <Users className="size-3.5" />
                                                                </span>
                                                                {interestsCount ?? 0} interested
                                                            </span>
                                                            <Button size="sm" className="h-8 rounded-lg" asChild>
                                                                <Link href={`/my-freight/${post.ulid}/interests`}>
                                                                    <Eye className="mr-1 size-3.5" /> View
                                                                </Link>
                                                            </Button>
                                                        </div>
                                                        <Button variant="outline" className="w-full rounded-lg" asChild>
                                                            <Link href={`/my-freight/${post.ulid}`}>Manage Post</Link>
                                                        </Button>
                                                    </>
                                                ) : hasInterest ? (
                                                    <>
                                                        <div className="rounded-lg border bg-amber-50 px-3 py-2.5 text-center text-sm font-medium text-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                                                            Interest {interestStatus ?? 'submitted'} — awaiting consignor
                                                        </div>
                                                        <Button variant="outline" className="w-full rounded-lg" asChild>
                                                            <Link href="/my-interests">
                                                                <Users className="mr-1.5 size-4" /> My Interests
                                                            </Link>
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <Dialog open={interestOpen} onOpenChange={setInterestOpen}>
                                                        <Button onClick={handleExpressInterest} className="h-11 w-full rounded-lg text-sm font-semibold shadow-sm">
                                                            Express Interest
                                                        </Button>
                                                        <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                                                            <DialogHeader>
                                                                <DialogTitle>
                                                                    Express Interest — {originLocation?.name ?? 'Freight'} → {destinationLocation?.name ?? ''}
                                                                </DialogTitle>
                                                                <DialogDescription>Provide your contact details for the consignor to contact you.</DialogDescription>
                                                            </DialogHeader>
                                                            <form onSubmit={submitInterest} className="space-y-4">
                                                                <div>
                                                                    <Label>Message (optional)</Label>
                                                                    <Textarea
                                                                        value={interestData.message}
                                                                        onChange={(e) => setInterestData('message', e.target.value)}
                                                                        placeholder="Why are you interested? e.g., Available truck, experience on this route"
                                                                        rows={2}
                                                                    />
                                                                </div>
                                                                <div>
                                                                    <Label>Contact Name *</Label>
                                                                    <Input
                                                                        value={interestData.contact_name}
                                                                        onChange={(e) => setInterestData('contact_name', e.target.value)}
                                                                        placeholder="Your full name"
                                                                    />
                                                                    {interestErrors.contact_name && (
                                                                        <p className="text-xs text-destructive">{interestErrors.contact_name}</p>
                                                                    )}
                                                                </div>
                                                                <div>
                                                                    <Label>Phone *</Label>
                                                                    <Input
                                                                        id="phone"
                                                                        value={interestData.contact_phone}
                                                                        onChange={(e) => setInterestData('contact_phone', e.target.value)}
                                                                        placeholder="+251 91 123 4567"
                                                                        inputMode="tel"
                                                                        autoComplete="tel"
                                                                    />
                                                                    {interestErrors.contact_phone && (
                                                                        <p className="text-xs text-destructive">{interestErrors.contact_phone}</p>
                                                                    )}
                                                                </div>
                                                                <div>
                                                                    <Label>Email *</Label>
                                                                    <Input
                                                                        type="email"
                                                                        value={interestData.contact_email}
                                                                        onChange={(e) => setInterestData('contact_email', e.target.value)}
                                                                        placeholder="you@example.com"
                                                                    />
                                                                    {interestErrors.contact_email && (
                                                                        <p className="text-xs text-destructive">{interestErrors.contact_email}</p>
                                                                    )}
                                                                </div>
                                                                <DialogFooter>
                                                                    <Button type="button" variant="outline" onClick={() => setInterestOpen(false)}>
                                                                        Cancel
                                                                    </Button>
                                                                    <Button type="submit" disabled={interestProcessing}>
                                                                        Submit Interest
                                                                    </Button>
                                                                </DialogFooter>
                                                            </form>
                                                        </DialogContent>
                                                    </Dialog>
                                                )}
                                                <Button variant="ghost" size="sm" asChild className="rounded-lg text-muted-foreground">
                                                    <Link href="/freight">Browse More Freight</Link>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card className="rounded-xl border shadow-sm gap-0 py-0 overflow-hidden">
                                        <CardHeader className="px-5 py-4">
                                            <CardTitle className="text-[15px] font-semibold">Freight Details</CardTitle>
                                            <CardDescription className="text-xs">Complete shipment specification</CardDescription>
                                        </CardHeader>
                                        <CardContent className="px-5 pb-4 pt-0">
                                            <div className="divide-y text-sm">
                                                <div className="flex gap-4 py-3">
                                                    <span className="flex w-24 shrink-0 items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <MapPin className="size-3" /> Origin
                                                    </span>
                                                    <span className="ml-auto text-right font-medium">
                                                        {originLocation?.name ?? '—'}
                                                        {post.origin_exact_lat && (
                                                            <span className="ml-1 font-mono text-xs font-normal text-muted-foreground">
                                                                ({Number(post.origin_exact_lat).toFixed(4)}, {Number(post.origin_exact_lng).toFixed(4)})
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex gap-4 py-3">
                                                    <span className="flex w-24 shrink-0 items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <MapPin className="size-3" /> Destination
                                                    </span>
                                                    <span className="ml-auto text-right font-medium">
                                                        {destinationLocation?.name ?? '—'}
                                                        {post.destination_exact_lat && (
                                                            <span className="ml-1 font-mono text-xs font-normal text-muted-foreground">
                                                                ({Number(post.destination_exact_lat).toFixed(4)}, {Number(post.destination_exact_lng).toFixed(4)})
                                                            </span>
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between py-3">
                                                    <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <Boxes className="size-3" /> Item
                                                    </span>
                                                    <span className="font-medium">{(post as unknown as { item_name?: string }).item_name ?? '—'}</span>
                                                </div>
                                                <div className="flex items-center justify-between py-3">
                                                    <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <Weight className="size-3" /> Weight
                                                    </span>
                                                    <span className="font-medium">{post.actual_weight ? `${post.actual_weight} ${post.weight_unit ?? 'ton'}` : '—'}</span>
                                                </div>
                                                <div className="flex items-center justify-between py-3">
                                                    <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <Banknote className="size-3" /> Tariff
                                                    </span>
                                                    <span className="font-semibold">{post.tariff_amount ? `${post.tariff_amount} ${post.tariff_currency ?? 'ETB'}` : '—'}</span>
                                                </div>
                                                <div className="flex items-center justify-between gap-4 py-3">
                                                    <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <Package className="size-3" /> Cargo
                                                    </span>
                                                    <span className="max-w-[170px] truncate text-right font-medium">{post.cargo_description ?? '—'}</span>
                                                </div>
                                                <div className="flex items-center justify-between py-3">
                                                    <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                                        <Truck className="size-3" /> Truck
                                                    </span>
                                                    <span className="font-medium">
                                                        {truckType?.name ?? '—'} {post.truck_count ? `×${post.truck_count}` : ''}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between py-3">
                                                    <span className="text-xs font-medium uppercase tracking-wider text-muted-foreground">Status</span>
                                                    <Badge variant="outline" className="capitalize rounded-full border px-2.5 py-0.5 text-xs">
                                                        {post.status}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
                <LandingFooter />
            </div>
        </>
    );
}
