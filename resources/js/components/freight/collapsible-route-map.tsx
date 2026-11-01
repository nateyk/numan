import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Map, Route } from 'lucide-react';
import { useState } from 'react';

export type RouteSnapshot = {
    distance_meters: number;
    duration_seconds: number;
    geometry: unknown;
    routing_provider: string | null;
} | null;

/**
 * The route map, opened on demand in a dialog.
 *
 * Not rendered at all until asked for, which matters for more than tidiness:
 * route_snapshot.geometry is a full OSRM polyline and can be tens of kilobytes
 * of JSON, and Leaflet has to build a tile layer and measure the DOM on mount.
 * Most people open a freight post for the weight and the tariff, never scroll
 * back to a picture of a map.
 *
 * The toggle names the action only. The distance is already typeset in the
 * page's route stat, so repeating it on the button said the same thing twice.
 */
export default function CollapsibleRouteMap({
    fromLat,
    fromLng,
    toLat,
    toLng,
    routeSnapshot,
}: {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    routeSnapshot: RouteSnapshot;
}) {
    const [open, setOpen] = useState(false);

    const hasPoints =
        fromLat !== null &&
        fromLng !== null &&
        toLat !== null &&
        toLng !== null;

    // No coordinates at all means there is nothing to draw. A toggle that opens
    // onto an empty grey square is worse than no toggle.
    if (!hasPoints) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    className="h-8 gap-1.5 px-2 text-xs"
                >
                    <Map className="size-3.5" />
                    View route map
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Route preview</DialogTitle>
                    <DialogDescription>
                        Pickup and delivery locations for this load.
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <div className="space-y-3">
                        <div className="overflow-hidden rounded-lg border">
                            <RoutePreviewMap
                                fromLat={fromLat}
                                fromLng={fromLng}
                                toLat={toLat}
                                toLng={toLng}
                                geometry={routeSnapshot?.geometry}
                                className="h-[min(55dvh,420px)] !rounded-none !border-0"
                            />
                        </div>
                        {routeSnapshot && (
                            <p className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <Route
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {(routeSnapshot.distance_meters / 1000).toFixed(
                                    1,
                                )}{' '}
                                km ·{' '}
                                {Math.round(
                                    routeSnapshot.duration_seconds / 60,
                                )}{' '}
                                min
                                {routeSnapshot.routing_provider && (
                                    <span>
                                        ·{' '}
                                        {routeSnapshot.routing_provider ===
                                        'straight_line'
                                            ? 'straight-line estimate'
                                            : `${routeSnapshot.routing_provider} routed`}
                                    </span>
                                )}
                            </p>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
