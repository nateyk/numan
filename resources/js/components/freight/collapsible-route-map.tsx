import RoutePreviewMap from '@/components/maps/RoutePreviewMap';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ChevronDown, Map } from 'lucide-react';
import { useState } from 'react';

export type RouteSnapshot = {
    distance_meters: number;
    duration_seconds: number;
    geometry: unknown;
    routing_provider: string | null;
} | null;

/**
 * The town-to-town map expands beneath the freight facts, never in a modal.
 * Leaflet stays unmounted while closed so most visitors do not load map tiles.
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

    if (!hasPoints) return null;

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <div className="border-t border-border pt-3">
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="min-h-10 gap-2"
                        data-test="freight-route-map-toggle"
                    >
                        <Map className="size-4" aria-hidden="true" />
                        {open ? 'Hide route map' : 'Show route map'}
                        <ChevronDown
                            className={`size-4 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`}
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent>
                {open && (
                    <div
                        role="region"
                        aria-label="Expanded freight route map"
                        className="space-y-2 pt-3"
                        data-test="freight-route-map"
                    >
                        <RoutePreviewMap
                            fromLat={fromLat}
                            fromLng={fromLng}
                            toLat={toLat}
                            toLng={toLng}
                            geometry={routeSnapshot?.geometry}
                            className="h-72 w-full !rounded-md sm:h-80"
                        />
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {fromName && toName
                                ? `${fromName} → ${toName}. `
                                : ''}
                            Approximate route between town locations; confirm
                            the exact pickup and delivery points with the
                            consignor.
                        </p>
                    </div>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}
