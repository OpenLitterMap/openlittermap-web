<template>
    <div class="map-container">

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <l-map v-else :zoom="zoom" :center="center" :minZoom="1" ref="map">
            <l-tile-layer :url="url" :attribution="attribution" />

            <l-geo-json :geojson="hex_geojson" />
        </l-map>
    </div>
</template>

<script>
import { LMap, LTileLayer, LMarker, LPopup, LGeoJson } from 'vue2-leaflet'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
// import bbox from '@turf/bbox'
// import collect from '@turf/collect'
import * as turf from '@turf/turf'

export default {
    name: 'CityMap',
    components: {
        LMap,
        LTileLayer,
        LMarker,
        LPopup,
        LGeoJson,
        Loading
    },
    async created ()
    {
        this.loading = true;

        let city = window.location.href.split('/')[6];
        await this.$store.dispatch('GET_CITY_DATA', city);

        this.loadData();

        this.loading = false;
    },
    data ()
    {
        return {
            items: null,
            loading: true,
            url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
            attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors ',
            hex_geojson: null,
            options: {
                pointToLayer: function(feature, latlng) {
                    return L.marker(latlng, {icon: L.icon({
                            iconUrl: icon,
                            iconSize: [20, 40]
                        })
                    });
                },
                onEachFeature (feature, layer)
                {
                    layer.on({
                        mouseover: highlightFeature,
                        mouseout: resetHighlight,
                        click: zoomToFeature
                    });
                },
            }
        };
    },
    computed: {

        /**
         * Where to center the map (on page load)
         */
        center ()
        {
            return this.$store.state.citymap.center;
        },

        /**
         * Return geojson data for map
         */
        geojson ()
        {
            return this.$store.state.citymap.data;
        },

        /**
         * The size of the hex units
         */
        hex ()
        {
            return this.$store.state.citymap.hex;
        },

        /**
         *
         */

        /**
         * The current level of zoom
         */
        zoom ()
        {
            return this.$store.state.citymap.zoom;
        }
    },
    methods: {

        /**
         * Get colour for hex grid
         */
        getColor (n)
        {
            return n > 60 ? '#800026' :
                n > 20 ? '#BD0026' :
                n > 10 ? '#E31A1C' :
                n > 4  ? '#FD8D3C' :
                n > 2  ? '#FED976' :
                '#FFEDA0';
        },

        /**
         *
         */
        loadData ()
        {
            // Create a bounding box from a set of features
            let bbox = turf.bbox(this.$store.state.citymap.data);
            console.log({ bbox });

            // Create a hexgrid from our data. This needs to be filtered to only show relevant data.
            let hexgrid = turf.hexGrid(bbox, 10, { units: 'meters' });

            console.log({ hexgrid });

            console.log('geojson', this.geojson);

            // Filter Polygons by counting hex values with point in polygon and removing 0 values

            // [1. polygons, 2. points, 3. ["Item" to count], 4. [attach 'values' to the hex input]]
            // this annotates the input variable (eg. adminZone, hexgrid) with a new property Array(n) "values" counting "Item"
            // hexgrid will get an extra array, "values" with counts of "Item" that fall within the polygon of input paramater
            let aggregate = turf.collect(hexgrid, this.geojson, 'total_litter', 'values');

            console.log({ aggregate });

            let filtered = aggregate.features.filter((feature, layer) => {
                if (feature.properties.values.length > 0)
                {
                    var sum = 0;
                    for (var i = 0; i < feature.properties.values.length; i++)
                    {
                        sum += feature.properties.values[i]
                    }
                    feature.properties.total = sum;
                }

                return feature.properties.values.length > 0;
            });

            console.log({ filtered });

            this.hex_geojson = filtered;
        },

        /**
         * Style for grid hex
         */
        style (feature)
        {
            return {
                weight: 2,
                opacity: 1,
                color: 'white',
                dashArray: '3',
                fillOpacity: 0.7,
                fillColor: getColor(feature.properties.total)
            };
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
