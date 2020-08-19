<template>
    <div class="map-container">
        <l-map :zoom="zoom" :center="center" :minZoom="1">
            <l-tile-layer :url="url" :attribution="attribution" />
        </l-map>
    </div>
</template>

<script src="/js/turf.js" charset="utf-8"></script>

<script>
import { LMap, LTileLayer, LMarker, LPopup } from 'vue2-leaflet'

export default {
    name: 'CityMap',
    components: {
        LMap,
        LTileLayer,
        LMarker,
        LPopup,
    },
    created ()
    {
       // get data
    },
    data ()
    {
        return {
            loading: false,
            zoom: 2,
            center: L.latLng(0,0),
            url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
            attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors '
        };
    },
    computed: {
        /**
         * Return geojson data for map
         */
        getData ()
        {
            return [];
        }
    }
}
</script>

<style scoped>

    .map-container {
        height: calc(100% - 72px);
        margin: 0;
        position: relative;
    }

</style>