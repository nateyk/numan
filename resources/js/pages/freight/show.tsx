import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { LandingFooter, LandingHeader } from '@/components/landing';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Truck, Package, Calendar, MapPin, Route, Clock, User, ShieldCheck, ArrowLeft, ArrowRight, Users, Eye } from 'lucide-react';
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

    // Handle both camelCase and snake_case from backend (Eloquent toArray uses snake_case)
    const originLocation = (post as unknown as Record<string, unknown>).originLocation as { id: number; name: string; region_name: string } | null | undefined ?? (post as unknown as Record<string, unknown>).origin_location as { id: number; name: string; region_name: string } | null | undefined;
    const destinationLocation = (post as unknown as Record<string, unknown>).destinationLocation as { id: number; name: string; region_name: string } | null | undefined ?? (post as unknown as Record<string, unknown>).destination_location as { id: number; name: string; region_name: string } | null | undefined;
    const cargoType = ((post as unknown as Record<string, unknown>).cargoType ?? (post as unknown as Record<string, unknown>).cargo_type) as { name: string } | null | undefined;
    const truckType = ((post as unknown as Record<string, unknown>).truckType ?? (post as unknown as Record<string, unknown>).truck_type) as { name: string } | null | undefined;
    const routeSnapshot = ((post as unknown as Record<string, unknown>).routeSnapshot ?? (post as unknown as Record<string, unknown>).route_snapshot) as { distance_meters: number; duration_seconds: number; geometry: unknown } | null | undefined;

    const isOwnPost = auth?.user?.id === post.user?.id;

    const { data: interestData, setData: setInterestData, post: postInterest, processing: interestProcessing, errors: interestErrors, reset: resetInterest } = useForm({
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
                    {/* Breadcrumb */}
                    <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-6">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/freight">
                                <ArrowLeft className="mr-2 size-4" /> Back to Freight
                            </Link>
                        </Button>
                    </div>

                    {/* Header — route meta */}
                    <section className="py-6">
                        <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="space-y-1 mb-6">
                                <div className="flex items-center gap-2">
                                    <Badge variant={post.status === 'active' ? 'default' : 'secondary'} className="capitalize">{post.status}</Badge>
                                    {cargoType?.name && <Badge variant="outline">{cargoType.name}</Badge>}
                                    <span className="text-xs text-muted-foreground">ULID {post.ulid.slice(0, 8)} • {post.created_at ? new Date(post.created_at).toLocaleDateString() : ''}</span>
                                </div>
                                <h1 className="text-3xl font-bold tracking-tight flex items-center gap-3">
                                    <MapPin className="size-7 text-primary" />
                                    {originLocation?.name ?? 'Origin'} <ArrowRight className="size-5 text-muted-foreground" /> {destinationLocation?.name ?? 'Destination'}
                                </h1>
                                <p className="text-muted-foreground">
                                    {originLocation?.region_name ?? ''} → {destinationLocation?.region_name ?? ''} • {truckType?.name ?? 'Any truck'} {post.truck_count ? `×${post.truck_count}` : ''} • {cargoType?.name ?? 'General cargo'}
                                </p>
                            </div>
                            <div className="grid gap-6 lg:grid-cols-12 items-start">
                                <div className="lg:col-span-7 space-y-3">
                                    <div className="overflow-hidden rounded-lg border bg-card">
                                        <RoutePreviewMap
                                            fromLat={post.origin_exact_lat ? Number(post.origin_exact_lat) : null}
                                            fromLng={post.origin_exact_lng ? Number(post.origin_exact_lng) : null}
                                            toLat={post.destination_exact_lat ? Number(post.destination_exact_lat) : null}
                                            toLng={post.destination_exact_lng ? Number(post.destination_exact_lng) : null}
                                            geometry={routeSnapshot?.geometry}
                                            className="h-[420px] !rounded-none !border-0"
                                        />
                                    </div>
                                    {routeSnapshot && (
                                        <p className="text-sm text-muted-foreground flex items-center gap-2">
                                            <Route className="size-4" /> {(routeSnapshot.distance_meters / 1000).toFixed(1)} km • {Math.round(routeSnapshot.duration_seconds / 60)} min • OSRM routed
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
                                                <p className="flex items-center gap-2"><Package className="size-4 text-muted-foreground" /> <span className="font-medium">{cargoType?.name ?? 'General Cargo'}</span> — {post.cargo_description ?? 'No description'}</p>
                                                <p className="flex items-center gap-2"><Truck className="size-4 text-muted-foreground" /> {truckType?.name ?? 'Any truck'} {post.truck_count ? `×${post.truck_count}` : ''}</p>
                                                <p className="flex items-center gap-2"><Calendar className="size-4 text-muted-foreground" /> Pickup: {post.pickup_date ? String(post.pickup_date).slice(0, 10) : 'Flexible'} {post.pickup_time ? String(post.pickup_time).slice(0,5) : ''} {post.pickup_date_flexibility_days ? `±${post.pickup_date_flexibility_days}d` : ''}</p>
                                                <p className="flex items-center gap-2"><Clock className="size-4 text-muted-foreground" /> Schedule: {post.schedule_type ?? 'One-time'}</p>
                                            </div>
                                            <div className="pt-2 flex flex-col gap-2">
                                                {isOwnPost ? (
                                                    <>
                                                        <Card className="bg-muted/30">
                                                            <CardContent className="p-3 flex items-center justify-between">
                                                                <div className="flex items-center gap-2">
                                                                    <Users className="size-4 text-muted-foreground" />
                                                                    <span className="text-sm font-medium">{interestsCount ?? 0} interested transporter{interestsCount === 1 ? '' : 's'}</span>
                                                                </div>
                                                                <Button size="sm" asChild>
                                                                    <Link href={`/my-freight/${post.ulid}/interests`}><Eye className="mr-1 size-3" /> View Interests</Link>
                                                                </Button>
                                                            </CardContent>
                                                        </Card>
                                                        <Button variant="outline" asChild>
                                                            <Link href={`/my-freight/${post.ulid}`}>Manage Your Post</Link>
                                                        </Button>
                                                    </>
                                                ) : hasInterest ? (
                                                    <>
                                                        <Badge variant="secondary" className="justify-center py-2">Interest {interestStatus ?? 'submitted'} — awaiting consignor</Badge>
                                                        <Button variant="outline" asChild>
                                                            <Link href="/my-interests"><Users className="mr-1 size-4" /> My Interests</Link>
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <Dialog open={interestOpen} onOpenChange={setInterestOpen}>
                                                        <Button onClick={handleExpressInterest} className="w-full" size="lg">
                                                            Express Interest
                                                        </Button>
                                                        <DialogContent className="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                                                            <DialogHeader>
                                                                <DialogTitle>Express Interest — {originLocation?.name ?? 'Freight'} → {destinationLocation?.name ?? ''}</DialogTitle>
                                                                <DialogDescription>Provide your contact details for the consignor to contact you.</DialogDescription>
                                                            </DialogHeader>
                                                            <form onSubmit={submitInterest} className="space-y-4">
                                                                <div>
                                                                    <Label>Message (optional)</Label>
                                                                    <Textarea value={interestData.message} onChange={(e) => setInterestData('message', e.target.value)} placeholder="Why are you interested? e.g., Available truck, experience on this route" rows={2} />
                                                                </div>
                                                                <div>
                                                                    <Label>Contact Name *</Label>
                                                                    <Input value={interestData.contact_name} onChange={(e) => setInterestData('contact_name', e.target.value)} placeholder="Your full name" />
                                                                    {interestErrors.contact_name && <p className="text-xs text-destructive">{interestErrors.contact_name}</p>}
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
                                                                    {interestErrors.contact_phone && <p className="text-xs text-destructive">{interestErrors.contact_phone}</p>}
                                                                </div>
                                                                <div>
                                                                    <Label>Email *</Label>
                                                                    <Input type="email" value={interestData.contact_email} onChange={(e) => setInterestData('contact_email', e.target.value)} placeholder="you@example.com" />
                                                                    {interestErrors.contact_email && <p className="text-xs text-destructive">{interestErrors.contact_email}</p>}
                                                                </div>
                                                                <DialogFooter>
                                                                    <Button type="button" variant="outline" onClick={() => setInterestOpen(false)}>Cancel</Button>
                                                                    <Button type="submit" disabled={interestProcessing}>Submit Interest</Button>
                                                                </DialogFooter>
                                                            </form>
                                                        </DialogContent>
                                                    </Dialog>
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
                                            <div className="flex justify-between"><span className="text-muted-foreground">Origin</span><span className="font-medium">{originLocation?.name ?? '—'} {post.origin_exact_lat ? `(${Number(post.origin_exact_lat).toFixed(4)}, ${Number(post.origin_exact_lng).toFixed(4)})` : ''}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Destination</span><span className="font-medium">{destinationLocation?.name ?? '—'} {post.destination_exact_lat ? `(${Number(post.destination_exact_lat).toFixed(4)}, ${Number(post.destination_exact_lng).toFixed(4)})` : ''}</span></div>
                                            <Separator />
                                            <div className="flex justify-between"><span className="text-muted-foreground">Item</span><span className="font-medium">{(post as unknown as { item_name?: string }).item_name ?? '—'}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Weight</span><span>{post.actual_weight ? `${post.actual_weight} ${post.weight_unit ?? 'ton'}` : '—'}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Tariff</span><span>{post.tariff_amount ? `${post.tariff_amount} ${post.tariff_currency ?? 'ETB'}` : '—'}</span></div>
                                            <Separator />
                                            <div className="flex justify-between"><span className="text-muted-foreground">Cargo</span><span className="truncate max-w-[160px]">{post.cargo_description ?? '—'}</span></div>
                                            <div className="flex justify-between"><span className="text-muted-foreground">Truck</span><span>{truckType?.name ?? '—'} {post.truck_count ? `×${post.truck_count}` : ''}</span></div>
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
