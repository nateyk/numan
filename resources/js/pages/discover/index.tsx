import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import LocationSearchInput from '@/components/maps/location-search-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Truck, Package, Calendar, MapPin, Route, ArrowRight, Search } from 'lucide-react';
import { useState } from 'react';

interface Freight {
    ulid: string;
    status: string;
    cargo_description: string | null;
    pickup_date: string | null;
    pickup_time?: string | null;
    truck_count?: number | null;
    originLocation?: { id: number; name: string; region_name: string };
    destinationLocation?: { id: number; name: string; region_name: string };
    cargoType?: { name: string };
    truckType?: { name: string };
    routeSnapshot?: { distance_meters: number; duration_seconds: number } | null;
    user?: { id: number; name: string; email: string; avatar_url?: string | null };
}

interface Props {
    freight: { data: Freight[]; links: Array<{ url: string | null; label: string; active: boolean }>; total: number; from: number | null; to: number | null };
    filters: Record<string, string>;
}

export default function DiscoverIndex({ freight, filters }: Props) {
    const [origin, setOrigin] = useState(filters.origin ?? '');
    const [destination, setDestination] = useState(filters.destination ?? '');

    const search = () => {
        router.get('/freight', { origin: origin || undefined, destination: destination || undefined }, { preserveState: true, replace: true });
    };

    const clear = () => {
        setOrigin('');
        setDestination('');
        router.get('/freight', {}, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Discover Freight — Ethiopian Marketplace" />
            <div className="mx-auto max-w-7xl space-y-8 p-6">
                {/* Header */}
                <div className="space-y-2">
                    <h1 className="text-3xl font-bold tracking-tight">Discover Freight</h1>
                    <p className="text-muted-foreground max-w-2xl">
                        Browse {freight.total ?? freight.data.length} active freight posts across Ethiopia — 934 major towns, from Addis Ababa to Dire Dawa. Find the perfect load for your fleet.
                    </p>
                </div>

                {/* Filters — shadcn Card */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Search className="size-4" /> Filter Freight
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-12">
                            <div className="md:col-span-5">
                                <label className="text-xs font-medium text-muted-foreground">Origin Town</label>
                                <LocationSearchInput
                                    onSelectLocation={(loc) => setOrigin(loc ? String(loc.id) : '')}
                                    placeholder="e.g., Addis Ababa…"
                                    className="mt-1"
                                />
                                {filters.origin && <p className="mt-1 text-xs text-muted-foreground">Filtered by origin ID {filters.origin}</p>}
                            </div>
                            <div className="md:col-span-5">
                                <label className="text-xs font-medium text-muted-foreground">Destination Town</label>
                                <LocationSearchInput
                                    onSelectLocation={(loc) => setDestination(loc ? String(loc.id) : '')}
                                    placeholder="e.g., Hawassa…"
                                    className="mt-1"
                                />
                                {filters.destination && <p className="mt-1 text-xs text-muted-foreground">Filtered by destination ID {filters.destination}</p>}
                            </div>
                            <div className="md:col-span-2 flex items-end gap-2">
                                <Button onClick={search} className="flex-1">
                                    <Search className="mr-2 size-4" /> Search
                                </Button>
                                <Button variant="outline" onClick={clear}>Clear</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Results meta */}
                <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">
                        {freight.total ? `${freight.total} results` : `${freight.data.length} results`} {freight.from && freight.to ? `• ${freight.from}–${freight.to}` : ''}
                    </span>
                    <Badge variant="secondary">{freight.data.length} shown</Badge>
                </div>

                {/* Freight grid — beautiful shadcn cards */}
                {freight.data.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Package className="size-6 text-muted-foreground" />
                            </div>
                            <h3 className="font-semibold">No active freight found</h3>
                            <p className="max-w-md text-sm text-muted-foreground">Try adjusting your filters or check back later. Active posts appear here after admin approval.</p>
                            <Button variant="outline" asChild>
                                <Link href="/my-freight">Post Freight</Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {freight.data.map((f) => (
                            <Card key={f.ulid} className="flex flex-col overflow-hidden hover:shadow-md transition-shadow">
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2 text-base leading-tight">
                                            <MapPin className="size-4 shrink-0 text-primary" />
                                            <span className="truncate">{f.originLocation?.name ?? '—'}</span>
                                            <ArrowRight className="size-3.5 shrink-0 text-muted-foreground" />
                                            <span className="truncate">{f.destinationLocation?.name ?? '—'}</span>
                                        </CardTitle>
                                        <Badge variant="outline" className="shrink-0 capitalize">
                                            {f.status}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {f.originLocation?.region_name ?? '—'} → {f.destinationLocation?.region_name ?? '—'}
                                    </p>
                                </CardHeader>
                                <CardContent className="flex-1 space-y-3">
                                    <div className="flex items-center gap-2 text-sm">
                                        <Package className="size-4 text-muted-foreground" />
                                        <span className="font-medium">{f.cargoType?.name ?? 'General Cargo'}</span>
                                        <span className="text-muted-foreground truncate">— {f.cargo_description ?? 'No description'}</span>
                                    </div>
                                    <div className="flex flex-wrap gap-2 text-xs">
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            <Truck className="size-3" /> {f.truckType?.name ?? 'Any truck'} {f.truck_count ? `×${f.truck_count}` : ''}
                                        </Badge>
                                        <Badge variant="secondary" className="flex items-center gap-1">
                                            <Calendar className="size-3" /> {f.pickup_date ? String(f.pickup_date).slice(0, 10) : 'Flexible'}
                                            {f.pickup_time ? ` ${String(f.pickup_time).slice(0, 5)}` : ''}
                                        </Badge>
                                        {f.routeSnapshot && (
                                            <Badge variant="secondary" className="flex items-center gap-1">
                                                <Route className="size-3" /> {(f.routeSnapshot.distance_meters / 1000).toFixed(0)} km • {Math.round(f.routeSnapshot.duration_seconds / 60)} min
                                            </Badge>
                                        )}
                                    </div>
                                </CardContent>
                                <div className="mx-5 h-px bg-border" />
                                {/* User card */}
                                <CardFooter className="flex items-center justify-between gap-3 py-4">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="size-8">
                                            <AvatarImage src={f.user?.avatar_url ?? undefined} />
                                            <AvatarFallback>{f.user?.name?.slice(0, 2).toUpperCase() ?? '—'}</AvatarFallback>
                                        </Avatar>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium leading-none">{f.user?.name ?? 'Unknown consignor'}</p>
                                            <p className="truncate text-xs text-muted-foreground">{f.user?.email ?? '—'}</p>
                                        </div>
                                    </div>
                                    <Button asChild size="sm">
                                        <Link href={`/my-freight/${f.ulid}`}>View</Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {freight.links && freight.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {freight.links.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                            >
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
