<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div id="openlittermap" ref="openlittermap" />

        <!-- Search Custom Tags -->
        <LiveEvents @fly-to-location="updateUrlPhotoIdAndFlyToLocation" :mapInstance="mapInstance" />
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { useLoading } from 'vue-loading-overlay';
import { useI18n } from 'vue-i18n';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import glify from 'leaflet.glify';
import { useRouter, useRoute } from 'vue-router';

import { CLUSTER_ZOOM_THRESHOLD, MAX_ZOOM, MIN_ZOOM } from './helpers/constants.js';
import { flyToLocationFromURL, updateLocationInURL, updateUrlPhotoIdAndFlyToLocation } from './helpers/urlHelpers.js';
import { createClusterIcon, onEachFeature, renderLeafletPopup } from './helpers/layerHelpers.js';

import { useGlobalMapStore } from '../../stores/maps/global/index.js';
import { useCleanupStore } from '../../stores/cleanups/index.js';
import { useMerchantStore } from '../../stores/littercoin/merchants/index.js';

import LiveEvents from '../../components/Websockets/GlobalMap/LiveEvents.vue';

const $loading = useLoading();
const { t } = useI18n();
const router = useRouter();
const route = useRoute();

const mapInstance = ref(null);
const clusters = ref(null);
const points = ref(null);
const prevZoom = ref(MIN_ZOOM);

const globalMapStore = useGlobalMapStore();
const cleanupStore = useCleanupStore();
const merchantStore = useMerchantStore();

onMounted(async () => {
    const loader = $loading.show({ container: null });
    const year = parseInt(new URLSearchParams(window.location.search).get('year')) || null;

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

    loader.hide();

    // If there is lat + long + zoom in the url, fly to that location
    flyToLocationFromURL(mapInstance.value);
});

/**
 * Remove map & layers when unmounting
 */
onBeforeUnmount(() => {
    if (mapInstance.value) {
        mapInstance.value.off('moveend', mapUpdated);

        // Remove glify points if present
        removeGlifyPoints();

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
 *
 * Get / Clear Points or Clusters based on the zoom level
 */
const mapUpdated = async () => {
    if (!mapInstance.value) return;

    updateLocationInURL(mapInstance.value);

    const bounds = mapInstance.value.getBounds();
    const bbox = {
        left: bounds.getWest(),
        bottom: bounds.getSouth(),
        right: bounds.getEast(),
        top: bounds.getNorth(),
    };
    const zoom = Math.round(mapInstance.value.getZoom());

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if ([2, 3, 4, 5].includes(zoom) && zoom === prevZoom) return;

    // Remove points when zooming out
    if (points.value) {
        clusters.value.clearLayers();
        points.value.remove();
        points.value = null;
    }

    // Get the year from url
    const searchParams = new URLSearchParams(window.location.search);
    const year = parseInt(searchParams.get('year')) || null;
    const fromDate = searchParams.get('fromDate') || null;
    const toDate = searchParams.get('toDate') || null;
    const username = searchParams.get('username') || null;

    // Get Clusters or Points
    if (zoom < CLUSTER_ZOOM_THRESHOLD) {
        // createGlobalGroups();

        // Remove photo id and filters from the url when zooming out
        const url = new URL(window.location.href);
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('username');
        url.searchParams.delete('photo');
        window.history.pushState(null, '', url);

        try {
            await globalMapStore.GET_CLUSTERS({ zoom, bbox, year });
            clusters.value.clearLayers();
            clusters.value.addData(globalMapStore.clustersGeojson);
        } catch (error) {
            console.error('get clusters error', error);
        }
    } else {
        // Clear cluster layer if we were in cluster mode
        if (prevZoom.value < CLUSTER_ZOOM_THRESHOLD) {
            clusters.value.clearLayers();
        }

        // createPointGroups()

        // const layers = getActiveLayers();
        const layers = [];

        try {
            await globalMapStore.GET_POINTS({ zoom, bbox, layers, year, fromDate, toDate, username });

            addGlifyPoints(globalMapStore.pointsGeojson);

            // If there is a photo id in the url, open it
            const urlParams = new URLSearchParams(window.location.search);
            const photoId = parseInt(urlParams.get('photo'));

            if (photoId) {
                if (!globalMapStore.pointsGeojson.features.length) {
                    return;
                }

                const feature = globalMapStore.pointsGeojson.features.find((f) => f.properties.photo_id === photoId);

                if (feature) {
                    renderLeafletPopup(
                        feature,
                        [feature.geometry.coordinates[0], feature.geometry.coordinates[1]],
                        t,
                        mapInstance.value
                    );
                }
            }
        } catch (error) {
            console.log('get points error', error);
        }
    }

    prevZoom.value = zoom;
};

function addGlifyPoints(pointsGeojson) {
    // Remove existing glify layer, just in case
    removeGlifyPoints();

    // Build array for glify
    const data = pointsGeojson.features.map((feature) => {
        return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
    });

    points.value = glify.points({
        map: mapInstance.value,
        data,
        size: 10,
        color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // 14, 209, 69 / 255
        click: (e, point) => {
            const feature = pointsGeojson.features.find((f) => {
                return f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1];
            });
            if (!feature) return;

            // Set the photoId in the url when opening a photo
            const url = new URL(window.location.href);
            url.searchParams.set('photo', feature.properties.photo_id);
            window.history.pushState(null, '', url);

            renderLeafletPopup(feature, e.latlng, t, mapInstance.value);
        },
    });
}

function removeGlifyPoints() {
    if (points.value && mapInstance.value) {
        points.value.remove();
        points.value = null;
    }
}
</script>

<style scoped>
.global-map-container {
    height: calc(100% - 72px);
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

/* target the <span> inside any cluster icon */
::v-deep(.leaflet-marker-icon.marker-cluster-large .mi span),
::v-deep(.leaflet-marker-icon.marker-cluster-medium .mi span),
::v-deep(.leaflet-marker-icon.marker-cluster-small .mi span) {
    color: #4a4a4a !important;
}
</style>
