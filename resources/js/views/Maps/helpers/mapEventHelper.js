import { clustersHelper } from './clustersHelper.js';
import { pointsHelper } from './pointsHelper.js';
import { urlHelper } from './urlHelper.js';

export const mapEventHelper = {
    /**
     * Setup all map event handlers
     */
    setupMapEvents({ mapInstance, onMoveEnd, onPopupClose, onZoom }) {
        mapInstance.on('moveend', onMoveEnd);
        mapInstance.on('popupclose', onPopupClose);
        mapInstance.on('zoom', onZoom);
    },

    /**
     * Clear points from map
     */
    clearPoints(points, mapInstance) {
        return pointsHelper.clearPoints(points, mapInstance);
    },

    /**
     * Debounce map updates to prevent excessive requests
     * Now properly updates the references
     */
    debounceMapUpdate({ state, callback }) {
        // Cancel any pending updates
        if (state.updateTimeout) {
            clearTimeout(state.updateTimeout);
        }

        // Cancel any ongoing requests
        if (state.currentPointsRequest) {
            state.currentPointsRequest.abort();
            state.currentPointsRequest = null;
        }

        if (state.currentStatsRequest) {
            state.currentStatsRequest.abort();
            state.currentStatsRequest = null;
        }

        // Set new timeout
        state.updateTimeout = setTimeout(async () => {
            await callback();
        }, 300);
    },

    /**
     * Perform the actual map update
     */
    async performUpdate({ mapInstance, clustersStore, pointsStore, clusters, points, prevZoom, currentPage, t }) {
        if (!mapInstance) return { points, prevZoom };

        urlHelper.updateLocationInURL(mapInstance);

        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());

        // Check if we should skip the update (for low zoom panning)
        if (!clustersHelper.shouldReloadClusters(zoom, prevZoom)) {
            return { points, prevZoom };
        }

        // Clear existing points when changing view
        if (points) {
            points = pointsHelper.clearPoints(points, mapInstance);
        }

        // Handle transitions between cluster and points views
        if (clustersHelper.shouldShowClusters(zoom) && pointsHelper.shouldShowPoints(prevZoom)) {
            // Transitioning from points to clusters
            points = clustersHelper.handlePointsToClusterTransition(clusters, points, mapInstance);
        } else if (pointsHelper.shouldShowPoints(zoom) && clustersHelper.shouldShowClusters(prevZoom)) {
            // Transitioning from clusters to points
            clustersHelper.handleClusterToPointsTransition(clusters);
        }

        // Get filters from URL
        const filters = pointsHelper.getFiltersFromURL();

        // Load appropriate data based on zoom level
        if (clustersHelper.shouldShowClusters(zoom)) {
            points = await clustersHelper.handleClusterView({
                clustersStore,
                clusters,
                zoom,
                bbox,
                year: filters.year,
                points,
                mapInstance,
            });

            return {
                points,
                prevZoom: zoom,
                paginationData: null,
            };
        } else {
            points = await pointsHelper.handlePointsView({
                mapInstance,
                pointsStore,
                clusters,
                prevZoom,
                zoom,
                bbox,
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                t,
                page: currentPage,
            });

            const paginationData = pointsHelper.getPaginationData(pointsStore);

            return {
                points,
                prevZoom: zoom,
                paginationData,
            };
        }
    },

    /**
     * Get current view type
     */
    getCurrentViewType(zoom) {
        return pointsHelper.shouldShowPoints(zoom) ? 'points' : 'clusters';
    },
};
