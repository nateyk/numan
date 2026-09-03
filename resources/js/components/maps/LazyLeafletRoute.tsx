import { MapContainer, TileLayer, Polyline, Marker, Popup, useMapEvents, useMap } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useState } from 'react';

interface Props {
    fromLat: number;
    fromLng: number;
    toLat: number;
    toLng: number;
    geometry?: { type?: string; coordinates?: number[][] } | null;
    onMapClick?: (lat: number, lng: number) => void;
    selectable?: 'origin' | 'destination' | null;
}

function MapClickHandler({ onClick }: { onClick?: (lat: number, lng: number) => void }) {
    useMapEvents({
        click(e) {
            onClick?.(e.latlng.lat, e.latlng.lng);
        },
    });
    return null;
}

function FitBounds({ fromLat, fromLng, toLat, toLng }: { fromLat: number; fromLng: number; toLat: number; toLng: number }) {
    const map = useMap();
    useEffect(() => {
        const bounds = [
            [fromLat, fromLng],
            [toLat, toLng],
        ] as [[number, number], [number, number]];
        map.fitBounds(bounds, { padding: [40, 40] });
    }, [fromLat, fromLng, toLat, toLng, map]);
    return null;
}

export default function LazyLeafletRoute({ fromLat, fromLng, toLat, toLng, geometry: initialGeometry, onMapClick }: Props) {
    const [geometry, setGeometry] = useState(initialGeometry ?? null);
    const [loading, setLoading] = useState(!initialGeometry);

    useEffect(() => {
        setGeometry(initialGeometry ?? null);
        if (initialGeometry) {
            setLoading(false);
            return;
        }
        setLoading(true);
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
            <Marker position={[fromLat, fromLng]} draggable={!!onMapClick}>
                <Popup>Origin — {fromLat.toFixed(4)}, {fromLng.toFixed(4)}</Popup>
            </Marker>
            <Marker position={[toLat, toLng]} draggable={!!onMapClick}>
                <Popup>Destination — {toLat.toFixed(4)}, {toLng.toFixed(4)}</Popup>
            </Marker>
            <Polyline
                positions={polylinePositions}
                color="#2563eb"
                weight={4}
            />
            <FitBounds fromLat={fromLat} fromLng={fromLng} toLat={toLat} toLng={toLng} />
            <MapClickHandler onClick={onMapClick} />
        </MapContainer>
    );
}
