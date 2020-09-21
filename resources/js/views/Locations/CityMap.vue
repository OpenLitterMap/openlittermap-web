<template>
    <div class="map-container">

        <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

        <l-map v-else :zoom="zoom" :center="center" :minZoom="1" ref="map">
            <l-tile-layer :url="url" :attribution="attribution" />

            <l-geo-json :geojson="aggregate" :options="options" />

            <!-- Top Right - Show polygon count -->
            <l-control class="info leaflet-control">
                <p class="info-title">{{ this.hex }} meter hex grid</p>
                <!-- Todo - pluralize with filter -->
                <strong v-if="isHover">{{ this.display_count }} images</strong>
                <strong v-else>Hover over grid to count</strong>
            </l-control>


            <!-- Top Right - Control Layers -->
            <l-control-layers position="topright" @ready="addDataToLayerGroups" />

            <l-layer-group
                ref="groups"
                layer-type="overlay"
                v-for="cat in categories"
                :name="cat.name"
                :key="cat.id"
                :checked="cat.selected"
            />

            <!-- Bottom Left - Legend -->
            <l-control class="info legend" position="bottomleft">
                <span v-for="grade, index in grades" class="flex">
                    <i :style="getGradeColor(grade)" />
                    <p v-html="getGradeText(index)" />
                </span>
            </l-control>
        </l-map>
    </div>
</template>

<script>
import {
    LMap,
    LTileLayer,
    LMarker,
    LPopup,
    LGeoJson,
    LControl,
    LControlLayers,
    LLayerGroup
} from 'vue2-leaflet'
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'
// import bbox from '@turf/bbox' // todo - import functions instead of *
// import collect from '@turf/collect'
// import hexGrid from '@turf/hex-grid'
import * as turf from '@turf/turf'

const defaultStyle = {
    weight: 1,
    opacity: 0.7,
    color: '#999',
    fillOpacity: 0.7
}

const hoverStyle = {
    weight: 5,
    color: '#666',
    dashArray: '',
    fillOpacity: 0.7
}

function onEachFeature (feature, layer)
{
    // todo - layer.on('click', () => ..... open popup with statistics

    layer.on('mouseover', (e) => {
        this.isHover = true;
        this.display_count = e.sourceTarget.feature.properties.total;
        layer.setStyle(hoverStyle);
    });

    layer.on('mouseout', () => {
        this.isHover = false;
        layer.setStyle(defaultStyle);
    });
}

export default {
    name: 'CityMap',
    components: {
        LMap,
        LTileLayer,
        LMarker,
        LPopup,
        LGeoJson,
        LControl,
        LControlLayers,
        LLayerGroup,
        Loading
    },
    async created ()
    {
        this.loading = true;

        let city = window.location.href.split('/')[6];
        await this.$store.dispatch('GET_CITY_DATA', city);

        this.loading = false;
    },
    data ()
    {
        return {
            display_count: 0,
            grades: [1, 3, 6, 10, 20], // todo - generate these numbers dynamically
            isHover: false, // is the grid being hovered
            loading: true,
            url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
            attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors ',
            options: {
                filter: function(feature, layer)
                {
                    if (feature.properties.values.length > 0)
                    {
                        let sum = 0;
                        feature.properties.values.map(value => {
                            sum += value;
                        });
                        feature.properties.total = sum; // total photos per grid
                    }

                    return feature.properties.values.length > 0;
                },
                onEachFeature: onEachFeature.bind(this),
                style: this.style,
            },
            categories: [
                { id: 1,  name: 'Smoking', selected: false },
                { id: 2,  name: 'Food', selected: false },
                { id: 3,  name: 'Alcohol', selected: false },
                { id: 4,  name: 'Coffee', selected: false },
                { id: 5,  name: 'SoftDrinks', selected: true },
                { id: 6,  name: 'Sanitary', selected: false },
                { id: 7,  name: 'Other', selected: false },
                { id: 8,  name: 'Coastal', selected: false },
                { id: 9,  name: 'Brands', selected: false },
                { id: 10, name: 'Dumping', selected: false },
                { id: 11, name: 'PetSurprise', selected: false },
                { id: 12, name: 'Industrial', selected: false }
            ]
        };
    },
    computed: {

        /**
         * From our input geojson object,
         * 1. Create bounding box
         * 2. Create hexgrid with bounding box
         * 3. Count point-in-polygon to filter 0 values
         */
        aggregate ()
        {
            // Create a bounding box from our set of features
            let bbox = turf.bbox(this.geojson);

            // Create a hexgrid from our data. This needs to be filtered to only show relevant data.
            let hexgrid = turf.hexGrid(bbox, 50, { units: 'meters' });

            // we need to parse here to avoid copying the object as shallow copies
            // see https://github.com/Turfjs/turf/issues/1914
            hexgrid = JSON.parse(JSON.stringify(hexgrid));

            // To filter the hexgrid, we need to find hex values with point in polygon and remove 0 values
            // 1. Hexgrid, 2. Points, 3. Our column value, 4. New value will be appended to the hexgrid
            return turf.collect(hexgrid, this.geojson, 'total_litter', 'values');
        },

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
         * The current level of zoom
         */
        zoom ()
        {
            return this.$store.state.citymap.zoom;
        }
    },
    methods: {

        /**
         *
         */
        addDataToLayerGroups ()
        {
            console.log('add_data_to_layer_groups', this.$refs.groups);

            this.$refs.groups.map(group => {
                console.log(group.name);
            });
        },

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
         * Return the colour for each grade
         */
        getGradeColor (i)
        {
            return 'background:' + this.getColor(i);
        },

        /**
         * Return the text for each grade
         */
        getGradeText (i)
        {
            let from = this.grades[i];
            let to = this.grades[i + 1];

            if (to) return from + '&ndash;' + to;

            return from + '+';
        },

        // /**
        //  *
        //  */
        // highlightFeature (e)
        // {
        //     console.log('highlight', e);
        //
        //     let layer = e.target;
        //
        //     layer.setStyle({
        //         weight: 5,
        //         color: '#666',
        //         dashArray: '',
        //         fillOpacity: 0.7
        //     });
        //
        //     // if (! L.Browser.ie && ! L.Browser.opera && ! L.Browser.edge) {
        //     //     layer.bringToFront();
        //     // }
        //
        //     // info.update(layer.feature.properties);
        // },

        /**
         *
         */
        resetHighlight (e)
        {
            console.log('reset', e);
            // hexFiltered.resetStyle(e.target);

            // info.update();
        },

        /**
         * Style for grid hex
         * This is based on the number of photos per polygon, not the total_litter
         */
        style (feature)
        {
            console.log({ feature });
            return {
                weight: 2,
                opacity: 1,
                color: 'white',
                dashArray: '3',
                fillOpacity: 0.7,
                fillColor: this.getColor(feature.properties.total)
            };
        },

        /**
         * A hexgrid has been pressed
         */
        zoomToFeature (e)
        {
            this.$refs.map.mapObject.fitBounds(e.target.getBounds());
        }
    }
}
</script>

<style scoped>

    .info-title {
        color: #777;
        margin-bottom: 10px;
    }

    .leaflet-default-icon-path {
        background-image: url('/images/vendor/leaflet/dist/marker-icon.png');
    }

    .leaflet-default-shadow-path {
        background-image: none
    }

    .map-container {
        height: calc(100% - 72px);
        margin: 0;
        position: relative;
    }

</style>
