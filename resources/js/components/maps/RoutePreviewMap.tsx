import { useEffect, useState } from 'react';

interface Props {
    fromLat: number | null;
    fromLng: number | null;
    toLat: number | null;
    toLng: number | null;
    geometry?: unknown;
    className?: string;
}

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

    // Lazy load leaflet only on client; fallback to placeholder if not available
    return (
        <div className={`relative h-64 overflow-hidden rounded-md border bg-muted ${className}`}>
            <div className="flex h-full items-center justify-center p-4 text-center text-sm text-muted-foreground">
                <div>
                    <p>Route preview</p>
                    <p className="mt-1 text-xs">
                        {fromLat.toFixed(2)},{fromLng.toFixed(2)} → {toLat.toFixed(2)},{toLng.toFixed(2)}
                    </p>
                    {geometry ? <p className="mt-2 text-xs">Geometry loaded.</p> : <p className="mt-2 text-xs">Fetching OSRM route…</p>}
                </div>
            </div>
        </div>
    );
}
