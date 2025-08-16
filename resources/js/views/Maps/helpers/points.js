import { addGlifyPoints, removeGlifyPoints } from './glifyHelpers.js';
import { popupHelper } from './popup.js';
import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';

export const pointsHelper = {
    /**
     * Handle points view (zoom >= CLUSTER_ZOOM_THRESHOLD)
     */
    async handlePointsView({
        mapInstance,
        pointsStore,
        clusters,
        prevZoom,
        zoom,
        bbox,
        year,
        fromDate,
        toDate,
        username,
        t,
        page = 1,
        abortSignal = null,
    }) {
        // Clear cluster layer if we were in cluster mode
        if (prevZoom < CLUSTER_ZOOM_THRESHOLD) {
            clusters.clearLayers();
        }

        // const layers = getActiveLayers();
        const layers = [];

        try {
            // Use pointsStore for points data
            await pointsStore.GET_POINTS({
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username,
                page,
            });

            // Add the new points using pointsStore data
            const points = addGlifyPoints(pointsStore.pointsGeojson, mapInstance, t);

            // If there is a photo id in the url, open it
            const urlParams = new URLSearchParams(window.location.search);
            const photoId = parseInt(urlParams.get('photo'));

            if (photoId && pointsStore.pointsGeojson.features.length) {
                const feature = pointsStore.pointsGeojson.features.find((f) => f.properties.id === photoId);

                if (feature) {
                    popupHelper.renderLeafletPopup(
                        feature,
                        [feature.geometry.coordinates[1], feature.geometry.coordinates[0]], // [lat, lng] for Leaflet
                        t,
                        mapInstance
                    );
                }
            }

            return points;
        } catch (error) {
            console.log('get points error', error);
            return null;
        }
    },

    /**
     * Load points data for pagination
     */
    async loadPointsData({
        mapInstance,
        pointsStore,
        zoom,
        bbox,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        page = 1,
        abortSignal = null,
    }) {
        const layers = [];

        try {
            await pointsStore.GET_POINTS({
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username,
                page,
            });

            // Add the new points
            const points = addGlifyPoints(pointsStore.pointsGeojson, mapInstance);

            return points;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Load points data error:', error);
            }
            throw error;
        }
    },

    /**
     * Clear points from map
     */
    clearPoints(points, mapInstance) {
        if (points) {
            removeGlifyPoints(points, mapInstance);
        }
        return null;
    },

    /**
     * Remove points-related URL parameters when zooming out
     */
    cleanupPointsURL() {
        const url = new URL(window.location.href);
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('username');
        url.searchParams.delete('photo');
        url.searchParams.delete('page');
        window.history.pushState(null, '', url);
    },

    /**
     * Load statistics for points in current view
     */
    async loadPointsStats({
        mapInstance,
        zoom,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        abortSignal = null,
    }) {
        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };

        // Build stats request parameters
        const params = new URLSearchParams({
            zoom: zoom.toString(),
            'bbox[left]': bbox.left.toString(),
            'bbox[bottom]': bbox.bottom.toString(),
            'bbox[right]': bbox.right.toString(),
            'bbox[top]': bbox.top.toString(),
        });

        // Add optional filters
        if (year) params.append('year', year.toString());
        if (fromDate) params.append('from', fromDate);
        if (toDate) params.append('to', toDate);
        if (username) params.append('username', username);

        const response = await fetch(`/api/points/stats?${params.toString()}`, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            signal: abortSignal,
        });

        if (response.ok) {
            const data = await response.json();
            return data.data;
        } else {
            console.error('Failed to load points stats:', response.status);
            return null;
        }
    },

    /**
     * Check if we should show points (above cluster zoom threshold)
     */
    shouldShowPoints(zoom) {
        return zoom >= CLUSTER_ZOOM_THRESHOLD;
    },

    /**
     * Get points data from store
     */
    getPointsData(pointsStore) {
        return pointsStore.pointsGeojson;
    },

    /**
     * Get pagination data from store
     */
    getPaginationData(pointsStore) {
        return pointsStore.pointsPagination;
    },

    /**
     * Update pagination in URL
     */
    updatePageInURL(page) {
        const url = new URL(window.location.href);
        if (page > 1) {
            url.searchParams.set('page', page.toString());
        } else {
            url.searchParams.delete('page');
        }
        window.history.pushState(null, '', url.toString());
    },

    /**
     * Remove page parameter from URL
     */
    removePageFromURL() {
        const url = new URL(window.location.href);
        url.searchParams.delete('page');
        window.history.pushState(null, '', url.toString());
    },

    /**
     * Get filters from URL
     */
    getFiltersFromURL() {
        const searchParams = new URLSearchParams(window.location.search);
        return {
            year: parseInt(searchParams.get('year')) || null,
            fromDate: searchParams.get('fromDate') || null,
            toDate: searchParams.get('toDate') || null,
            username: searchParams.get('username') || null,
            page: parseInt(searchParams.get('page')) || 1,
        };
    },

    /**
     * Get popup options for points
     */
    getPopupOptions() {
        return popupHelper.popupOptions;
    },

    /**
     * Get popup content for a point feature
     */
    getPopupContent(properties, url, t) {
        return popupHelper.getContent(properties, url, t);
    },
};
