import { clustersHelper } from './clustersHelper.js';
import { pointsHelper } from './pointsHelper.js';
import { urlHelper } from './urlHelper.js';

/**
 * Adaptive debounce timings based on action type
 */
const DEBOUNCE_TIMINGS = {
    pan: 500, // Longer for panning to avoid too many requests
    zoom: 200, // Faster for zoom changes (more disruptive to view)
    filter: 100, // Nearly immediate for filter changes
    default: 300, // Fallback
};

/**
 * Request state manager for consistent tracking
 */
class MapRequestState {
    constructor() {
        this.state = {
            updateTimeout: null,
            pointsController: null,
            statsController: null,
            lastRequestHash: null,
            lastBbox: null,
            lastZoom: null,
            lastFilters: null,
        };
    }

    /**
     * Cancel all active operations
     */
    cancelAll() {
        if (this.state.updateTimeout) {
            clearTimeout(this.state.updateTimeout);
            this.state.updateTimeout = null;
        }

        if (this.state.pointsController) {
            this.state.pointsController.abort();
            this.state.pointsController = null;
        }

        if (this.state.statsController) {
            this.state.statsController.abort();
            this.state.statsController = null;
        }
    }

    /**
     * Get appropriate debounce timing based on change type
     */
    getDebounceDelay(prevState, currentState) {
        // Filter change
        if (prevState.lastFilters && JSON.stringify(prevState.lastFilters) !== JSON.stringify(currentState.filters)) {
            return DEBOUNCE_TIMINGS.filter;
        }

        // Zoom change
        if (prevState.lastZoom !== null && Math.abs(prevState.lastZoom - currentState.zoom) >= 1) {
            return DEBOUNCE_TIMINGS.zoom;
        }

        // Pan
        return DEBOUNCE_TIMINGS.pan;
    }

    /**
     * Update tracking state
     */
    updateTrackingState(bbox, zoom, filters) {
        this.state.lastBbox = bbox;
        this.state.lastZoom = zoom;
        this.state.lastFilters = filters;
    }

    /**
     * Generate request hash for deduplication
     */
    generateRequestHash(zoom, bbox, filters) {
        return JSON.stringify({
            z: Math.round(zoom * 10),
            b: [
                Math.round(bbox.left * 1000),
                Math.round(bbox.bottom * 1000),
                Math.round(bbox.right * 1000),
                Math.round(bbox.top * 1000),
            ],
            f: filters,
        });
    }
}

const requestState = new MapRequestState();

export const mapEventHelper = {
    /**
     * Setup all map event handlers
     */
    setupMapEvents({ mapInstance, onMoveEnd, onPopupClose, onZoom }) {
        if (!mapInstance) return;

        mapInstance.on('moveend', onMoveEnd);
        mapInstance.on('popupclose', onPopupClose);
        mapInstance.on('zoom', onZoom);

        // Add error handling for context loss
        mapInstance.on('error', (e) => {
            console.error('Map error:', e);
            if (e.error?.message?.includes('WebGL')) {
                // Handle WebGL context loss
                requestState.cancelAll();
            }
        });
    },

    /**
     * Clear points from map
     */
    clearPoints(points, mapInstance) {
        return pointsHelper.clearPoints(points, mapInstance);
    },

    /**
     * Debounce map updates with adaptive timing
     */
    debounceMapUpdate({ callback, forceDelay = null }) {
        // Cancel any pending updates
        requestState.cancelAll();

        // Determine appropriate delay
        const currentFilters = urlHelper.stateManager.getFiltersFromURL();
        const currentState = {
            zoom: null, // Will be set in callback
            filters: currentFilters,
        };

        const delay = forceDelay ?? requestState.getDebounceDelay(requestState.state, currentState);

        // Set new timeout
        requestState.state.updateTimeout = setTimeout(async () => {
            requestState.state.updateTimeout = null;
            await callback();
        }, delay);
    },

    /**
     * Perform the actual map update with proper abort handling
     */
    async performUpdate({
        mapInstance,
        clustersStore,
        pointsStore,
        clusters,
        points,
        prevZoom,
        currentPage,
        t,
        preservePage = false,
    }) {
        if (!mapInstance) return { points, prevZoom };

        // Update URL with current location
        urlHelper.updateLocationInURL(mapInstance);

        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());

        // Get current filters
        const filters = urlHelper.stateManager.getFiltersFromURL();

        // Generate request hash to check for duplicates
        const requestHash = requestState.generateRequestHash(zoom, bbox, filters);

        // Skip if this exact request was just made
        if (requestHash === requestState.state.lastRequestHash) {
            console.log('Skipping duplicate request');
            return { points, prevZoom };
        }

        // Check if we should skip the update (for low zoom panning)
        if (!clustersHelper.shouldReloadClusters(zoom, prevZoom)) {
            return { points, prevZoom };
        }

        // Determine if we should reset pagination
        const shouldResetPage =
            !preservePage &&
            pointsHelper.shouldResetPagination(
                {
                    bbox: requestState.state.lastBbox,
                    zoom: requestState.state.lastZoom,
                    filters: requestState.state.lastFilters,
                },
                { bbox, zoom, filters }
            );

        // Clear existing points when changing view significantly
        if (points && shouldResetPage) {
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

        // Update tracking state
        requestState.updateTrackingState(bbox, zoom, filters);
        requestState.state.lastRequestHash = requestHash;

        // Create abort controller for this request
        const controller = new AbortController();
        requestState.state.pointsController = controller;

        try {
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
                    abortSignal: controller.signal,
                });

                return {
                    points,
                    prevZoom: zoom,
                    paginationData: null,
                    shouldResetPagination: false,
                };
            } else {
                // Determine page to load
                const pageToLoad = shouldResetPage ? 1 : currentPage || 1;

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
                    page: pageToLoad,
                    abortSignal: controller.signal,
                });

                const paginationData = pointsHelper.getPaginationData(pointsStore);

                return {
                    points,
                    prevZoom: zoom,
                    paginationData,
                    shouldResetPagination: shouldResetPage,
                    currentPage: shouldResetPage ? 1 : pageToLoad,
                };
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Map update error:', error);
            }
            return { points, prevZoom, error };
        } finally {
            if (requestState.state.pointsController === controller) {
                requestState.state.pointsController = null;
            }
        }
    },

    /**
     * Get current view type
     */
    getCurrentViewType(zoom) {
        return pointsHelper.shouldShowPoints(zoom) ? 'points' : 'clusters';
    },

    /**
     * Get current view data
     */
    getCurrentViewData(zoom, clustersStore, pointsStore) {
        if (pointsHelper.shouldShowPoints(zoom)) {
            return pointsHelper.getPointsData(pointsStore);
        } else {
            return clustersHelper.getClustersData(clustersStore);
        }
    },

    /**
     * Clean up all resources
     */
    cleanup() {
        requestState.cancelAll();
        pointsHelper.cleanup();
    },

    /**
     * Export request state for external use
     */
    getRequestState() {
        return requestState.state;
    },

    /**
     * Force cancel all requests (for emergency cleanup)
     */
    forceCancel() {
        requestState.cancelAll();
    },
};

export default mapEventHelper;
