<template>
    <div class="team-map-container">
        <l-map :zoom="zoom" :center="center" :minZoom="1">
            <l-tile-layer :url="url" :attribution="attribution" />
            <v-marker-cluster v-if="geojson.length > 0">
                <l-marker v-for="i in geojson" :lat-lng="i.properties.latlng" :key="i.properties.id">
                    <l-popup :content="content(i.properties.img, i.properties.text, i.properties.datetime)" />
                </l-marker>
            </v-marker-cluster>
        </l-map>
    </div>
</template>

<script>
import { LMap, LTileLayer, LMarker, LPopup } from 'vue2-leaflet';
import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster';
import i18n from '../../i18n';
import moment from 'moment';

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
            loading: true
        };
    },
    computed: {

        /**
         * From backend api request
         */
        geojson ()
        {
            return this.$store.state.teams.geojson.features;
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
        content (img, text, date)
        {
            let a = text.split(',');
            a.pop();

            let z = '';
            a.forEach(i => {
                let b = i.split(' ');

                z += i18n.t('litter.' + b[0]) + ': ' + b[1] + ' <br>';
            });

            return '<p style="margin-bottom: 5px;">' + z + ' </p><img src= "' + img + '" style="max-width: 100%;" /><p>Taken on ' + moment(date).format('LLL') +'</p>'
        }
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

    .leaflet-popup-content {
        width: 180px !important;
    }

    .lealet-popup {
        left: -106px !important;
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
