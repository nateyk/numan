import { Head, Link, router, usePage } from '@inertiajs/react';
import { LandingFooter, LandingHeader } from '@/components/landing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Truck, Package, Calendar, MapPin, Route, Clock, User, ShieldCheck, ArrowLeft, ArrowRight } from 'lucide-react';
import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import type { SharedData } from '@/types';

interface Props {
    post: {
        ulid: string;
        status: string;
        cargo_description: string | null;
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
        originLocation?: { id: number; name: string; region_name: string; latitude?: string; longitude?: string } | null;
        destinationLocation?: { id: number; name: string; region_name: string; latitude?: string; longitude?: string } | null;
        cargoType?: { name: string } | null;
        truckType?: { name: string } | null;
        routeSnapshot?: { distance_meters: number; duration_seconds: number; geometry: unknown } | null;
        user?: { id: number; name: string; email: string; avatar_url?: string | null } | null;
        created_at?: string | null;
    };
    hasInterest?: boolean;
    interestStatus?: string | null;
    canRegister?: boolean;
}

export default function FreightShow({ post, hasInterest, interestStatus, canRegister = true }: Props) {
    const { auth } = usePage<SharedData>().props;

    const handleExpressInterest = () => {
        if (!auth?.user) {
            router.visit('/login');
            return;
        }
        router.post(`/my-freight/${post.ulid}/interests`, { message: '' }, { preserveScroll: true });
    };

    const isOwnPost = auth?.user?.id === post.user?.id;

    return (
        <>
            <Head title={`${post.originLocation?.name ?? 'Freight'} → ${post.destinationLocation?.name ?? ''} — NumanFreight`} />
            <div className="min-h-screen bg-background text-foreground">
                <LandingHeader canRegister={canRegister} />
                <main>
                    {/* Breadcrumb */}
                    <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-6">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/freight">
                                <ArrowLeft className="mr-2 size-4" /> Back to Freight
                            </Link>
                        </Button>
                    </div>

                    {/* Hero — Route + Map */}
                    <section className="py-6">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="grid gap-6 lg:grid-cols-12">
                                <div className="lg:col-span-7 space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Badge variant={post.status === 'active' ? 'default' : 'secondary'} className="capitalize">{post.status}</Badge>
                                        {post.cargoType?.name && <Badge variant="outline">{post.cargoType.name}</Badge>}
                                        <span className="text-xs text-muted-foreground">ULID {post.ulid.slice(0, 8)} • {post.created_at ? new Date(post.created_at).toLocaleDateString() : ''}</span>
                                    </div>
                                    <h1 className="text-3xl font-bold tracking-tight flex items-center gap-3">
                                        <MapPin className="size-7 text-primary" />
                                        {post.originLocation?.name ?? 'Origin'} <ArrowRight className="size-5 text-muted-foreground" /> {post.destinationLocation?.name ?? 'Destination'}
                                    </h1>
                                    <p className="text-muted-foreground">
                                        {post.originLocation?.region_name ?? ''} → {post.destinationLocation?.region_name ?? ''} • {post.truckType?.name ?? 'Any truck'} {post.truck_count ? `×${post.truck_count}` : ''} • {post.cargoType?.name ?? 'General cargo'}
                                    </p>
                                    <RoutePreviewMap
                                        fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null}
                                        fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null}
                                        toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null}
                                        toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null}
                                        geometry={post.routeSnapshot?.geometry}
                                        className="h-[380px]"
                                    />
                                    {post.routeSnapshot && (
                                        <p className="text-sm text-muted-foreground flex items-center gap-2">
                                            <Route className="size-4" /> {(post.routeSnapshot.distance_meters / 1000).toFixed(1)} km • {Math.round(post.routeSnapshot.duration_seconds / 60)} min • OSRM routed
                                        </p>
                                    )}
                                </div>

                                {/* Poster + Action Card — shadcn */}
                                <div className="lg:col-span-5 space-y-4">
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <User className="size-4" /> Consignor Poster
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="flex items-center gap-3">
                                                <Avatar className="size-12">
                                                    <AvatarImage src={post.user?.avatar_url ?? undefined} />
                                                    <AvatarFallback>{post.user?.name?.slice(0, 2).toUpperCase() ?? '—'}</AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <p className="font-semibold leading-none">{post.user?.name ?? 'Unknown consignor'}</p>
                                                    <p className="text-sm text-muted-foreground">{post.user?.email ?? ''}</p>
                                                    <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                                        <ShieldCheck className="size-3 text-green-600" /> Verified consignor • NumanFreight
                                                    </p>
                                                </div>
                                            </div>
                                            <Separator />
                                            <div className="space-y-2 text-sm">
                                                <p className="flex items-center gap-2"><Package className="size-4 text-muted-foreground" /> <span className="font-medium">{post.cargoType?.name ?? 'General Cargo'}</span> — {post.cargo_description ?? 'No description'}</p>
                                                <p className="flex items-center gap-2"><Truck className="size-4 text-muted-foreground" /> {post.truckType?.name ?? 'Any truck'} {post.truck_count ? `×${post.truck_count}` : ''}</p>
                                                <p className="flex items-center gap-2"><Calendar className="size-4 text-muted-foreground" /> Pickup: {post.pickup_date ? String(post.pickup_date).slice(0, 10) : 'Flexible'} {post.pickup_time ? String(post.pickup_time).slice(0,5) : ''} {post.pickup_date_flexibility_days ? `±${post.pickup_date_flexibility_days}d` : ''}</p>
                                                <p className="flex items-center gap-2"><Clock className="size-4 text-muted-foreground" /> Schedule: {post.schedule_type ?? 'One-time'}</p>
                                            </div>
                                            <div className="pt-2 flex flex-col gap-2">
                                                {isOwnPost ? (
                                                    <Button variant="outline" asChild>
                                                        <Link href={`/my-freight/${post.ulid}`}>Manage Your Post</Link>
                                                    </Button>
                                                ) : hasInterest ? (
                                                    <Badge variant="secondary" className="justify-center py-2">Interest {interestStatus ?? 'submitted'} — awaiting consignor</Badge>
                                                ) : (
                                                    <Button onClick={handleExpressInterest} className="w-full" size="lg">
                                                        Express Interest
                                                    </Button>
                                                )}
                                                <Button variant="ghost" asChild>
                                                    <Link href="/freight">Browse More Freight</Link>
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-3"><CardTitle className="text-base">Freight Details</CardTitle></CardHeader>
                                        <CardContent className="space-y-2 text-sm">
                                            <div className="flex justify-between"><span className="text-muted-foreground">Origin</span><span className="font-medium">{post.originLocation?.name ?? '—'} {post.origin_exact_lat ? `(${Number(post.origin_exact_lat).toFixed(4)}, ${Number(post.origin_exact_lng).toFixed(4)})` : ''}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Destination</span><span className="font-medium">{post.destinationLocation?.name ?? '—'} {post.destination_exact_lat ? `(${Number(post.destination_exact_lat).toFixed(4)}, ${Number(post.destination_exact_lng).toFixed(4)})` : ''}</span></div>
                                            <Separator />
                                            <div className="flex justify-between"><span className="text-muted-foreground">Cargo</span><span>{post.cargo_description ?? '—'}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Truck</span><span>{post.truckType?.name ?? '—'} {post.truck_count ? `×${post.truck_count}` : ''}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Status</span><Badge variant="outline" className="capitalize">{post.status}</Badge></div>
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
