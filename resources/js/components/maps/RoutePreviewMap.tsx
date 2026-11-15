import { Suspense, lazy, useSyncExternalStore } from 'react';

interface Props {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    geometry?: unknown;
    className?: string;
}

const LazyLeafletRoute = lazy(() => import('./LazyLeafletRoute'));
const subscribeToHydration = () => () => {};
const getClientSnapshot = () => true;
const getServerSnapshot = () => false;

const LazyDefaultEthiopiaMap = lazy(() => import('./DefaultEthiopiaMap'));

export default function RoutePreviewMap({
    fromLat,
    fromLng,
    toLat,
    toLng,
    geometry,
    className,
}: Props) {
    // Keep the server-rendered placeholder during hydration, without an
    // effect that immediately updates React state.
    const isClient = useSyncExternalStore(
        subscribeToHydration,
        getClientSnapshot,
        getServerSnapshot,
    );

    if (!isClient) {
        return (
            <div
                className={`flex h-64 items-center justify-center rounded-md border bg-muted ${className}`}
            >
                Loading map…
            </div>
        );
    }

    if (!fromLat || !fromLng || !toLat || !toLng) {
        return (
            <div
                className={`relative h-64 overflow-hidden rounded-md border bg-muted ${className ?? ''} h-[420px]`}
            >
                <Suspense
                    fallback={
                        <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                            Loading Ethiopia map…
                        </div>
                    }
                >
                    <LazyDefaultEthiopiaMap />
                </Suspense>
                <div className="pointer-events-none absolute right-2 bottom-2 left-2 rounded bg-white/90 px-2 py-1 text-center text-xs text-muted-foreground shadow">
                    Search origin & destination towns (934 major Ethiopian
                    towns) to see Leaflet route
                </div>
            </div>
        );
    }

    return (
        <div
            className={`relative h-64 overflow-hidden rounded-md border bg-muted ${className}`}
        >
            <Suspense
                fallback={
                    <div className="flex h-full items-center justify-center text-sm text-muted-foreground">
                        Loading route…
                    </div>
                }
            >
                <LazyLeafletRoute
                    fromLat={fromLat}
                    fromLng={fromLng}
                    toLat={toLat}
                    toLng={toLng}
                    geometry={
                        geometry as {
                            type?: string;
                            coordinates?: number[][];
                        } | null
                    }
                />
            </Suspense>
        </div>
    );
}
