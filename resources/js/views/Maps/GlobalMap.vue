<script setup>
import {onMounted} from "vue";
import { useLoading } from 'vue-loading-overlay';
const $loading = useLoading();

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import { useGlobalStore } from "../../stores/maps/global/index.js";
import { useCleanupStore } from "../../stores/cleanups/index.js";
import { useMerchantStore } from "../../stores/littercoin/merchants/index.js";
import {MAX_ZOOM, MIN_ZOOM} from "../../../old_js/constants/index.js";

const globalStore = useGlobalStore();
const cleanupStore = useCleanupStore();
const merchantStore = useMerchantStore();

var map;

onMounted(async () => {
    const loader = $loading.show({
        container: null,
    });

    const year = parseInt((new URLSearchParams(window.location.search)).get('year')) || null;

    await globalStore.GET_CLUSTERS({
        zoom: 2,
        year
    });

    await globalStore.GET_ART_DATA();
    await cleanupStore.GET_CLEANUPS();
    await merchantStore.GET_MERCHANTS_GEOJSON();

    /** 0: Hack! Bind variable outside of vue scope */
    // window.olm_map = this;

    /** 1. Create map object */
    map = L.map('openlittermap', {
        center: [0, 0],
        zoom: MIN_ZOOM,
        scrollWheelZoom: false,
        smoothWheelZoom: true,
        smoothSensitivity: 2
    });

    const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
        maxZoom: MAX_ZOOM,
        minZoom: MIN_ZOOM
    }).addTo(map);

    loader.hide();
});
</script>

<template>
    <div class="global-map-container">
        <!-- The map & data -->
        <div
            id="openlittermap"
            ref="openlittermap"
        />
    </div>
</template>

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
