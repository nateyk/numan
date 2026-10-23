import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import { Map, Route } from 'lucide-react';
import { useState } from 'react';

export type RouteSnapshot = {
    distance_meters: number;
    duration_seconds: number;
    geometry: unknown;
    routing_provider: string | null;
} | null;

/**
 * The route map, behind a button.
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
        <div className="space-y-3">
            <Button
                variant="outline"
                size="sm"
                type="button"
                onClick={() => setOpen((wasOpen) => !wasOpen)}
                aria-expanded={open}
                className="w-full sm:w-auto"
            >
                <Map className="mr-2 size-4" />
                {open ? 'Hide route map' : 'View route map'}
            </Button>

            {open && (
                <div className="space-y-2">
                    <div className="overflow-hidden rounded-lg border">
                        <RoutePreviewMap
                            fromLat={fromLat}
                            fromLng={fromLng}
                            toLat={toLat}
                            toLng={toLng}
                            geometry={routeSnapshot?.geometry}
                            className="h-[420px] !rounded-none !border-0"
                        />
                    </div>

                    {routeSnapshot && (
                        <p className="text-sm text-muted-foreground">
                            <Route className="mr-2 inline size-4" />
                            {(routeSnapshot.distance_meters / 1000).toFixed(
                                1,
                            )}{' '}
                            km
                            {' • '}
                            {Math.round(
                                routeSnapshot.duration_seconds / 60,
                            )}{' '}
                            min
                            {routeSnapshot.routing_provider && (
                                <span className="ml-1">
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
        </div>
    );
}
