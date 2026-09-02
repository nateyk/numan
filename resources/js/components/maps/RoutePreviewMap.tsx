import { useEffect, useState, Suspense, lazy } from 'react';

interface Props {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    geometry?: unknown;
    className?: string;
}

const LazyLeafletRoute = lazy(() => import('./LazyLeafletRoute'));

export default function RoutePreviewMap({ fromLat, fromLng, toLat, toLng, geometry, className }: Props) {
    const [isClient, setIsClient] = useState(false);

    useEffect(() => {
        setIsClient(true);
    }, []);

    if (!isClient) {
        return <div className={`flex h-64 items-center justify-center rounded-md border bg-muted ${className}`}>Loading map…</div>;
    }

    if (!fromLat || !fromLng || !toLat || !toLng) {
        return <div className={`flex h-64 items-center justify-center rounded-md border bg-muted ${className}`}>Select origin and destination to see route.</div>;
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
