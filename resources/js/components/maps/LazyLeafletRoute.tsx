import { MapContainer, TileLayer, Polyline, Marker, Popup } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useState } from 'react';

interface Props {
    fromLat: number;
    fromLng: number;
    toLat: number;
    toLng: number;
    geometry?: { type?: string; coordinates?: number[][] } | null;
}

export default function LazyLeafletRoute({ fromLat, fromLng, toLat, toLng, geometry: initialGeometry }: Props) {
    const [geometry, setGeometry] = useState(initialGeometry ?? null);
    const [loading, setLoading] = useState(!initialGeometry);

    useEffect(() => {
        if (initialGeometry) return;
        // Fetch OSRM route geometry from backend if not provided
        fetch('/freight/routes/preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
            body: JSON.stringify({ from_lat: fromLat, from_lng: fromLng, to_lat: toLat, to_lng: toLng }),
        })
            .then(r => (r.ok ? r.json() : null))
            .then(data => {
                if (data?.geometry) setGeometry(data.geometry);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [fromLat, fromLng, toLat, toLng, initialGeometry]);

    const center: [number, number] = [
        (fromLat + toLat) / 2,
        (fromLng + toLng) / 2,
    ];

    const polylinePositions = geometry?.coordinates
        ? (geometry.coordinates as number[][]).map(c => [c[1], c[0]] as [number, number])
        : [[fromLat, fromLng], [toLat, toLng]] as [number, number][];

    if (loading && !geometry) {
        return <div className="flex h-full items-center justify-center text-sm text-muted-foreground">Fetching OSRM route…</div>;
    }

    return (
        <MapContainer
            center={center}
            zoom={7}
            scrollWheelZoom={false}
            style={{ height: '100%', width: '100%' }}
        >
            <TileLayer
                attribution='&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Marker position={[fromLat, fromLng]}>
                <Popup>Origin</Popup>
            </Marker>
            <Marker position={[toLat, toLng]}>
                <Popup>Destination</Popup>
            </Marker>
            <Polyline
                positions={polylinePositions}
                color="#2563eb"
                weight={4}
            />
        </MapContainer>
    );
}
