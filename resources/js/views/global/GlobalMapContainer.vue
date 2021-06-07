<template>
    <div v-if="!isPhone()" class="global-map-container">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <supercluster v-else />
    </div>
    <div v-else class="global-map-container" :style="{height: mapHeight}">
        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <supercluster v-else />
    </div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
import Supercluster from './Supercluster'

export default {
    name: 'GlobalMapContainer',
    components: { Loading, Supercluster },
    data() {return {mapHeight: window.outerHeight - 72 + "px"}},
    async created ()
    {
        // this.$store.dispatch('GLOBAL_MAP_DATA', 'one-month'); // today, one-week
        await this.$store.dispatch('GET_CLUSTERS', 2);
    },
    async destroyed ()
    {
        window.removeEventListener("resize", this.resizeHandler);
    },
    computed: {
        /**
         * Show loading when changing dates
         */
        loading ()
        {
            return this.$store.state.globalmap.loading;
        }
    },
    methods: {
        resizeHandler () {
            this.mapHeight = window.innerHeight - 72 + "px";
        },
        isPhone(){
            if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)){
                window.addEventListener("resize", this.resizeHandler);
                return true;
            }
            return false;
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
