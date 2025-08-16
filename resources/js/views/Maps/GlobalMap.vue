<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div id="openlittermap" ref="openlittermap" />

        <!-- Data Drawer - Always render so it can respond to URL parameters -->
        <MapDataDrawer
            :points-data="pointsStore.pointsGeojson"
            :stats-data="pointsStats"
            :current-zoom="currentZoom"
            :cluster-zoom-threshold="CLUSTER_ZOOM_THRESHOLD"
            :is-loading="isLoadingData"
        />

        <!-- Search Custom Tags -->
        <LiveEvents @fly-to-location="handleFlyToLocation" :mapInstance="mapInstance" />

        <!-- Pagination Controls -->
        <div v-if="showPaginationControls" class="pagination-controls">
            <button
                @click="loadPreviousPage"
                :disabled="!canLoadPrevious || isLoadingPage"
                class="pagination-btn"
                :class="{ disabled: !canLoadPrevious || isLoadingPage }"
            >
                <i v-if="!isLoadingPage" class="fas fa-chevron-left"></i>
                <i v-else class="fas fa-spinner fa-spin"></i>
                Previous
            </button>

            <span class="pagination-info"> Page {{ currentPage }} of {{ totalPages }} </span>

            <button
                @click="loadNextPage"
                :disabled="!canLoadNext || isLoadingPage"
                class="pagination-btn"
                :class="{ disabled: !canLoadNext || isLoadingPage }"
            >
                Next
                <i v-if="!isLoadingPage" class="fas fa-chevron-right"></i>
                <i v-else class="fas fa-spinner fa-spin"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from 'vue';
import { useLoading } from 'vue-loading-overlay';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useRouter, useRoute } from 'vue-router';

import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './helpers/constants.js';
import { flyToLocationFromURL, updateUrlPhotoIdAndFlyToLocation } from './helpers/urlHelpers.js';
import { initializeGlify } from './helpers/glifyHelpers.js';
import { mapHelper } from './helpers/mapHelper.js';
import { pointsHelper } from './helpers/points.js';
import { clustersHelper } from './helpers/clusters.js';
import './helpers/SmoothWheelZoom.js';

import { useGlobalMapStore } from '../../stores/maps/global/index.js';
import { usePointsStore } from '../../stores/points/index.js';

import LiveEvents from '../../components/Websockets/GlobalMap/LiveEvents.vue';
import MapDataDrawer from './components/MapDataDrawer.vue';

const $loading = useLoading();
const { t } = useI18n();
const router = useRouter();
const route = useRoute();

const mapInstance = ref(null);
const clusters = ref(null);
const points = ref(null);
const prevZoom = ref(MIN_ZOOM);

// Pagination state
const currentPage = ref(1);
const totalPages = ref(1);
const isLoadingPage = ref(false);
const currentZoom = ref(MIN_ZOOM);
const isLoadingData = ref(false);

// Stats state
const pointsStats = ref(null);
const isLoadingStats = ref(false);

// Request management
const updateTimeout = ref(null);
const currentPointsRequest = ref(null);
const currentStatsRequest = ref(null);

// Stores
const globalMapStore = useGlobalMapStore(); // For clusters
const pointsStore = usePointsStore(); // For points

// Computed properties for pagination
const showPaginationControls = computed(() => {
    return (
        mapInstance.value &&
        pointsHelper.shouldShowPoints(currentZoom.value) &&
        totalPages.value > 1 &&
        !isLoadingPage.value
    );
});

const canLoadPrevious = computed(() => currentPage.value > 1);
const canLoadNext = computed(() => currentPage.value < totalPages.value);

onMounted(async () => {
    const loader = $loading.show({ container: null });
    const urlParams = new URLSearchParams(window.location.search);
    const clusterFilters = clustersHelper.getClusterFiltersFromURL();
    const initialPage = parseInt(urlParams.get('page')) || 1;

    // Load initial cluster data
    await clustersHelper.loadClusters({
        globalMapStore,
        zoom: 2,
        year: clusterFilters.year,
    });

    mapInstance.value = L.map('openlittermap', {
        center: [0, 0],
        zoom: MIN_ZOOM,
        scrollWheelZoom: false, // This is set to true in SmoothWheelZoom.js
        smoothWheelZoom: true,
        smoothSensitivity: 2,
    });

    // Set initial zoom level and page
    currentZoom.value = MIN_ZOOM;
    currentPage.value = initialPage;

    // IMPORTANT: Tell glify to expect [lng, lat] arrays (GeoJSON order)
    initializeGlify();

    // Check if we should load the map instantly at a specific location
    const shouldLoadInstantly = urlParams.get('load') === 'true';
    const hasLocationParams = urlParams.get('lat') && urlParams.get('lon') && urlParams.get('zoom');

    if (shouldLoadInstantly && hasLocationParams) {
        const lat = parseFloat(urlParams.get('lat'));
        const lon = parseFloat(urlParams.get('lon'));
        const zoom = parseFloat(urlParams.get('zoom'));

        console.log('Loading map instantly at:', { lat, lon, zoom });

        // Set the initial view without animation
        mapInstance.value.setView([lat, lon], zoom, { animate: false });

        // Update current zoom immediately
        currentZoom.value = zoom;
    }

    const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
        maxZoom: MAX_ZOOM,
        minZoom: MIN_ZOOM,
    }).addTo(mapInstance.value);

    // Initialize clusters layer
    clusters.value = L.geoJSON(null, {
        pointToLayer: clustersHelper.createClusterIcon,
        onEachFeature: (feature, layer) => clustersHelper.onEachFeature(feature, layer, mapInstance.value),
    });

    // Add initial cluster data if available
    const clustersData = clustersHelper.getClustersData(globalMapStore);
    if (clustersData?.features?.length > 0) {
        clustersHelper.addClustersToMap(clusters.value, clustersData);
        mapInstance.value.addLayer(clusters.value);
    }

    // Respond to map events
    mapInstance.value.on('moveend', mapUpdated);

    mapInstance.value.on('popupclose', (evt) => {
        console.log('popup closed', evt);
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('photo');
        window.history.replaceState(null, '', currentUrl.toString());
    });

    mapInstance.value.on('zoom', () => {
        // Update current zoom level
        currentZoom.value = Math.round(mapInstance.value.getZoom());

        console.log('Zoom changed to:', currentZoom.value);

        // Remove glify points immediately when zooming
        if (points.value) {
            points.value = pointsHelper.clearPoints(points.value, mapInstance.value);
        }

        // Reset pagination when zooming out of points view
        if (clustersHelper.shouldShowClusters(currentZoom.value)) {
            currentPage.value = 1;
            totalPages.value = 1;
            pointsStats.value = null; // Clear stats when zooming out
            pointsHelper.removePageFromURL();
        }
    });

    loader.hide();

    // If there is lat + long + zoom in the url, fly to that location
    flyToLocationFromURL(mapInstance.value);
});

/**
 * Remove map & layers when unmounting
 */
onBeforeUnmount(() => {
    // Cancel any pending requests
    if (updateTimeout.value) {
        clearTimeout(updateTimeout.value);
    }

    if (currentPointsRequest.value) {
        currentPointsRequest.value.abort();
    }

    if (currentStatsRequest.value) {
        currentStatsRequest.value.abort();
    }

    if (mapInstance.value) {
        mapInstance.value.off('moveend', mapUpdated);

        // Remove glify points if present
        if (points.value) {
            pointsHelper.clearPoints(points.value, mapInstance.value);
        }

        // Remove clusters
        clustersHelper.clearClusters(clusters.value);

        // Finally remove the map
        mapInstance.value.remove();
        mapInstance.value = null;
    }

    // Remove all params from the URL
    router.replace({ path: route.path });
});

/**
 * The user dragged or zoomed the map, or changed a category
 * Debounced to prevent excessive requests
 */
const mapUpdated = async () => {
    // Cancel any pending updates
    if (updateTimeout.value) {
        clearTimeout(updateTimeout.value);
    }

    // Cancel any ongoing requests
    if (currentPointsRequest.value) {
        currentPointsRequest.value.abort();
        currentPointsRequest.value = null;
    }

    if (currentStatsRequest.value) {
        currentStatsRequest.value.abort();
        currentStatsRequest.value = null;
    }

    // Debounce the actual update
    updateTimeout.value = setTimeout(async () => {
        await performMapUpdate();
    }, 300); // 300ms debounce
};

/**
 * Perform the actual map update with request tracking
 */
const performMapUpdate = async () => {
    // Update current zoom
    currentZoom.value = Math.round(mapInstance.value.getZoom());

    // Get page from URL or reset to 1
    const urlParams = new URLSearchParams(window.location.search);
    const urlPage = parseInt(urlParams.get('page')) || 1;

    // Use URL page if different from current (handles refresh scenarios)
    if (urlPage !== currentPage.value) {
        currentPage.value = urlPage;
    } else {
        // Reset to page 1 for new map movements
        currentPage.value = 1;
        pointsHelper.updatePageInURL(1);
    }

    isLoadingData.value = true;

    try {
        // Create abort controller for points request
        const pointsController = new AbortController();
        currentPointsRequest.value = pointsController;

        const result = await mapHelper.handleMapUpdate({
            mapInstance: mapInstance.value,
            globalMapStore,
            pointsStore,
            clusters: clusters.value,
            points: points.value,
            prevZoom: prevZoom.value,
            t,
            page: currentPage.value,
            abortSignal: pointsController.signal,
        });

        // Clear the request reference if it completed successfully
        if (currentPointsRequest.value === pointsController) {
            currentPointsRequest.value = null;
        }

        points.value = result.points;
        prevZoom.value = result.prevZoom;

        // Update pagination info from the points store
        const paginationData = pointsHelper.getPaginationData(pointsStore);
        if (paginationData && pointsHelper.shouldShowPoints(currentZoom.value)) {
            currentPage.value = paginationData.current_page || 1;
            totalPages.value = paginationData.last_page || 1;

            // Load stats after points are loaded, but only if points request wasn't cancelled
            if (currentPointsRequest.value === null || currentPointsRequest.value === pointsController) {
                await loadPointsStats();
            }
        } else {
            // Reset pagination when in cluster view
            totalPages.value = 1;
            pointsStats.value = null; // Clear stats when not in points view
            // Remove page param when in cluster view
            pointsHelper.removePageFromURL();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Map update error:', error);
        }
        // Clear request reference on any error
        currentPointsRequest.value = null;
    } finally {
        isLoadingData.value = false;
    }
};

/**
 * Load the previous page of points
 */
const loadPreviousPage = async () => {
    if (!canLoadPrevious.value || isLoadingPage.value) return;

    isLoadingPage.value = true;
    currentPage.value--;
    pointsHelper.updatePageInURL(currentPage.value);

    try {
        await loadPageData();
        await loadPointsStats(); // Load stats after page data
    } finally {
        isLoadingPage.value = false;
    }
};

/**
 * Load the next page of points
 */
const loadNextPage = async () => {
    if (!canLoadNext.value || isLoadingPage.value) return;

    isLoadingPage.value = true;
    currentPage.value++;
    pointsHelper.updatePageInURL(currentPage.value);

    try {
        await loadPageData();
        await loadPointsStats(); // Load stats after page data
    } finally {
        isLoadingPage.value = false;
    }
};

/**
 * Load data for the current page
 */
const loadPageData = async () => {
    // Cancel any ongoing requests first
    if (currentPointsRequest.value) {
        currentPointsRequest.value.abort();
        currentPointsRequest.value = null;
    }

    // Clear existing points before loading new page
    if (points.value) {
        points.value = pointsHelper.clearPoints(points.value, mapInstance.value);
    }

    const bounds = mapInstance.value.getBounds();
    const bbox = {
        left: bounds.getWest(),
        bottom: bounds.getSouth(),
        right: bounds.getEast(),
        top: bounds.getNorth(),
    };
    const zoom = Math.round(mapInstance.value.getZoom());

    // Get filters from URL
    const filters = pointsHelper.getFiltersFromURL();

    // Create abort controller for this request
    const controller = new AbortController();
    currentPointsRequest.value = controller;

    try {
        const result = await pointsHelper.loadPointsData({
            mapInstance: mapInstance.value,
            pointsStore,
            zoom,
            bbox,
            year: filters.year,
            fromDate: filters.fromDate,
            toDate: filters.toDate,
            username: filters.username,
            page: currentPage.value,
            abortSignal: controller.signal,
        });

        // Only update if this request is still current
        if (currentPointsRequest.value === controller) {
            points.value = result;

            // Update pagination info
            const paginationData = pointsHelper.getPaginationData(pointsStore);
            if (paginationData) {
                currentPage.value = paginationData.current_page || 1;
                totalPages.value = paginationData.last_page || 1;
            }

            currentPointsRequest.value = null;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Load page data error:', error);
        }
        // Clear request reference on any error
        if (currentPointsRequest.value === controller) {
            currentPointsRequest.value = null;
        }
    }
};

/**
 * Load statistics for the current points view
 */
const loadPointsStats = async () => {
    if (clustersHelper.shouldShowClusters(currentZoom.value)) {
        pointsStats.value = null;
        return;
    }

    // Cancel any ongoing stats request
    if (currentStatsRequest.value) {
        currentStatsRequest.value.abort();
    }

    isLoadingStats.value = true;

    try {
        // Create abort controller for stats request
        const statsController = new AbortController();
        currentStatsRequest.value = statsController;

        const zoom = Math.round(mapInstance.value.getZoom());

        // Get filters from URL (same as points request)
        const filters = pointsHelper.getFiltersFromURL();

        const stats = await pointsHelper.loadPointsStats({
            mapInstance: mapInstance.value,
            zoom,
            year: filters.year,
            fromDate: filters.fromDate,
            toDate: filters.toDate,
            username: filters.username,
            abortSignal: statsController.signal,
        });

        // Only process response if this request is still current
        if (currentStatsRequest.value === statsController) {
            pointsStats.value = stats;
            console.log('Loaded points stats:', pointsStats.value);

            // Clear the request reference
            currentStatsRequest.value = null;
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Error loading points stats:', error);
            pointsStats.value = null;
        }
        // Clear request reference on any error
        if (currentStatsRequest.value) {
            currentStatsRequest.value = null;
        }
    } finally {
        isLoadingStats.value = false;
    }
};

/**
 * Handle fly to location from LiveEvents
 */
const handleFlyToLocation = (location) => {
    updateUrlPhotoIdAndFlyToLocation({
        ...location,
        mapInstance: mapInstance.value,
    });
};
</script>

<style scoped>
.global-map-container {
    height: calc(100% - 80px);
    margin: 0;
    position: relative;
    z-index: 1;
}

#openlittermap {
    height: 100%;
    width: 100%;
    margin: 0;
    position: relative;
}

/* Adjust map container when drawer is open */
.global-map-container:has(.map-drawer-container.open) #openlittermap {
    margin-left: 350px;
    width: calc(100% - 350px);
    transition: all 0.3s ease;
}

@media (max-width: 640px) {
    .global-map-container:has(.map-drawer-container.open) #openlittermap {
        margin-left: 280px;
        width: calc(100% - 280px);
    }
}

/* Popup Content */
::v-deep(.leaflet-popup-content) {
    /* width: 180px !important; */
    margin: 0;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

/* Shadow Pane */
::v-deep(.leaflet-pane .leaflet-shadow-pane) {
    display: none;
}

/* Popup Content Wrapper */
::v-deep(.leaflet-popup-content-wrapper) {
    padding: 0 !important;
}

/* Popup Content Overrides */
::v-deep(.leaflet-popup-content) {
    margin: 0 !important;
    overflow-y: auto;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

::v-deep(.leaflet-popup-content div:last-of-type) {
    margin-bottom: 0 !important;
}

::v-deep(.leaflet-popup-content div:first-of-type) {
    margin-top: 0 !important;
}

/* Image Container */
::v-deep(.leaflet-litter-img-container) {
    position: relative;
    padding: 1.2em;
}

::v-deep(.leaflet-litter-img-container div) {
    color: black !important;
    font-size: 12px;
    word-break: break-word;
    max-width: 220px;
    margin: 4px 0;
}

::v-deep(.leaflet-litter-img-container .team) {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

::v-deep(.leaflet-litter-img-container .link) {
    position: absolute;
    bottom: 10px;
    right: 16px;
    font-size: 1.2rem;
}

::v-deep(.leaflet-litter-img-container .social-container) {
    display: flex;
    flex-direction: row;
    gap: 0.5rem;
    transform: translate(0, 5px);
}

::v-deep(.leaflet-litter-img-container .social-container a) {
    width: 1.5rem;
    font-size: 1.2rem;
    margin-top: 0.25rem;
}

::v-deep(.leaflet-litter-img-container .link:hover),
::v-deep(.leaflet-litter-img-container .social-container a:hover) {
    transform: scale(1.1);
}

/* Litter Image */
::v-deep(.leaflet-litter-img) {
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
    -o-object-fit: cover;
    object-fit: cover;
    cursor: pointer;
}

/* Tablet and Above */
@media (min-width: 768px) {
    ::v-deep(.leaflet-litter-img-container div) {
        font-size: 14px;
        max-width: 300px;
        margin: 10px 0;
    }

    ::v-deep(.leaflet-litter-img-container .team) {
        max-width: 280px;
    }
}

::v-deep(.leaflet-popup-close-button) {
    display: none !important;
}

/* Pagination Controls */
.pagination-controls {
    position: absolute;
    bottom: 20px;
    right: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.95);
    padding: 10px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    z-index: 1000;
}

.pagination-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 16px;
    background: #14d145;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.pagination-btn:hover:not(.disabled) {
    background: #12b83d;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(20, 209, 69, 0.3);
}

.pagination-btn.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #ccc;
}

.pagination-info {
    font-size: 14px;
    font-weight: 500;
    color: #333;
    min-width: 100px;
    text-align: center;
}

/* Mobile responsiveness */
@media (max-width: 640px) {
    .pagination-controls {
        bottom: 10px;
        right: 10px;
        padding: 8px 12px;
        gap: 10px;
    }

    .pagination-btn {
        padding: 6px 12px;
        font-size: 12px;
    }

    .pagination-info {
        font-size: 12px;
        min-width: 80px;
    }
}

/* Individual Point Markers */
::v-deep(.verified-dot) {
    width: 8px;
    height: 8px;
    background-color: #14d145;
    border-radius: 50%;
    border: 1px solid white;
}

::v-deep(.unverified-dot) {
    width: 8px;
    height: 8px;
    background-color: #6b7280;
    border-radius: 50%;
    border: 1px solid white;
}

/* Target the <span> inside any cluster icon */
::v-deep(.leaflet-marker-icon.marker-cluster-large .mi span),
::v-deep(.leaflet-marker-icon.marker-cluster-medium .mi span),
::v-deep(.leaflet-marker-icon.marker-cluster-small .mi span) {
    color: #4a4a4a !important;
}
</style>
