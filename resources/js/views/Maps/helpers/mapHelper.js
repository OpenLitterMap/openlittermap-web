import { updateLocationInURL } from './urlHelpers.js';
import { pointsHelper } from './points.js';
import { clustersHelper } from './clusters.js';

export const mapHelper = {
    /**
     * Handle map updates when user drags or zooms
     * Delegates to appropriate helper based on zoom level
     */
    async handleMapUpdate({
        mapInstance,
        globalMapStore,
        pointsStore,
        clusters,
        points,
        prevZoom,
        t,
        page = 1,
        abortSignal = null,
    }) {
        if (!mapInstance) return { points, prevZoom };

        updateLocationInURL(mapInstance);

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
                globalMapStore,
                clusters,
                zoom,
                bbox,
                year: filters.year,
                points,
                mapInstance,
            });
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
                page,
                abortSignal,
            });
        }

        return { points, prevZoom: zoom };
    },

    /**
     * Utility method to determine current view type
     */
    getCurrentViewType(zoom) {
        return pointsHelper.shouldShowPoints(zoom) ? 'points' : 'clusters';
    },

    /**
     * Get data for current view
     */
    getCurrentViewData(zoom, globalMapStore, pointsStore) {
        if (pointsHelper.shouldShowPoints(zoom)) {
            return pointsHelper.getPointsData(pointsStore);
        } else {
            return clustersHelper.getClustersData(globalMapStore);
        }
    },
};
