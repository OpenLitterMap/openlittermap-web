<template>
    <div class="global-map-container">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <global-map v-else />
    </div>
</template>

<script>
import GlobalMap from './GlobalMap'
// import OldGlobalMap from './OldGlobalMap'
import Mapbox from "./Mapbox";

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'GlobalMapContainer',
    components: { Loading, GlobalMap, Mapbox },
    created ()
    {
        this.$store.dispatch('GLOBAL_MAP_DATA', 'one-week');
    },
    computed: {

        /**
         * Show loading when changing dates
         */
        loading ()
        {
            return this.$store.state.globalmap.loading;
        }
    }
}
</script>

<style scoped>
    @import '~leaflet/dist/leaflet.css';

    .global-map-container {
        height: calc(100% - 72px);
        margin: 0;
        position: relative;
        z-index: 1;
    }
</style>
