import { urlHelper } from './urlHelper.js';
import { clustersHelper } from './clustersHelper.js';
import { pointsHelper } from './pointsHelper.js';
import { mapEventHelper } from './mapEventHelper.js';
import { paginationHelper } from './paginationHelper.js';

/**
 * Unified Request State Manager
 * Single source of truth for all map requests and state
 */
class UnifiedRequestState {
    constructor() {
        this.state = {
            updateTimeout: null,
            pointsController: null,
            statsController: null,
            lastRequestHash: null,
            lastBbox: null,
            lastZoom: null,
            lastFilters: null,
            isUpdating: false,
            lastUpdateTime: 0,
        };
    }

    /**
     * Cancel all active requests and timers
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

        this.state.isUpdating = false;
    }

    /**
     * Check if we should throttle updates
     */
    shouldThrottle() {
        const now = Date.now();
        const timeSinceLastUpdate = now - this.state.lastUpdateTime;

        // Throttle if less than 100ms since last update
        return timeSinceLastUpdate < 100;
    }

    /**
     * Update last update time
     */
    markUpdated() {
        this.state.lastUpdateTime = Date.now();
    }
}

const unifiedState = new UnifiedRequestState();

export const mapHelper = {
    /**
     * Main map update handler with all optimizations
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
        forceUpdate = false,
        preservePage = false,
    }) {
        if (!mapInstance) return { points, prevZoom };

        // Throttle rapid updates unless forced
        if (!forceUpdate && unifiedState.shouldThrottle()) {
            console.log('Throttling rapid update');
            return { points, prevZoom };
        }

        // Prevent concurrent updates
        if (unifiedState.state.isUpdating && !forceUpdate) {
            console.log('Update already in progress');
            return { points, prevZoom };
        }

        unifiedState.state.isUpdating = true;
        unifiedState.markUpdated();

        // Update URL location first
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

        // Generate request hash for deduplication
        const requestHash = this.generateRequestHash(zoom, bbox, filters);

        // Skip if identical request (unless forced)
        if (!forceUpdate && requestHash === unifiedState.state.lastRequestHash) {
            console.log('Skipping duplicate request');
            unifiedState.state.isUpdating = false;
            return { points, prevZoom };
        }

        // Check if we should skip low-zoom panning
        if (!clustersHelper.shouldReloadClusters(zoom, prevZoom)) {
            unifiedState.state.isUpdating = false;
            return { points, prevZoom };
        }

        // Determine if pagination should reset
        const shouldResetPage =
            !preservePage && this.shouldResetPagination(unifiedState.state, { bbox, zoom, filters });

        // Clear points if needed
        if (points && (shouldResetPage || this.isViewTransition(prevZoom, zoom))) {
            points = pointsHelper.clearPoints(points, mapInstance);
        }

        // Handle view transitions
        if (clustersHelper.shouldShowClusters(zoom) && pointsHelper.shouldShowPoints(prevZoom)) {
            points = clustersHelper.handlePointsToClusterTransition(clusters, points, mapInstance);
        } else if (pointsHelper.shouldShowPoints(zoom) && clustersHelper.shouldShowClusters(prevZoom)) {
            clustersHelper.handleClusterToPointsTransition(clusters);
        }

        // Update tracking state
        unifiedState.state.lastBbox = bbox;
        unifiedState.state.lastZoom = zoom;
        unifiedState.state.lastFilters = filters;
        unifiedState.state.lastRequestHash = requestHash;

        // Cancel any ongoing requests
        if (unifiedState.state.pointsController) {
            unifiedState.state.pointsController.abort();
        }

        const controller = new AbortController();
        unifiedState.state.pointsController = controller;

        try {
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
                    abortSignal: controller.signal,
                });

                return {
                    points,
                    prevZoom: zoom,
                    paginationData: null,
                    viewType: 'clusters',
                };
            } else {
                // Determine page to load
                const pageToLoad = shouldResetPage ? 1 : page;

                // Update URL if page was reset
                if (shouldResetPage && page !== 1) {
                    paginationHelper.updatePageInURL(1, false);
                }

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
                    viewType: 'points',
                    currentPage: pageToLoad,
                };
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Map update error:', error);
            }
            return { points, prevZoom, error };
        } finally {
            if (unifiedState.state.pointsController === controller) {
                unifiedState.state.pointsController = null;
            }
            unifiedState.state.isUpdating = false;
        }
    },

    /**
     * Debounced map update with adaptive timing
     */
    debouncedMapUpdate(callback, eventType = 'pan') {
        // Cancel any pending update
        if (unifiedState.state.updateTimeout) {
            clearTimeout(unifiedState.state.updateTimeout);
        }

        // Determine delay based on event type
        const delays = {
            pan: 500,
            zoom: 200,
            filter: 100,
            user: 0, // Immediate for user actions
        };

        const delay = delays[eventType] || 300;

        if (delay === 0) {
            // Execute immediately for user actions
            unifiedState.cancelAll();
            callback();
        } else {
            // Debounce other events
            unifiedState.state.updateTimeout = setTimeout(() => {
                unifiedState.state.updateTimeout = null;
                callback();
            }, delay);
        }
    },

    /**
     * Load stats for current view
     */
    async loadStats({ mapInstance, zoom }) {
        if (!mapInstance || zoom < clustersHelper.CLUSTER_ZOOM_THRESHOLD) {
            return null;
        }

        // Cancel any ongoing stats request
        if (unifiedState.state.statsController) {
            unifiedState.state.statsController.abort();
        }

        const controller = new AbortController();
        unifiedState.state.statsController = controller;

        try {
            const filters = urlHelper.stateManager.getFiltersFromURL();

            const stats = await pointsHelper.loadPointsStats({
                mapInstance,
                zoom,
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                abortSignal: controller.signal,
            });

            if (!controller.signal.aborted) {
                return stats;
            }
            return null;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Stats loading error:', error);
            }
            return null;
        } finally {
            if (unifiedState.state.statsController === controller) {
                unifiedState.state.statsController = null;
            }
        }
    },

    /**
     * Handle pagination
     */
    async handlePagination({ direction, mapInstance, pointsStore, currentPage, totalPages, isLoadingPage }) {
        if (!mapInstance || isLoadingPage?.value) return;

        const page = currentPage?.value || currentPage;
        const total = totalPages?.value || totalPages;

        if (direction === 'prev' && page <= 1) return;
        if (direction === 'next' && page >= total) return;

        // Update loading state
        if (isLoadingPage?.value !== undefined) {
            isLoadingPage.value = true;
        }

        const newPage = direction === 'prev' ? page - 1 : page + 1;

        try {
            // Update URL (user action)
            paginationHelper.updatePageInURL(newPage, true);

            // Load new page data
            const result = await paginationHelper.loadPageData({
                mapInstance,
                pointsStore,
                points: null,
                currentPage: newPage,
                requestState: unifiedState.state,
            });

            // Update stats in background
            this.loadStats({ mapInstance, zoom: mapInstance.getZoom() });

            return result;
        } finally {
            if (isLoadingPage?.value !== undefined) {
                isLoadingPage.value = false;
            }
        }
    },

    /**
     * Check if view is transitioning between clusters and points
     */
    isViewTransition(prevZoom, currentZoom) {
        const wasInClusters = clustersHelper.shouldShowClusters(prevZoom);
        const isInClusters = clustersHelper.shouldShowClusters(currentZoom);
        return wasInClusters !== isInClusters;
    },

    /**
     * Check if pagination should reset
     */
    shouldResetPagination(prevState, currentState) {
        // Reset on view transition
        if (this.isViewTransition(prevState.lastZoom, currentState.zoom)) {
            return true;
        }

        // Reset on filter change
        if (JSON.stringify(prevState.lastFilters) !== JSON.stringify(currentState.filters)) {
            return true;
        }

        // Reset on significant movement (>50% of viewport)
        if (prevState.lastBbox && currentState.bbox) {
            const width = Math.abs(prevState.lastBbox.right - prevState.lastBbox.left);
            const height = Math.abs(prevState.lastBbox.top - prevState.lastBbox.bottom);

            const deltaX = Math.abs(currentState.bbox.left - prevState.lastBbox.left);
            const deltaY = Math.abs(currentState.bbox.bottom - prevState.lastBbox.bottom);

            return deltaX > width * 0.5 || deltaY > height * 0.5;
        }

        return false;
    },

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
    getCurrentViewData(zoom, globalMapStore, pointsStore) {
        if (pointsHelper.shouldShowPoints(zoom)) {
            return pointsHelper.getPointsData(pointsStore);
        } else {
            return clustersHelper.getClustersData(globalMapStore);
        }
    },

    /**
     * Force refresh current view
     */
    async forceRefresh({ mapInstance, globalMapStore, pointsStore, clusters, points, prevZoom, t, page }) {
        // Cancel all ongoing operations
        unifiedState.cancelAll();

        // Force update flag will bypass deduplication
        return this.handleMapUpdate({
            mapInstance,
            globalMapStore,
            pointsStore,
            clusters,
            points,
            prevZoom,
            t,
            page,
            forceUpdate: true,
        });
    },

    /**
     * Cleanup all resources
     */
    cleanup() {
        unifiedState.cancelAll();
        mapEventHelper.cleanup();
        pointsHelper.cleanup();
        paginationHelper.resetTracking();
    },

    /**
     * Get unified request state (for debugging)
     */
    getRequestState() {
        return unifiedState.state;
    },

    /**
     * Export all helpers for advanced usage
     */
    helpers: {
        url: urlHelper,
        clusters: clustersHelper,
        points: pointsHelper,
        events: mapEventHelper,
        pagination: paginationHelper,
    },
};

export default mapHelper;
