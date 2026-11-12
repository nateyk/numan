import {
    formatDistance,
    formatDuration,
} from '@/components/freight/format-route';
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
    fromName,
    toName,
    routeSnapshot,
}: {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    fromName?: string;
    toName?: string;
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
                    className="min-h-11 gap-2 px-3 text-sm"
                    aria-label="View route map"
                >
                    <Map className="size-4" aria-hidden="true" />
                    View map
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[92dvh] w-[calc(100%-1rem)] flex-col gap-4 overflow-hidden p-4 sm:max-w-4xl sm:p-6 [&>button]:flex [&>button]:size-11 [&>button]:items-center [&>button]:justify-center">
                <DialogHeader className="shrink-0 pr-9">
                    <DialogTitle className="text-base sm:text-lg">
                        Route map
                    </DialogTitle>
                    <DialogDescription className="leading-relaxed">
                        {fromName && toName ? (
                            <>
                                <span className="font-medium text-foreground">
                                    {fromName} → {toName}.
                                </span>{' '}
                                Pickup and delivery locations for this load.
                            </>
                        ) : (
                            'Pickup and delivery locations for this load.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                {open && (
                    <div className="min-h-0 space-y-3 overflow-y-auto">
                        <div className="overflow-hidden rounded-md border bg-muted">
                            <RoutePreviewMap
                                fromLat={fromLat}
                                fromLng={fromLng}
                                toLat={toLat}
                                toLng={toLng}
                                geometry={routeSnapshot?.geometry}
                                className="h-[min(58dvh,32rem)] min-h-64 !rounded-none !border-0"
                            />
                        </div>
                        {routeSnapshot && (
                            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">
                                <Route
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                {formatDistance(routeSnapshot.distance_meters)}
                                {' · '}
                                {formatDuration(routeSnapshot.duration_seconds)}
                                {routeSnapshot.routing_provider && (
                                    <span>
                                        ·{' '}
                                        {routeSnapshot.routing_provider ===
                                        'straight_line'
                                            ? 'Straight-line estimate'
                                            : routeSnapshot.routing_provider + ' routed'}
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
