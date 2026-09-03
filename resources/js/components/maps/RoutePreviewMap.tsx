import { useEffect, useState, Suspense, lazy, ComponentType } from 'react';

interface Props {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    geometry?: unknown;
    className?: string;
}

const LazyLeafletRoute = lazy<ComponentType<{ fromLat: number; fromLng: number; toLat: number; toLng: number; geometry?: unknown }>>(() => import('./LazyLeafletRoute').then(m => ({ default: (m as any).default || (m as any) })));

const LazyDefaultEthiopiaMap = lazy(() => import('./DefaultEthiopiaMap'));

export default function RoutePreviewMap({ fromLat, fromLng, toLat, toLng, geometry, className }: Props) {
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    if (!isClient) {
        return <div className={`flex h-64 items-center justify-center rounded-md border bg-muted ${className}`}>Loading map…</div>;
    }

    if (!fromLat || !fromLng || !toLat || !toLng) {
        return (
            <div className={`relative h-64 overflow-hidden rounded-md border bg-muted ${className ?? ''} h-[420px]`}>
                <Suspense fallback={<div className="flex h-full items-center justify-center text-sm text-muted-foreground">Loading Ethiopia map…</div>}>
                    <LazyDefaultEthiopiaMap />
                </Suspense>
                <div className="pointer-events-none absolute bottom-2 left-2 right-2 rounded bg-white/90 px-2 py-1 text-center text-xs text-muted-foreground shadow">
                    Search origin & destination towns (934 major Ethiopian towns) to see Leaflet route
                </div>
            </div>
        );
    }

    return (
        <div className={`relative h-64 overflow-hidden rounded-md border bg-muted ${className}`}>
            <Suspense fallback={<div className="flex h-full items-center justify-center text-sm text-muted-foreground">Loading route…</div>}>
                <LazyLeafletRoute
                    fromLat={fromLat}
                    fromLng={fromLng}
                    toLat={toLat}
                    toLng={toLng}
                    geometry={geometry}
                />
            </Suspense>
        </div>
    );
}
