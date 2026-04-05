import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './constants.js';
import L from 'leaflet';

/**
 * URL State Manager - Single source of truth for URL operations
 * Handles all URL parameter management with consistent push/replace strategies
 */
class URLStateManager {
    constructor() {
        // Define parameter categories
        this.mapParams = ['lat', 'lon', 'zoom'];
        this.viewParams = ['photo', 'page', 'open', 'load'];
        this.filterParams = ['year', 'fromDate', 'toDate', 'username', 'filter'];
        this.allParams = [...this.mapParams, ...this.viewParams, ...this.filterParams];
    }

    /**
     * Get photo ID from URL (handles both 'photo' and 'photoId' for backwards compatibility)
     */
    getPhotoIdFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const photoId = urlParams.get('photo') || urlParams.get('photoId');
        return photoId ? parseInt(photoId) : null;
    }

    /**
     * Normalize photo parameter in URL (converts photoId to photo)
     */
    normalizePhotoParam() {
        const url = new URL(window.location.href);
        const photoId = url.searchParams.get('photoId');

        if (photoId) {
            url.searchParams.delete('photoId');
            url.searchParams.set('photo', photoId);
            this.commitURL(url, true); // Use replace for normalization
            return parseInt(photoId);
        }

        const photo = url.searchParams.get('photo');
        return photo ? parseInt(photo) : null;
    }

    /**
     * Update map location in URL (always replace for continuous updates)
     */
    updateMapLocation(lat, lon, zoom) {
        const url = new URL(window.location.href);
        url.searchParams.set('lat', lat.toFixed(6));
        url.searchParams.set('lon', lon.toFixed(6));
        url.searchParams.set('zoom', zoom.toFixed(2));
        this.commitURL(url, true); // Always replace for map movement
    }

    /**
     * Update drawer state in URL
     */
    updateDrawerState(isOpen, isUserAction = true) {
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

        // Use push for user actions, replace for programmatic
        this.commitURL(url, !isUserAction);
    }

    /**
     * Update photo ID in URL
     */
    updatePhotoId(photoId, isUserAction = true) {
        const url = new URL(window.location.href);

        if (photoId) {
            url.searchParams.set('photo', photoId);
        } else {
            url.searchParams.delete('photo');
            url.searchParams.delete('photoId');
        }

        this.commitURL(url, !isUserAction);
    }

    /**
     * Update page number in URL
     */
    updatePage(page, isUserAction = true) {
        const url = new URL(window.location.href);

        if (page > 1) {
            url.searchParams.set('page', page.toString());
        } else {
            url.searchParams.delete('page');
        }

        // Use push for user navigation, replace for auto-resets
        this.commitURL(url, !isUserAction);
    }

    /**
     * Get current filters from URL
     */
    getFiltersFromURL() {
        const params = new URLSearchParams(window.location.search);

        // Parse tag filter: format is "type:value" (e.g., "category:smoking", "brand:45")
        let tagFilter = null;
        const filterParam = params.get('filter');
        if (filterParam) {
            const colonIdx = filterParam.indexOf(':');
            if (colonIdx > 0) {
                tagFilter = {
                    type: filterParam.substring(0, colonIdx),
                    id: filterParam.substring(colonIdx + 1),
                };
            }
        }

        return {
            year: parseInt(params.get('year')) || null,
            fromDate: params.get('fromDate') || null,
            toDate: params.get('toDate') || null,
            username: params.get('username') || null,
            page: parseInt(params.get('page')) || 1,
            tagFilter,
        };
    }

    /**
     * Set tag filter in URL
     */
    setTagFilter(type, id, { from, to } = {}) {
        const url = new URL(window.location.href);
        url.searchParams.set('filter', `${type}:${id}`);
        // Reset page when filter changes
        url.searchParams.delete('page');

        // Date filters also set fromDate/toDate
        if (type === 'date' && from && to) {
            url.searchParams.set('fromDate', from);
            url.searchParams.set('toDate', to);
        }

        this.commitURL(url, false); // Push for user action
    }

    /**
     * Clear tag filter from URL
     */
    clearTagFilter() {
        const url = new URL(window.location.href);
        url.searchParams.delete('filter');
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('page');
        this.commitURL(url, false); // Push for user action
    }

    /**
     * Clear specific parameter groups
     */
    clearParamGroup(group) {
        const url = new URL(window.location.href);
        const params =
            group === 'map'
                ? this.mapParams
                : group === 'view'
                  ? this.viewParams
                  : group === 'filter'
                    ? this.filterParams
                    : this.allParams;

        params.forEach((param) => url.searchParams.delete(param));
        this.commitURL(url, true);
    }

    /**
     * Check if drawer should be open from URL
     */
    shouldDrawerBeOpen() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('open') === 'true';
    }

    /**
     * Check if instant load is enabled
     */
    hasLoadParam() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('load') === 'true';
    }

    /**
     * Get location parameters from URL
     */
    getLocationFromURL() {
        const params = new URLSearchParams(window.location.search);
        return {
            lat: parseFloat(params.get('lat')) || 0,
            lon: parseFloat(params.get('lon')) || 0,
            zoom: parseFloat(params.get('zoom')) || MIN_ZOOM,
            photo: this.getPhotoIdFromURL(),
            load: params.get('load') === 'true',
        };
    }

    /**
     * Commit URL changes with proper history management
     */
    commitURL(url, useReplace = true) {
        const method = useReplace ? 'replaceState' : 'pushState';
        window.history[method](null, '', url.toString());
    }
}

// Create singleton instance
const urlStateManager = new URLStateManager();

// Export legacy interface for backwards compatibility
export const urlHelper = {
    // Photo management
    getPhotoIdFromURL: () => urlStateManager.getPhotoIdFromURL(),
    normalizePhotoParam: () => urlStateManager.normalizePhotoParam(),
    removePhotoFromURL: () => urlStateManager.updatePhotoId(null, false),

    // Location management
    updateLocationInURL: (mapInstance) => {
        const center = mapInstance.getCenter();
        urlStateManager.updateMapLocation(center.lat, center.lng, mapInstance.getZoom());
    },

    // Fly to location functionality
    flyToLocationFromURL: (mapInstance) => {
        const location = urlStateManager.getLocationFromURL();

        // Validate coordinates
        const latitude = location.lat < -85 || location.lat > 85 ? 0 : location.lat;
        const longitude = location.lon < -180 || location.lon > 180 ? 0 : location.lon;
        const zoom = location.zoom < MIN_ZOOM || location.zoom > MAX_ZOOM ? MIN_ZOOM : location.zoom;

        if (latitude === 0 && longitude === 0 && zoom === MIN_ZOOM) return;

        if (location.load || location.photo) {
            // Always set view instantly — initializeMap already positioned the map,
            // this just applies the Y-offset for photo viewing
            urlHelper.setViewInstantly({ latitude, longitude, zoom, photoId: location.photo }, mapInstance);
        }
    },

    setViewInstantly: (location, mapInstance) => {
        const latLng = L.latLng(location.latitude, location.longitude);
        const zoom =
            location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
                ? CLUSTER_ZOOM_THRESHOLD
                : location.zoom;

        // Calculate offset for better photo viewing
        const mapSize = mapInstance.getSize();
        const originalPoint = mapInstance.project(latLng, zoom);
        const offsetY = mapSize.y * 0.225;
        const shiftedPoint = originalPoint.subtract([0, offsetY]);
        const targetLatLng = mapInstance.unproject(shiftedPoint, zoom);

        mapInstance.setView(targetLatLng, zoom, { animate: false });
    },

    flyToLocation: (location, mapInstance) => {
        const latLng = L.latLng(location.latitude, location.longitude);
        const zoom =
            location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
                ? CLUSTER_ZOOM_THRESHOLD
                : location.zoom;

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

    updateUrlPhotoIdAndFlyToLocation: ({ latitude, longitude, photoId, mapInstance }) => {
        const zoom = 17;

        // Set full URL immediately so it's shareable before fly animation completes
        const url = new URL(window.location.href);
        url.searchParams.set('photo', photoId);
        url.searchParams.set('lat', latitude.toFixed(6));
        url.searchParams.set('lon', longitude.toFixed(6));
        url.searchParams.set('zoom', zoom.toFixed(2));
        url.searchParams.set('load', 'true');
        url.searchParams.set('open', 'true');
        urlStateManager.commitURL(url, false);

        const currentZoom = Math.round(mapInstance.getZoom());
        const distance = mapInstance.distance(mapInstance.getCenter(), [latitude, longitude]);

        // Short animation for nearby points
        const duration = currentZoom >= CLUSTER_ZOOM_THRESHOLD && distance <= 2000 ? 1 : 5;

        urlHelper.flyToLocation({ latitude, longitude, zoom, photoId, duration }, mapInstance);
    },

    // Drawer state
    updateDrawerStateInURL: (isOpen, isUserAction = true) => {
        urlStateManager.updateDrawerState(isOpen, isUserAction);
    },

    shouldDrawerBeOpen: () => urlStateManager.shouldDrawerBeOpen(),
    setLoadInURL: () => {
        const url = new URL(window.location.href);
        url.searchParams.set('load', 'true');
        urlStateManager.commitURL(url, true);
    },
    hasLoadParam: () => urlStateManager.hasLoadParam(),

    // Export the manager for advanced use
    stateManager: urlStateManager,
};

export default urlHelper;
