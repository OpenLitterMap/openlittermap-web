<template>
    <div class="profile-map-outer-container" style="padding: 0 !important;">
        <fullscreen ref="fullscreen" @change="fullscreenChange" class="profile-map-container">

            <button class="btn-map-fullscreen" @click="toggle">
                <i class="fa fa-expand" />
            </button>

            <l-map :zoom="zoom" :center="center" :minZoom="1">
                <l-tile-layer :url="url" :attribution="attribution" />
                <v-marker-cluster v-if="geojson.length > 0">
                    <l-marker v-for="i in geojson" :lat-lng="i.properties.latlng" :key="i.properties.id">
                        <l-popup :content="content(i)" :options="options"/>
                    </l-marker>
                </v-marker-cluster>
            </l-map>
        </fullscreen>
    </div>
</template>

<!-- NOTE: This very similar to TeamMap.vue - We should combine them -->
<script>
import { LMap, LTileLayer, LMarker, LPopup } from 'vue2-leaflet';
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster';
import {mapHelper} from '../../../maps/mapHelpers';

export default {
    name: 'ProfileMap',
    components: {
        LMap,
        LTileLayer,
        LMarker,
        LPopup,
        'v-marker-cluster': Vue2LeafletMarkerCluster
    },
    async created ()
    {
        this.attribution += new Date().getFullYear();

        // Todo - we need to add back a way to get data by string eg "today" or "this year", etc.
        // await this.$store.dispatch('GET_USERS_PROFILE_MAP_DATA', 'today');
    },
    data ()
    {
        return {
            zoom: 2,
            center: L.latLng(0,0),
            url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
            attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors ',
            loading: true,
            fullscreen: false,
            options: mapHelper.popupOptions
        };
    },
    computed: {
        /**
         * From backend api request
         */
        geojson ()
        {
            return this.$store.state.user.geojson.features;
        }
    },
    methods: {
        /**
         * Return html content for each popup
         *
         * Translate tags
         *
         * Format datetime (time image was taken)
         */
        content (feature)
        {
            return mapHelper.getMapImagePopupContent(
                feature.properties.img,
                feature.properties.text,
                feature.properties.datetime,
                feature.properties.picked_up,
                '',
                ''
            );
        },

        /**
         *
         */
        fullscreenChange (fullscreen)
        {
            this.fullscreen = fullscreen
        },

        /**
         *
         */
        toggle ()
        {
            this.$refs['fullscreen'].toggle() // recommended
        }
    }
};
</script>

<style lang="scss">
@import "~leaflet.markercluster/dist/MarkerCluster.css";
@import "~leaflet.markercluster/dist/MarkerCluster.Default.css";
// @import '../../../styles/variables.scss';

    .profile-map-outer-container {
        height: 20em;
    }

    .btn-map-fullscreen {
        position: absolute;
        top: 1em;
        right: 1em;
        z-index: 1234;
    }

    /* remove padding on mobile */
    .profile-map-container {
        height: 100%;
        position: relative;
    }


</style>
