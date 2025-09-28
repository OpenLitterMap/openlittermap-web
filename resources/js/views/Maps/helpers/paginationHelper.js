import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { pointsHelper } from './pointsHelper.js';
import { urlHelper } from './urlHelper.js';

/**
 * Pagination state manager for consistent pagination handling
 */
class PaginationStateManager {
    constructor() {
        this.state = {
            lastBbox: null,
            lastFilters: null,
            lastZoom: null,
            isResetting: false,
        };
    }

    /**
     * Check if pagination should be reset based on movement and filter changes
     */
    shouldResetPagination(currentBbox, currentZoom, currentFilters) {
        // Always reset when transitioning between view modes
        if (this.lastZoom !== null) {
            const wasInPoints = this.lastZoom >= CLUSTER_ZOOM_THRESHOLD;
            const isInPoints = currentZoom >= CLUSTER_ZOOM_THRESHOLD;

            if (wasInPoints !== isInPoints) {
                this.updateState(currentBbox, currentZoom, currentFilters);
                return true;
            }
        }

        // Reset if filters changed
        if (this.lastFilters && JSON.stringify(this.lastFilters) !== JSON.stringify(currentFilters)) {
            this.updateState(currentBbox, currentZoom, currentFilters);
            return true;
        }

        // Reset if moved more than 50% of viewport
        if (this.lastBbox && currentBbox) {
            const viewportWidth = Math.abs(this.lastBbox.right - this.lastBbox.left);
            const viewportHeight = Math.abs(this.lastBbox.top - this.lastBbox.bottom);

            const deltaX = Math.abs(currentBbox.left - this.lastBbox.left);
            const deltaY = Math.abs(currentBbox.bottom - this.lastBbox.bottom);

            if (deltaX > viewportWidth * 0.5 || deltaY > viewportHeight * 0.5) {
                this.updateState(currentBbox, currentZoom, currentFilters);
                return true;
            }
        }

        // Don't reset for small pans or minor zoom changes
        this.updateState(currentBbox, currentZoom, currentFilters);
        return false;
    }

    /**
     * Update tracking state
     */
    updateState(bbox, zoom, filters) {
        this.lastBbox = bbox ? { ...bbox } : null;
        this.lastZoom = zoom;
        this.lastFilters = filters ? { ...filters } : null;
    }

    /**
     * Reset tracking state
     */
    reset() {
        this.lastBbox = null;
        this.lastFilters = null;
        this.lastZoom = null;
        this.isResetting = false;
    }
}

const paginationState = new PaginationStateManager();

export const paginationHelper = {
    /**
     * Check if pagination controls should be shown
     */
    shouldShowPaginationControls({ mapInstance, currentZoom, totalPages, isLoadingPage }) {
        return mapInstance && currentZoom >= CLUSTER_ZOOM_THRESHOLD && totalPages > 1 && !isLoadingPage;
    },

    /**
     * Get page number from URL
     */
    getPageFromURL() {
        return parseInt(urlHelper.stateManager.getFiltersFromURL().page) || 1;
    },

    /**
     * Update page number in URL
     */
    updatePageInURL(page, isUserAction = true) {
        urlHelper.stateManager.updatePage(page, isUserAction);
    },

    /**
     * Remove page parameter from URL
     */
    removePageFromURL() {
        urlHelper.stateManager.updatePage(1, false);
    },

    /**
     * Smart pagination reset based on context
     */
    smartResetPagination({ currentPage, totalPages, pointsStats, mapInstance }) {
        if (!mapInstance) return;

        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());
        const filters = urlHelper.stateManager.getFiltersFromURL();

        // Check if reset is needed
        const shouldReset = paginationState.shouldResetPagination(bbox, zoom, filters);

        if (shouldReset) {
            // Set reset flag to prevent loops
            paginationState.isResetting = true;

            // Reset page values
            if (currentPage?.value !== undefined) {
                currentPage.value = 1;
            }
            if (totalPages?.value !== undefined) {
                totalPages.value = 1;
            }
            if (pointsStats?.value !== undefined) {
                pointsStats.value = null;
            }

            // Update URL (using replace since this is automatic)
            this.removePageFromURL();

            // Clear reset flag after next tick
            requestAnimationFrame(() => {
                paginationState.isResetting = false;
            });

            return true;
        }

        return false;
    },

    /**
     * Reset pagination state explicitly
     */
    resetPagination({ currentPage, totalPages, pointsStats }) {
        if (currentPage?.value !== undefined) {
            currentPage.value = 1;
        }
        if (totalPages?.value !== undefined) {
            totalPages.value = 1;
        }
        if (pointsStats?.value !== undefined) {
            pointsStats.value = null;
        }
        this.removePageFromURL();
        paginationState.reset();
    },

    /**
     * Load previous page with proper abort handling
     */
    async loadPreviousPage({ currentPage, isLoadingPage, loadPageData, loadPointsStats, requestState }) {
        const current = currentPage?.value ?? currentPage;
        const isLoading = isLoadingPage?.value ?? isLoadingPage;

        if (current <= 1 || isLoading || paginationState.isResetting) return;

        // Cancel any ongoing requests
        if (requestState?.pointsController) {
            requestState.pointsController.abort();
            requestState.pointsController = null;
        }
        if (requestState?.statsController) {
            requestState.statsController.abort();
            requestState.statsController = null;
        }

        if (isLoadingPage?.value !== undefined) {
            isLoadingPage.value = true;
            currentPage.value--;
            this.updatePageInURL(currentPage.value, true); // User action
        }

        try {
            const results = await Promise.allSettled([loadPageData(), loadPointsStats()]);

            // Check for errors but don't throw if stats fail
            if (results[0].status === 'rejected') {
                throw results[0].reason;
            }
        } finally {
            if (isLoadingPage?.value !== undefined) {
                isLoadingPage.value = false;
            }
        }
    },

    /**
     * Load next page with proper abort handling
     */
    async loadNextPage({ currentPage, totalPages, isLoadingPage, loadPageData, loadPointsStats, requestState }) {
        const current = currentPage?.value ?? currentPage;
        const total = totalPages?.value ?? totalPages;
        const isLoading = isLoadingPage?.value ?? isLoadingPage;

        if (current >= total || isLoading || paginationState.isResetting) return;

        // Cancel any ongoing requests
        if (requestState?.pointsController) {
            requestState.pointsController.abort();
            requestState.pointsController = null;
        }
        if (requestState?.statsController) {
            requestState.statsController.abort();
            requestState.statsController = null;
        }

        if (isLoadingPage?.value !== undefined) {
            isLoadingPage.value = true;
            currentPage.value++;
            this.updatePageInURL(currentPage.value, true); // User action
        }

        try {
            const results = await Promise.allSettled([loadPageData(), loadPointsStats()]);

            // Check for errors but don't throw if stats fail
            if (results[0].status === 'rejected') {
                throw results[0].reason;
            }
        } finally {
            if (isLoadingPage?.value !== undefined) {
                isLoadingPage.value = false;
            }
        }
    },

    /**
     * Load page data with unified request management
     */
    async loadPageData({ mapInstance, pointsStore, points, currentPage, requestState }) {
        // Get the current page value
        const page = currentPage?.value ?? currentPage;

        // Cancel any ongoing requests through unified state
        if (requestState?.pointsController) {
            requestState.pointsController.abort();
            requestState.pointsController = null;
        }

        // Clear existing points
        if (points) {
            points = pointsHelper.clearPoints(points, mapInstance);
        }

        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());

        // Get filters from URL
        const filters = urlHelper.stateManager.getFiltersFromURL();

        // Create abort controller
        const controller = new AbortController();

        if (requestState) {
            requestState.pointsController = controller;
        }

        try {
            const result = await pointsHelper.loadPointsData({
                mapInstance,
                pointsStore,
                zoom,
                bbox,
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                page: page,
                abortSignal: controller.signal,
            });

            // Only update if request wasn't aborted
            if (!controller.signal.aborted) {
                const paginationData = pointsHelper.getPaginationData(pointsStore);

                if (requestState?.pointsController === controller) {
                    requestState.pointsController = null;
                }

                return {
                    points: result,
                    paginationData,
                };
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Load page data error:', error);
            }

            if (requestState?.pointsController === controller) {
                requestState.pointsController = null;
            }

            throw error;
        }
    },

    /**
     * Load points statistics with unified request management
     */
    async loadPointsStats({ mapInstance, currentZoom, requestState }) {
        if (currentZoom < CLUSTER_ZOOM_THRESHOLD) {
            return null;
        }

        // Cancel any ongoing stats request through unified state
        if (requestState?.statsController) {
            requestState.statsController.abort();
            requestState.statsController = null;
        }

        const statsController = new AbortController();

        if (requestState) {
            requestState.statsController = statsController;
        }

        try {
            const zoom = Math.round(mapInstance.getZoom());
            const filters = urlHelper.stateManager.getFiltersFromURL();

            const stats = await pointsHelper.loadPointsStats({
                mapInstance,
                zoom,
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                abortSignal: statsController.signal,
            });

            // Only return if request wasn't aborted
            if (!statsController.signal.aborted) {
                if (requestState?.statsController === statsController) {
                    requestState.statsController = null;
                }
                return stats;
            }

            return null;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error loading points stats:', error);
            }

            if (requestState?.statsController === statsController) {
                requestState.statsController = null;
            }

            return null;
        }
    },

    /**
     * Preload adjacent pages for smoother navigation
     */
    async preloadAdjacentPages({ currentPage, totalPages, mapInstance, pointsStore }) {
        const current = currentPage?.value ?? currentPage;
        const total = totalPages?.value ?? totalPages;

        const pagesToPreload = [];

        // Preload previous page if exists
        if (current > 1) {
            pagesToPreload.push(current - 1);
        }

        // Preload next page if exists
        if (current < total) {
            pagesToPreload.push(current + 1);
        }

        // Use low priority fetch for preloading
        for (const page of pagesToPreload) {
            // Create a low-priority request
            requestIdleCallback(() => {
                this.preloadPage({ page, mapInstance, pointsStore });
            });
        }
    },

    /**
     * Preload a specific page (for cache warming)
     */
    async preloadPage({ page, mapInstance, pointsStore }) {
        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };
        const zoom = Math.round(mapInstance.getZoom());
        const filters = urlHelper.stateManager.getFiltersFromURL();

        try {
            // Make a low-priority request without rendering
            await pointsStore.PRELOAD_POINTS?.({
                zoom,
                bbox,
                layers: [],
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                page: page,
            });
        } catch (error) {
            // Silently fail for preload requests
            console.debug('Preload failed for page', page);
        }
    },

    /**
     * Get pagination state for debugging
     */
    getPaginationState() {
        return paginationState.state;
    },

    /**
     * Reset all pagination tracking
     */
    resetTracking() {
        paginationState.reset();
    },
};

export default paginationHelper;
