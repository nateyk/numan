import { MapContainer, TileLayer, Polyline, Marker, Popup } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';

interface Props {
    fromLat: number;
    fromLng: number;
    toLat: number;
    toLng: number;
    geometry?: unknown;
}

export default function LazyLeafletRoute({ fromLat, fromLng, toLat, toLng, geometry }: Props) {
    const center: [number, number] = [
        (fromLat + toLat) / 2,
        (fromLng + toLng) / 2,
    ];

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
                positions={[
                    [fromLat, fromLng],
                    [toLat, toLng],
                ]}
                color="#2563eb"
                weight={4}
            />
        </MapContainer>
    );
}
