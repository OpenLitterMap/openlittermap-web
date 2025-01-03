import {CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM} from "./constants.js";
import L from "leaflet";

/**
 * Goes to the location and zoom given in the URL
 * Params are: lat, lon, zoom, photo
 */
export const flyToLocationFromURL = (mapInstance) => {
    let urlParams = new URLSearchParams(window.location.search);
    let latitude = parseFloat(urlParams.get('lat') || 0);
    let longitude = parseFloat(urlParams.get('lon') || 0);
    let zoom = parseFloat(urlParams.get('zoom') || MIN_ZOOM);
    let photoId = parseInt(urlParams.get('photo'));

    // Validate lat, lon, and zoom level
    latitude = (latitude < -85 || latitude > 85) ? 0 : latitude;
    longitude = (longitude < -180 || longitude > 180) ? 0 : longitude;
    zoom = (zoom < 2 || zoom > MAX_ZOOM) ? MIN_ZOOM : zoom;

    if (latitude === 0 && longitude === 0 && zoom === 2) return;

    flyToLocation({latitude, longitude, zoom, photoId}, mapInstance);
};

export const flyToLocation = (location, mapInstance) => {
    const latLng = L.latLng(location.latitude, location.longitude);
    const zoom = location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
        ? CLUSTER_ZOOM_THRESHOLD
        : location.zoom;

    // Calculate the offset in pixels to position the point 10% from the bottom
    const mapSize = mapInstance.getSize();
    const point = mapInstance.project(latLng, zoom);
    const offsetY = mapSize.y * 0.4; // 0.4 times the map height
    const targetPoint = point.subtract([0, offsetY]);
    const targetLatLng = mapInstance.unproject(targetPoint, zoom);

    mapInstance.flyTo(targetLatLng, zoom, {
        animate: true,
        duration: location.duration ?? 5,
    });
};

/**
 * Simply updates the URL
 * with the current map location and zoom
 */
export const updateLocationInURL = (mapInstance) => {
    const location = mapInstance.getCenter();

    const url = new URL(window.location.href);
    url.searchParams.set('lat', location.lat);
    url.searchParams.set('lon', location.lng);
    url.searchParams.set('zoom', mapInstance.getZoom());

    window.history.pushState(null, '', url);
};

/**
 * Updates the url with the photoId
 * and goes to the location
 */
export const updateUrlPhotoIdAndFlyToLocation = ({ latitude, longitude, photoId, mapInstance }) => {

    const url = new URL(window.location.href);
    url.searchParams.set('photo', photoId);
    window.history.pushState(null, '', url);

    const targetZoom = 17;

    // Check if we're viewing points and moving within 2km
    const currentMapZoom = Math.round(mapInstance.getZoom());

    const flyDistanceInMeters = mapInstance.distance(mapInstance.getCenter(), [latitude, longitude]);
    if (currentMapZoom >= CLUSTER_ZOOM_THRESHOLD && flyDistanceInMeters <= 2000) {
        mapInstance.flyTo([latitude, longitude], targetZoom, { duration: 1 });
    } else {
        mapInstance.flyTo([latitude, longitude], targetZoom);
    }
}
