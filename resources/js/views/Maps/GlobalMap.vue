<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div id="openlittermap" ref="openlittermap" />

        <!-- Map Drawer - Always render so it can respond to URL parameters -->
        <MapDrawer
            :stats-data="pointsStats"
            :current-zoom="currentZoom"
            :cluster-zoom-threshold="CLUSTER_ZOOM_THRESHOLD"
            :is-loading="isLoadingData"
            :is-open="isDrawerOpen"
            @toggle="handleDrawerToggle"
            @highlight-category="handleCategoryHighlight"
            @highlight-object="handleObjectHighlight"
        />

        <!-- Search Custom Tags -->
        <LiveEvents @fly-to-location="handleFlyToLocation" :mapInstance="mapInstance" />

        <!-- Pagination Controls -->
        <PaginationControls
            v-if="showPaginationControls"
            :current-page="currentPage"
            :total-pages="totalPages"
            :is-loading="isLoadingPage"
            @previous="loadPreviousPage"
            @next="loadNextPage"
        />
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import { useLoading } from 'vue-loading-overlay';
import { useI18n } from 'vue-i18n';
import 'leaflet/dist/leaflet.css';
import { useRouter, useRoute } from 'vue-router';

// Constants
import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './helpers/constants.js';

// Helpers
import { urlHelper } from './helpers/urlHelper.js';
import { mapLifecycleHelper } from './helpers/mapLifecycleHelper.js';
import { mapEventHelper } from './helpers/mapEventHelper.js';
import { paginationHelper } from './helpers/paginationHelper.js';
import { initializeGlify, highlightPointsByCategory, highlightPointsByObject } from './helpers/glifyHelpers.js';

// Stores
import { useClustersStore } from '../../stores/maps/clusters/index.js';
import { usePointsStore } from '../../stores/maps/points/index.js';

// Components
import LiveEvents from '../../components/Websockets/GlobalMap/LiveEvents.vue';
import MapDrawer from './components/MapDrawer.vue';
import PaginationControls from './components/PaginationControls.vue';

const $loading = useLoading();
const { t } = useI18n();
const router = useRouter();
const route = useRoute();

// Map instance and layers
const mapInstance = ref(null);
const clusters = ref(null);
const points = ref(null);
const prevZoom = ref(MIN_ZOOM);

// Drawer state
const isDrawerOpen = ref(false);
const userHasInteractedWithDrawer = ref(false);

// Pagination state
const currentPage = ref(1);
const totalPages = ref(1);
const isLoadingPage = ref(false);
const currentZoom = ref(MIN_ZOOM);
const isLoadingData = ref(false);

// Stats state
const pointsStats = ref(null);

// Request management
const updateTimeout = ref(null);
const currentPointsRequest = ref(null);
const currentStatsRequest = ref(null);

// Stores
const clustersStore = useClustersStore();
const pointsStore = usePointsStore();

// Computed properties
const showPaginationControls = computed(() =>
    paginationHelper.shouldShowPaginationControls({
        mapInstance: mapInstance.value,
        currentZoom: currentZoom.value,
        totalPages: totalPages.value,
        isLoadingPage: isLoadingPage.value,
    })
);

// Initialize map on mount
onMounted(async () => {
    const loader = $loading.show({ container: null });

    try {
        const initialData = await mapLifecycleHelper.initializeMap({
            clustersStore,
            $loading,
            t,
        });

        mapInstance.value = initialData.mapInstance;
        clusters.value = initialData.clusters;
        currentZoom.value = initialData.currentZoom;
        currentPage.value = initialData.currentPage;

        // Initialize glify
        initializeGlify();

        // Set up event handlers
        mapEventHelper.setupMapEvents({
            mapInstance: mapInstance.value,
            onMoveEnd: mapUpdated,
            onPopupClose: handlePopupClose,
            onZoom: handleZoom,
        });

        // Check URL for drawer state preference
        const urlParams = new URLSearchParams(window.location.search);
        const openParam = urlParams.get('open');
        const hasLoad = urlParams.get('load') === 'true';

        if (openParam === 'true') {
            isDrawerOpen.value = true;
            userHasInteractedWithDrawer.value = true;
            // Ensure load=true is set if not already
            if (!hasLoad) {
                urlHelper.setLoadInURL();
            }
        } else if (openParam === 'false') {
            isDrawerOpen.value = false;
            userHasInteractedWithDrawer.value = true;
        }
        // If no param, leave userHasInteractedWithDrawer as false for auto-open logic
    } finally {
        loader.hide();
    }

    // Fly to location if specified in URL
    urlHelper.flyToLocationFromURL(mapInstance.value);
});

// Cleanup on unmount
onBeforeUnmount(() => {
    mapLifecycleHelper.cleanup({
        mapInstance: mapInstance.value,
        clusters: clusters.value,
        points: points.value,
        updateTimeout: updateTimeout.value,
        currentPointsRequest: currentPointsRequest.value,
        currentStatsRequest: currentStatsRequest.value,
        router,
        route,
    });
});

// Handle drawer toggle - user initiated
const handleDrawerToggle = () => {
    isDrawerOpen.value = !isDrawerOpen.value;
    userHasInteractedWithDrawer.value = true;
    urlHelper.updateDrawerStateInURL(isDrawerOpen.value);
};

// Watch for zoom changes to manage drawer
watch(currentZoom, (newZoom, oldZoom) => {
    // Only auto-manage drawer if user hasn't interacted with it
    if (!userHasInteractedWithDrawer.value) {
        if (newZoom >= CLUSTER_ZOOM_THRESHOLD && oldZoom < CLUSTER_ZOOM_THRESHOLD) {
            // Entering points view - will open drawer after data loads
            // Don't open immediately, wait for stats to load
            urlHelper.setLoadInURL();
        } else if (newZoom < CLUSTER_ZOOM_THRESHOLD && oldZoom >= CLUSTER_ZOOM_THRESHOLD) {
            // Leaving points view - close drawer
            isDrawerOpen.value = false;
        }
    }
});

// Watch for stats data loading to auto-open drawer
watch([pointsStats, isLoadingData, currentZoom], ([stats, loading, zoom]) => {
    // Auto-open drawer when:
    // 1. We're in points view (zoom >= threshold)
    // 2. Stats are loaded
    // 3. Not currently loading
    // 4. User hasn't manually interacted with drawer
    if (
        zoom >= CLUSTER_ZOOM_THRESHOLD &&
        stats &&
        !loading &&
        !userHasInteractedWithDrawer.value &&
        !isDrawerOpen.value
    ) {
        isDrawerOpen.value = true;
        urlHelper.updateDrawerStateInURL(true);
    }
});

const updateState = ref({
    updateTimeout: null,
    currentPointsRequest: null,
    currentStatsRequest: null,
});

// Handle popup close
const handlePopupClose = () => {
    urlHelper.removePhotoFromURL();
};

// Handle zoom changes
const handleZoom = () => {
    const newZoom = Math.round(mapInstance.value.getZoom());
    currentZoom.value = newZoom;

    // Clear points immediately when zooming
    if (points.value) {
        points.value = mapEventHelper.clearPoints(points.value, mapInstance.value);
    }

    // Reset pagination when leaving points view
    if (newZoom < CLUSTER_ZOOM_THRESHOLD) {
        paginationHelper.resetPagination({
            currentPage,
            totalPages,
            pointsStats,
        });
    }
};

// Map update handler (debounced)
const mapUpdated = async () => {
    mapEventHelper.debounceMapUpdate({
        state: updateState.value,
        callback: performMapUpdate,
    });
};

const handleCategoryHighlight = (category) => {
    // Only highlight if we're in points view
    if (currentZoom.value >= CLUSTER_ZOOM_THRESHOLD && mapInstance.value) {
        highlightPointsByCategory(category, mapInstance.value);
    }
};

const handleObjectHighlight = (objectData) => {
    // Only highlight if we're in points view
    if (currentZoom.value >= CLUSTER_ZOOM_THRESHOLD && mapInstance.value) {
        highlightPointsByObject(objectData, mapInstance.value);
    }
};

const performMapUpdate = async () => {
    currentZoom.value = Math.round(mapInstance.value.getZoom());

    // Handle pagination
    const urlPage = paginationHelper.getPageFromURL();
    if (urlPage !== currentPage.value) {
        currentPage.value = urlPage;
    } else {
        currentPage.value = 1;
        paginationHelper.updatePageInURL(1);
    }

    isLoadingData.value = true;

    try {
        const result = await mapEventHelper.performUpdate({
            mapInstance: mapInstance.value,
            clustersStore,
            pointsStore,
            clusters: clusters.value,
            points: points.value,
            prevZoom: prevZoom.value,
            currentPage: currentPage.value,
            t,
        });

        points.value = result.points;
        prevZoom.value = result.prevZoom;

        // Update pagination info
        if (result.paginationData) {
            currentPage.value = result.paginationData.current_page;
            totalPages.value = result.paginationData.last_page;

            // Load stats for points view
            if (currentZoom.value >= CLUSTER_ZOOM_THRESHOLD) {
                await loadPointsStats();
            }
        } else {
            paginationHelper.resetPagination({
                currentPage,
                totalPages,
                pointsStats,
            });
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Map update error:', error);
        }
    } finally {
        isLoadingData.value = false;
    }
};

// Load previous page
const loadPreviousPage = async () => {
    await paginationHelper.loadPreviousPage({
        currentPage,
        isLoadingPage,
        loadPageData,
        loadPointsStats,
    });
};

// Load next page
const loadNextPage = async () => {
    await paginationHelper.loadNextPage({
        currentPage,
        totalPages,
        isLoadingPage,
        loadPageData,
        loadPointsStats,
    });
};

// Load page data
const loadPageData = async () => {
    const result = await paginationHelper.loadPageData({
        mapInstance: mapInstance.value,
        pointsStore,
        points: points.value,
        currentPage: currentPage.value,
        currentPointsRequest,
    });

    points.value = result.points;
    if (result.paginationData) {
        currentPage.value = result.paginationData.current_page;
        totalPages.value = result.paginationData.last_page;
    }
};

// Load points statistics
const loadPointsStats = async () => {
    const stats = await paginationHelper.loadPointsStats({
        mapInstance: mapInstance.value,
        currentZoom: currentZoom.value,
        currentStatsRequest,
    });
    pointsStats.value = stats;
};

// Handle fly to location from LiveEvents
const handleFlyToLocation = (location) => {
    urlHelper.updateUrlPhotoIdAndFlyToLocation({
        ...location,
        mapInstance: mapInstance.value,
    });
};
</script>

<style scoped>
@import './styles/GlobalMap.css';
</style>
