import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { pointsHelper } from './pointsHelper.js';

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
        const urlParams = new URLSearchParams(window.location.search);
        return parseInt(urlParams.get('page')) || 1;
    },

    /**
     * Update page number in URL
     */
    updatePageInURL(page) {
        const url = new URL(window.location.href);
        if (page > 1) {
            url.searchParams.set('page', page.toString());
        } else {
            url.searchParams.delete('page');
        }
        window.history.replaceState(null, '', url);
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
     * Reset pagination state
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
    },

    /**
     * Load previous page
     */
    async loadPreviousPage({ currentPage, isLoadingPage, loadPageData, loadPointsStats }) {
        const current = currentPage?.value ?? currentPage;
        const isLoading = isLoadingPage?.value ?? isLoadingPage;

        if (current <= 1 || isLoading) return;

        if (isLoadingPage?.value !== undefined) {
            isLoadingPage.value = true;
            currentPage.value--;
            this.updatePageInURL(currentPage.value);
        }

        try {
            await loadPageData();
            await loadPointsStats();
        } finally {
            if (isLoadingPage?.value !== undefined) {
                isLoadingPage.value = false;
            }
        }
    },

    /**
     * Load next page
     */
    async loadNextPage({ currentPage, totalPages, isLoadingPage, loadPageData, loadPointsStats }) {
        const current = currentPage?.value ?? currentPage;
        const total = totalPages?.value ?? totalPages;
        const isLoading = isLoadingPage?.value ?? isLoadingPage;

        if (current >= total || isLoading) return;

        if (isLoadingPage?.value !== undefined) {
            isLoadingPage.value = true;
            currentPage.value++;
            this.updatePageInURL(currentPage.value);
        }

        try {
            await loadPageData();
            await loadPointsStats();
        } finally {
            if (isLoadingPage?.value !== undefined) {
                isLoadingPage.value = false;
            }
        }
    },

    /**
     * Load page data
     */
    async loadPageData({ mapInstance, pointsStore, points, currentPage, currentPointsRequest }) {
        // Get the current page value (handle both ref and plain value)
        const page = currentPage?.value ?? currentPage;

        // Cancel any ongoing requests
        if (currentPointsRequest?.value) {
            currentPointsRequest.value.abort();
            currentPointsRequest.value = null;
        } else if (currentPointsRequest?.abort) {
            currentPointsRequest.abort();
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
        const filters = pointsHelper.getFiltersFromURL();

        // Create abort controller
        const controller = new AbortController();

        if (currentPointsRequest?.value !== undefined) {
            currentPointsRequest.value = controller;
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

            // Only update if request is still current
            const isCurrentRequest = currentPointsRequest?.value === controller || currentPointsRequest === controller;

            if (isCurrentRequest) {
                const paginationData = pointsHelper.getPaginationData(pointsStore);

                if (currentPointsRequest?.value !== undefined) {
                    currentPointsRequest.value = null;
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

            const isCurrentRequest = currentPointsRequest?.value === controller || currentPointsRequest === controller;

            if (isCurrentRequest && currentPointsRequest?.value !== undefined) {
                currentPointsRequest.value = null;
            }

            throw error;
        }
    },

    /**
     * Load points statistics
     */
    async loadPointsStats({ mapInstance, currentZoom, currentStatsRequest }) {
        if (currentZoom < CLUSTER_ZOOM_THRESHOLD) {
            return null;
        }

        // Cancel any ongoing stats request
        if (currentStatsRequest?.value) {
            currentStatsRequest.value.abort();
            currentStatsRequest.value = null;
        } else if (currentStatsRequest?.abort) {
            currentStatsRequest.abort();
        }

        try {
            const statsController = new AbortController();

            if (currentStatsRequest?.value !== undefined) {
                currentStatsRequest.value = statsController;
            }

            const zoom = Math.round(mapInstance.getZoom());
            const filters = pointsHelper.getFiltersFromURL();

            const stats = await pointsHelper.loadPointsStats({
                mapInstance,
                zoom,
                year: filters.year,
                fromDate: filters.fromDate,
                toDate: filters.toDate,
                username: filters.username,
                abortSignal: statsController.signal,
            });

            // Only return if request is still current
            const isCurrentRequest =
                currentStatsRequest?.value === statsController || currentStatsRequest === statsController;

            if (isCurrentRequest) {
                if (currentStatsRequest?.value !== undefined) {
                    currentStatsRequest.value = null;
                }
                return stats;
            }

            return null;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error loading points stats:', error);
            }
            return null;
        }
    },
};
