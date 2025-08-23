import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { addGlifyPoints, removeGlifyPoints, clearGlifyReferences } from './glifyHelpers.js';
import { popupHelper } from './popup.js';

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

        const layers = [];

        try {
            // Use pointsStore for points data with proper pagination
            const response = await pointsStore.GET_POINTS({
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

            if (photoId && pointsStore.pointsGeojson?.features?.length) {
                const feature = pointsStore.pointsGeojson.features.find((f) => f.properties?.id === photoId);

                if (feature) {
                    popupHelper.renderLeafletPopup(
                        feature,
                        [feature.geometry.coordinates[1], feature.geometry.coordinates[0]],
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
            clearGlifyReferences(); // Clear stored references
        }
        return null;
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

        try {
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
                return data.data || null;
            } else {
                console.error('Failed to load points stats:', response.status);
                return null;
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error loading points stats:', error);
            }
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
        // Check for pagination in multiple possible locations
        if (pointsStore.pointsPagination) {
            return pointsStore.pointsPagination;
        }

        if (pointsStore.pagination) {
            return pointsStore.pagination;
        }

        // Check in the geojson meta
        if (pointsStore.pointsGeojson?.meta) {
            return {
                current_page: pointsStore.pointsGeojson.meta.current_page || 1,
                last_page: pointsStore.pointsGeojson.meta.last_page || 1,
                per_page: pointsStore.pointsGeojson.meta.per_page || 300,
                total: pointsStore.pointsGeojson.meta.total || 0,
            };
        }

        // Check if pagination is at root of pointsGeojson
        if (pointsStore.pointsGeojson?.current_page !== undefined) {
            return {
                current_page: pointsStore.pointsGeojson.current_page || 1,
                last_page: pointsStore.pointsGeojson.last_page || 1,
                per_page: pointsStore.pointsGeojson.per_page || 300,
                total: pointsStore.pointsGeojson.total || 0,
            };
        }

        return null;
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
