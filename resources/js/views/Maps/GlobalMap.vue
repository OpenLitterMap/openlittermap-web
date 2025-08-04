<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div id="openlittermap" ref="openlittermap" />

        <!-- Data Drawer -->
        <MapDataDrawer v-if="showDataDrawer" :points-data="globalMapStore.pointsGeojson" :is-loading="isLoadingData" />

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

        <!-- Playback Controls -->
        <div v-if="showPlaybackControls" class="playback-controls">
            <button
                @click="togglePlayback"
                class="playback-btn"
                :class="{ playing: isPlaying }"
                :title="isPlaying ? 'Pause visualization' : 'Play visualization'"
            >
                <i class="fas" :class="isPlaying ? 'fa-pause' : 'fa-play'"></i>
            </button>

            <div class="playback-info">
                <span class="playback-counter">{{ playbackIndex + 1 }} / {{ sortedPoints.length }}</span>
                <span class="playback-date" v-if="currentPlaybackPoint">
                    {{ formatPlaybackDate(currentPlaybackPoint.properties.datetime) }}
                </span>
            </div>

            <div class="playback-speed">
                <label>Speed:</label>
                <select v-model="playbackSpeed" @change="updatePlaybackSpeed">
                    <option :value="1000">Slow</option>
                    <option :value="500">Normal</option>
                    <option :value="250">Fast</option>
                    <option :value="100">Very Fast</option>
                </select>
            </div>

            <button @click="resetPlayback" class="playback-btn reset-btn" title="Reset visualization">
                <i class="fas fa-redo"></i>
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
import moment from 'moment';

import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './helpers/constants.js';
import { flyToLocationFromURL, updateUrlPhotoIdAndFlyToLocation } from './helpers/urlHelpers.js';
import { createClusterIcon, onEachFeature } from './helpers/layerHelpers.js';
import { initializeGlify, removeGlifyPoints } from './helpers/glifyHelpers.js';
import { mapHelper } from './helpers/mapHelper.js';
import './helpers/SmoothWheelZoom.js';

import { useGlobalMapStore } from '../../stores/maps/global/index.js';
import { useCleanupStore } from '../../stores/cleanups/index.js';
import { useMerchantStore } from '../../stores/littercoin/merchants/index.js';

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

// Playback state
const isPlaying = ref(false);
const playbackIndex = ref(0);
const playbackSpeed = ref(500);
const playbackInterval = ref(null);
const playbackMarkers = ref([]);
const sortedPoints = ref([]);
const currentPlaybackPoint = ref(null);

const globalMapStore = useGlobalMapStore();
const cleanupStore = useCleanupStore();
const merchantStore = useMerchantStore();

// Computed properties for pagination
const showPaginationControls = computed(() => {
    return (
        mapInstance.value && currentZoom.value >= CLUSTER_ZOOM_THRESHOLD && totalPages.value > 1 && !isLoadingPage.value
    );
});

const showDataDrawer = computed(() => {
    return (
        mapInstance.value &&
        currentZoom.value >= CLUSTER_ZOOM_THRESHOLD &&
        globalMapStore.pointsGeojson?.features?.length > 0
    );
});

const showPlaybackControls = computed(() => {
    return (
        mapInstance.value &&
        currentZoom.value >= CLUSTER_ZOOM_THRESHOLD &&
        globalMapStore.pointsGeojson?.features?.length > 0 &&
        !isLoadingData.value
    );
});

const canLoadPrevious = computed(() => currentPage.value > 1);
const canLoadNext = computed(() => currentPage.value < totalPages.value);

onMounted(async () => {
    const loader = $loading.show({ container: null });
    const urlParams = new URLSearchParams(window.location.search);
    const year = parseInt(urlParams.get('year')) || null;
    const initialPage = parseInt(urlParams.get('page')) || 1;

    // To do - consider moving this to 1 request
    await globalMapStore.GET_CLUSTERS({ zoom: 2, year });
    // await globalMapStore.GET_ART_DATA();
    // await cleanupStore.GET_CLEANUPS();
    // await merchantStore.GET_MERCHANTS_GEOJSON();

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

        // Set the initial view without animation
        mapInstance.value.setView([lat, lon], zoom, { animate: false });
    }

    const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
        maxZoom: MAX_ZOOM,
        minZoom: MIN_ZOOM,
    }).addTo(mapInstance.value);

    // Initialise & add layers
    clusters.value = L.geoJSON(null, {
        pointToLayer: createClusterIcon,
        onEachFeature: (feature, layer) => onEachFeature(feature, layer, mapInstance.value),
    });

    if (globalMapStore.clustersGeojson.features.length > 0) {
        clusters.value.addData(globalMapStore.clustersGeojson);
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

        // Remove glify points immediately when zooming
        if (points.value && points.value.remove) {
            points.value.remove();
            points.value = null;
        }

        // Reset pagination when zooming out of points view
        if (currentZoom.value < CLUSTER_ZOOM_THRESHOLD) {
            currentPage.value = 1;
            totalPages.value = 1;
            removePageFromURL();
        }
    });

    loader.hide();

    // If there is lat + long + zoom in the url, fly to that location
    flyToLocationFromURL(mapInstance.value);

    // Remove the load parameter after initial load
    const currentUrl = new URL(window.location.href);
    if (currentUrl.searchParams.has('load')) {
        currentUrl.searchParams.delete('load');
        window.history.replaceState(null, '', currentUrl.toString());
    }
});

/**
 * Remove map & layers when unmounting
 */
onBeforeUnmount(() => {
    // Stop playback if running
    if (isPlaying.value) {
        stopPlayback();
    }

    if (mapInstance.value) {
        mapInstance.value.off('moveend', mapUpdated);

        // Remove glify points if present
        removeGlifyPoints(points.value, mapInstance.value);

        // Remove clusters
        if (clusters.value) {
            clusters.value.clearLayers();
        }

        // Finally remove the map
        mapInstance.value.remove();
        mapInstance.value = null;
    }

    // Remove all params from the URL
    router.replace({ path: route.path });
});

/**
 * The user dragged or zoomed the map, or changed a category
 */
const mapUpdated = async () => {
    // Stop playback if running
    if (isPlaying.value) {
        stopPlayback();
    }

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
        updatePageInURL(1);
    }

    isLoadingData.value = true;

    const result = await mapHelper.handleMapUpdate({
        mapInstance: mapInstance.value,
        globalMapStore,
        clusters: clusters.value,
        points: points.value,
        prevZoom: prevZoom.value,
        t,
        page: currentPage.value,
    });

    points.value = result.points;
    prevZoom.value = result.prevZoom;
    isLoadingData.value = false;

    // Update pagination info from the store
    if (globalMapStore.pointsPagination && currentZoom.value >= CLUSTER_ZOOM_THRESHOLD) {
        currentPage.value = globalMapStore.pointsPagination.current_page || 1;
        totalPages.value = globalMapStore.pointsPagination.last_page || 1;

        // Sort points by datetime for playback
        updateSortedPoints();
    } else {
        // Reset pagination when in cluster view
        totalPages.value = 1;
        // Remove page param when in cluster view
        removePageFromURL();
        sortedPoints.value = [];
    }
};

/**
 * Load the previous page of points
 */
const loadPreviousPage = async () => {
    if (!canLoadPrevious.value || isLoadingPage.value) return;

    isLoadingPage.value = true;
    currentPage.value--;
    updatePageInURL(currentPage.value);

    try {
        await loadPageData();
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
    updatePageInURL(currentPage.value);

    try {
        await loadPageData();
    } finally {
        isLoadingPage.value = false;
    }
};

/**
 * Update page parameter in URL
 */
const updatePageInURL = (page) => {
    const url = new URL(window.location.href);
    if (page > 1) {
        url.searchParams.set('page', page.toString());
    } else {
        url.searchParams.delete('page');
    }
    window.history.pushState(null, '', url.toString());
};

/**
 * Remove page parameter from URL
 */
const removePageFromURL = () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    window.history.pushState(null, '', url.toString());
};

/**
 * Load data for the current page
 */
const loadPageData = async () => {
    // Clear existing points before loading new page
    if (points.value) {
        removeGlifyPoints(points.value, mapInstance.value);
        points.value = null;
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
    const searchParams = new URLSearchParams(window.location.search);
    const year = parseInt(searchParams.get('year')) || null;
    const fromDate = searchParams.get('fromDate') || null;
    const toDate = searchParams.get('toDate') || null;
    const username = searchParams.get('username') || null;

    const result = await mapHelper.handlePointsView({
        mapInstance: mapInstance.value,
        globalMapStore,
        clusters: clusters.value,
        prevZoom: prevZoom.value,
        zoom,
        bbox,
        year,
        fromDate,
        toDate,
        username,
        t,
        page: currentPage.value,
    });

    points.value = result;

    // Update pagination info
    if (globalMapStore.pointsPagination) {
        currentPage.value = globalMapStore.pointsPagination.current_page || 1;
        totalPages.value = globalMapStore.pointsPagination.last_page || 1;
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

/**
 * Update sorted points for playback
 */
const updateSortedPoints = () => {
    if (globalMapStore.pointsGeojson?.features?.length > 0) {
        sortedPoints.value = [...globalMapStore.pointsGeojson.features].sort((a, b) => {
            return new Date(a.properties.datetime) - new Date(b.properties.datetime);
        });
    } else {
        sortedPoints.value = [];
    }
};

/**
 * Toggle playback
 */
const togglePlayback = () => {
    if (isPlaying.value) {
        pausePlayback();
    } else {
        startPlayback();
    }
};

/**
 * Start playback visualization
 */
const startPlayback = () => {
    if (sortedPoints.value.length === 0) return;

    // Hide regular points
    if (points.value) {
        removeGlifyPoints(points.value, mapInstance.value);
        points.value = null;
    }

    isPlaying.value = true;

    // Start from current index or beginning
    if (playbackIndex.value >= sortedPoints.value.length) {
        playbackIndex.value = 0;
    }

    // Start the interval
    playbackInterval.value = setInterval(() => {
        if (playbackIndex.value < sortedPoints.value.length) {
            showNextPoint();
        } else {
            // Reached the end
            pausePlayback();
        }
    }, playbackSpeed.value);
};

/**
 * Pause playback
 */
const pausePlayback = () => {
    isPlaying.value = false;
    if (playbackInterval.value) {
        clearInterval(playbackInterval.value);
        playbackInterval.value = null;
    }
};

/**
 * Stop playback and restore normal view
 */
const stopPlayback = () => {
    pausePlayback();
    clearPlaybackMarkers();
    playbackIndex.value = 0;
    currentPlaybackPoint.value = null;

    // Restore regular points
    if (globalMapStore.pointsGeojson?.features?.length > 0) {
        points.value = addGlifyPoints(globalMapStore.pointsGeojson, mapInstance.value, t);
    }
};

/**
 * Reset playback to beginning
 */
const resetPlayback = () => {
    const wasPlaying = isPlaying.value;
    stopPlayback();

    if (wasPlaying) {
        startPlayback();
    }
};

/**
 * Update playback speed
 */
const updatePlaybackSpeed = () => {
    if (isPlaying.value) {
        pausePlayback();
        startPlayback();
    }
};

/**
 * Show next point in sequence
 */
const showNextPoint = () => {
    if (playbackIndex.value >= sortedPoints.value.length) return;

    const point = sortedPoints.value[playbackIndex.value];
    currentPlaybackPoint.value = point;

    // Add marker for this point
    const marker = L.circleMarker([point.geometry.coordinates[1], point.geometry.coordinates[0]], {
        radius: 8,
        fillColor: '#14d145',
        color: '#fff',
        weight: 2,
        opacity: 1,
        fillOpacity: 0.8,
    });

    // Add click handler
    marker.on('click', () => {
        renderLeafletPopup(point, [point.geometry.coordinates[1], point.geometry.coordinates[0]], t, mapInstance.value);
    });

    marker.addTo(mapInstance.value);
    playbackMarkers.value.push(marker);

    // Fade older markers
    if (playbackMarkers.value.length > 20) {
        playbackMarkers.value.forEach((m, i) => {
            const age = playbackMarkers.value.length - i;
            const opacity = Math.max(0.1, 1 - age / 20);
            m.setStyle({
                fillOpacity: opacity * 0.8,
                opacity: opacity,
            });
        });
    }

    // Pan to point if it's outside current view
    const bounds = mapInstance.value.getBounds();
    const latLng = L.latLng(point.geometry.coordinates[1], point.geometry.coordinates[0]);
    if (!bounds.contains(latLng)) {
        mapInstance.value.panTo(latLng);
    }

    playbackIndex.value++;
};

/**
 * Clear all playback markers
 */
const clearPlaybackMarkers = () => {
    playbackMarkers.value.forEach((marker) => {
        mapInstance.value.removeLayer(marker);
    });
    playbackMarkers.value = [];
};

/**
 * Format playback date
 */
const formatPlaybackDate = (datetime) => {
    return moment(datetime).format('MMM D, YYYY HH:mm');
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
</style>
