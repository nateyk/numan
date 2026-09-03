import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

export default function DefaultEthiopiaMap() {
    return (
        <MapContainer
            center={[9.03, 38.74]}
            zoom={6}
            scrollWheelZoom={false}
            style={{ height: '100%', width: '100%' }}
        >
            <TileLayer
                attribution='&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
            />
            <Marker position={[9.03, 38.74]}>
                <Popup>Addis Ababa — search 934 major towns to plot route</Popup>
            </Marker>
            <Marker position={[7.05, 38.47]}>
                <Popup>Hawassa</Popup>
            </Marker>
            <Marker position={[11.59, 37.39]}>
                <Popup>Bahir Dar</Popup>
            </Marker>
        </MapContainer>
    );
}
