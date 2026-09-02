import { useEffect } from 'react';

interface Props {
    fromLat: number;
    fromLng: number;
    toLat: number;
    toLng: number;
    geometry?: unknown;
}

export default function LazyLeafletRoute({ fromLat, fromLng, toLat, toLng, geometry }: Props) {
    useEffect(() => {
        // Leaflet + OSRM route integration: load on demand when component mounts
        // Placeholder for production integration; avoids SSR and bundle bloat
        console.log('Lazy route', { fromLat, fromLng, toLat, toLng, geometry });
    }, [fromLat, fromLng, toLat, toLng, geometry]);

    return (
        <div className="flex h-full items-center justify-center p-4 text-center text-sm text-muted-foreground">
            <div>
                <p>Route preview</p>
                <p className="mt-1 text-xs">{fromLat.toFixed(2)},{fromLng.toFixed(2)} → {toLat.toFixed(2)},{toLng.toFixed(2)}</p>
                {geometry ? <p className="mt-2 text-xs">Geometry loaded.</p> : <p className="mt-2 text-xs">Fetching OSRM route…</p>}
            </div>
        </div>
    );
}
