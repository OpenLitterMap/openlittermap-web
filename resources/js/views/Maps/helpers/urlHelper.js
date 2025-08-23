import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './constants.js';
import L from 'leaflet';

export const urlHelper = {
    /**
     * Goes to the location and zoom given in the URL
     */
    flyToLocationFromURL(mapInstance) {
        let urlParams = new URLSearchParams(window.location.search);
        let latitude = parseFloat(urlParams.get('lat') || 0);
        let longitude = parseFloat(urlParams.get('lon') || 0);
        let zoom = parseFloat(urlParams.get('zoom') || MIN_ZOOM);
        let photoId = parseInt(urlParams.get('photo'));
        let load = urlParams.get('load') === 'true';

        // Validate lat, lon, and zoom level
        latitude = latitude < -85 || latitude > 85 ? 0 : latitude;
        longitude = longitude < -180 || longitude > 180 ? 0 : longitude;
        zoom = zoom < 2 || zoom > MAX_ZOOM ? MIN_ZOOM : zoom;

        if (latitude === 0 && longitude === 0 && zoom === 2) return;

        // If load=true, set view instantly without animation
        if (load) {
            this.setViewInstantly({ latitude, longitude, zoom, photoId }, mapInstance);
        } else {
            this.flyToLocation({ latitude, longitude, zoom, photoId }, mapInstance);
        }
    },

    /**
     * Set view instantly without animation
     */
    setViewInstantly(location, mapInstance) {
        const latLng = L.latLng(location.latitude, location.longitude);
        const zoom =
            location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
                ? CLUSTER_ZOOM_THRESHOLD
                : location.zoom;

        // Calculate the offset in pixels to position the point 10% from the bottom
        const mapSize = mapInstance.getSize();
        const originalPoint = mapInstance.project(latLng, zoom);
        const offsetY = mapSize.y * 0.225;
        const shiftedPoint = originalPoint.subtract([0, offsetY]);
        const targetLatLng = mapInstance.unproject(shiftedPoint, zoom);

        // Set view instantly without animation
        mapInstance.setView(targetLatLng, zoom, {
            animate: false,
        });
    },

    /**
     * Fly to the location with offset
     */
    flyToLocation(location, mapInstance) {
        const latLng = L.latLng(location.latitude, location.longitude);
        const zoom =
            location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
                ? CLUSTER_ZOOM_THRESHOLD
                : location.zoom;

        // Calculate the offset in pixels
        const mapSize = mapInstance.getSize();
        const originalPoint = mapInstance.project(latLng, zoom);
        const offsetY = mapSize.y * 0.225;
        const shiftedPoint = originalPoint.subtract([0, offsetY]);
        const targetLatLng = mapInstance.unproject(shiftedPoint, zoom);

        mapInstance.flyTo(targetLatLng, zoom, {
            animate: true,
            duration: location.duration ?? 5,
        });
    },

    /**
     * Update URL with current map location and zoom
     */
    updateLocationInURL(mapInstance) {
        const location = mapInstance.getCenter();
        const url = new URL(window.location.href);
        url.searchParams.set('lat', location.lat);
        url.searchParams.set('lon', location.lng);
        url.searchParams.set('zoom', mapInstance.getZoom());
        window.history.replaceState(null, '', url);
    },

    /**
     * Update URL with photo ID and fly to location
     */
    updateUrlPhotoIdAndFlyToLocation({ latitude, longitude, photoId, mapInstance }) {
        const url = new URL(window.location.href);
        url.searchParams.set('photo', photoId);
        window.history.pushState(null, '', url);

        const zoom = 17;

        // Check if we're viewing points and moving within 2km
        const currentMapZoom = Math.round(mapInstance.getZoom());
        const flyDistanceInMeters = mapInstance.distance(mapInstance.getCenter(), [latitude, longitude]);

        if (currentMapZoom >= CLUSTER_ZOOM_THRESHOLD && flyDistanceInMeters <= 2000) {
            this.flyToLocation({ latitude, longitude, zoom, photoId, duration: 1 }, mapInstance);
        } else {
            this.flyToLocation({ latitude, longitude, zoom, photoId }, mapInstance);
        }
    },

    /**
     * Remove photo ID from URL
     */
    removePhotoFromURL() {
        const url = new URL(window.location.href);
        url.searchParams.delete('photo');
        window.history.pushState(null, '', url);
    },

    /**
     * Update drawer open/close state in URL
     * Always adds load=true when drawer is opened
     */
    updateDrawerStateInURL(isOpen) {
        const url = new URL(window.location.href);

        if (isOpen) {
            url.searchParams.set('open', 'true');
            url.searchParams.set('load', 'true');
        } else {
            url.searchParams.set('open', 'false');
            // Keep load=true if it was already set
            if (!url.searchParams.has('load')) {
                url.searchParams.set('load', 'true');
            }
        }

        window.history.pushState(null, '', url);
    },

    /**
     * Check if drawer should be open from URL
     */
    shouldDrawerBeOpen() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('open') === 'true';
    },

    /**
     * Set load=true in URL (used when drawer first appears)
     */
    setLoadInURL() {
        const url = new URL(window.location.href);
        url.searchParams.set('load', 'true');
        window.history.pushState(null, '', url);
    },

    /**
     * Check if load=true is in URL
     */
    hasLoadParam() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('load') === 'true';
    },
};
