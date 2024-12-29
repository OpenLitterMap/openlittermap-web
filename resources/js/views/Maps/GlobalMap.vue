<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div id="openlittermap" ref="openlittermap" />

        <!-- Search Custom Tags -->
        <!-- LiveEvents -->
    </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useLoading } from 'vue-loading-overlay';
const $loading = useLoading();
import { useI18n } from 'vue-i18n';
const { t } = useI18n();

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import glify from "leaflet.glify";

import {
    CLUSTER_ZOOM_THRESHOLD,
    MAX_ZOOM,
    MIN_ZOOM,
} from "./helpers/constants.js";

import { useGlobalStore } from "../../stores/maps/global/index.js";
import { useCleanupStore } from "../../stores/cleanups/index.js";
import { useMerchantStore } from "../../stores/littercoin/merchants/index.js";

import {
    createClusterIcon,
    onEachFeature,
    renderLeafletPopup
} from "./helpers/layerHelpers.js";

let points;
const clusters = ref(null);
let visiblePoints = [];
let prevZoom = MIN_ZOOM;

import { flyToLocationFromURL, updateLocationInURL } from "./helpers/urlHelpers.js";

const mapInstance = ref(null);
const globalStore = useGlobalStore();
const cleanupStore = useCleanupStore();
const merchantStore = useMerchantStore();

onMounted(async () => {
    const loader = $loading.show({ container: null });

    const year = parseInt((new URLSearchParams(window.location.search)).get('year')) || null;

    await globalStore.GET_CLUSTERS({
        zoom: 2,
        year
    });

    await globalStore.GET_ART_DATA();
    await cleanupStore.GET_CLEANUPS();
    await merchantStore.GET_MERCHANTS_GEOJSON();

    mapInstance.value = L.map('openlittermap', {
        center: [0, 0],
        zoom: MIN_ZOOM,
        scrollWheelZoom: false, // This is set to true in SmoothWheelZoom.js
        smoothWheelZoom: true,
        smoothSensitivity: 2
    });

    const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
        maxZoom: MAX_ZOOM,
        minZoom: MIN_ZOOM
    }).addTo(mapInstance.value);

    // Add layers
    clusters.value = L.geoJSON(null, {
        pointToLayer: createClusterIcon,
        onEachFeature: (feature, layer) => onEachFeature(feature, layer, mapInstance.value)
    });

    if (globalStore.clustersGeojson.features.length > 0) {
        clusters.value.addData(globalStore.clustersGeojson);
        mapInstance.value.addLayer(clusters.value);
    }

    mapInstance.value.on('moveend', mapUpdated);

    loader.hide();

    flyToLocationFromURL(mapInstance.value);
});

/**
 * The user dragged or zoomed the map, or changed a category
 */
const mapUpdated = async () => {
    updateLocationInURL(mapInstance.value);

    const bounds = mapInstance.value.getBounds();

    const bbox = {
        'left': bounds.getWest(),
        'bottom': bounds.getSouth(),
        'right': bounds.getEast(),
        'top': bounds.getNorth()
    };

    const zoom = Math.round(mapInstance.value.getZoom());

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if (zoom === 2 && zoom === prevZoom) return;
    if (zoom === 3 && zoom === prevZoom) return;
    if (zoom === 4 && zoom === prevZoom) return;
    if (zoom === 5 && zoom === prevZoom) return;

    // Remove points when zooming out
    if (points)
    {
        clusters.value.clearLayers();
        points.remove();
    }

    // Get the year from url
    const searchParams = new URLSearchParams(window.location.search);
    const year = parseInt(searchParams.get('year')) || null;
    const fromDate = searchParams.get('fromDate') || null;
    const toDate = searchParams.get('toDate') || null;
    const username = searchParams.get('username') || null;

    // Get Clusters or Points
    if (zoom < CLUSTER_ZOOM_THRESHOLD)
    {
        // createGlobalGroups();

        // Remove photo id and filters from the url when zooming out
        const url = new URL(window.location.href);
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('username');
        url.searchParams.delete('photo');
        window.history.pushState(null, '', url);

        await axios.get('/global/clusters', {
            params: {
                zoom,
                bbox,
                year
            }
        })
        .then(response => {
            console.log('get_clusters.update', response);

            clusters.value.clearLayers();
            clusters.value.addData(response.data);
        })
        .catch(error => {
            console.error('get_clusters.update', error);
        });
    }
    else
    {
        // createPointGroups()

        // const layers = getActiveLayers();
        const layers = [];

        await axios.get('/global/points', {
            params: {
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username
            }
        })
        .then(response => {
            console.log('get_global_points', response);

            visiblePoints = response.data.features;

            // Clear layer if prev layer is cluster.
            if (prevZoom < CLUSTER_ZOOM_THRESHOLD) {
                clusters.value.clearLayers();
            }

            const data = response.data.features.map(feature => {
                return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
            });

            // New way using webGL
            points = glify.points({
                map: mapInstance.value,
                data,
                size: 10,
                color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // 14, 209, 69 / 255
                click:  (e, point, xy) => {
                    const feature = response.data.features.find(f => {
                        return f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1];
                    });

                    if (!feature) {
                        return;
                    }

                    // Set the photo id in the url when opening a photo
                    const url = new URL(window.location.href);
                    url.searchParams.set('photo', feature.properties.photo_id);
                    window.history.pushState(null, '', url);

                    renderLeafletPopup(feature, e.latlng, t)
                },
            });

            // If there is a photo id in the url, open it
            let urlParams = new URLSearchParams(window.location.search);
            let photoId = parseInt(urlParams.get('photo'));
            if (photoId) {
                if (!visiblePoints.length) return;
                const feature = visiblePoints.find(f => f.properties.photo_id === photoId);
                if (feature) {
                    renderLeafletPopup(
                        feature,
                        [feature.geometry.coordinates[0], feature.geometry.coordinates[1]],
                        t
                    )
                }
            }
        })
        .catch(error => {
            console.error('get_global_points', error);
        });
    }

    prevZoom = zoom;
}
</script>

<style scoped>

    @import 'leaflet/dist/leaflet.css';

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

</style>
