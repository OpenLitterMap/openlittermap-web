<template>
    <div class="team-map-container">
        <l-map :zoom="zoom" :center="center" :minZoom="1">
            <l-tile-layer :url="url" :attribution="attribution" />
            <v-marker-cluster v-if="geojson.length">
                <l-marker v-for="i in geojson" :lat-lng="i.properties.latlng" :key="i.properties.id">
                    <l-popup :content="content(i)" :options="options" />
                </l-marker>
            </v-marker-cluster>
        </l-map>
    </div>
</template>

<script>
import { LMap, LTileLayer, LMarker, LPopup } from 'vue2-leaflet';
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster';
import {mapHelper} from '../../maps/mapHelpers';

export default {
    name: 'TeamMap',
    components: {
        LMap,
        LTileLayer,
        LMarker,
        LPopup,
        'v-marker-cluster': Vue2LeafletMarkerCluster
    },
    created ()
    {
        this.attribution += new Date().getFullYear();
    },
    data ()
    {
        return {
            zoom: 2,
            center: L.latLng(0,0),
            url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
            attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors ',
            loading: true,
            options: mapHelper.popupOptions
        };
    },
    computed: {

        /**
         * From backend api request
         */
        geojson ()
        {
            return this.$store.state.teams.geojson
                ? this.$store.state.teams.geojson.features
                : [];
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
                '',
                ''
            );
        },
    }
};
</script>

<style lang="scss">
    @import "~leaflet.markercluster/dist/MarkerCluster.css";
    @import "~leaflet.markercluster/dist/MarkerCluster.Default.css";

    @import '../../styles/variables.scss';

    /* remove padding on mobile */
    .team-map-container {
        height: 500px;
        margin: 0;
        position: relative;
        padding-top: 1em;
    }

    @include media-breakpoint-down (sm)
    {
        .team-map-container {
            margin-left: -3em;
            margin-right: -3em;
        }

        .temp-info {
            text-align: center;
            margin-top: 1em;
        }
    }
</style>
