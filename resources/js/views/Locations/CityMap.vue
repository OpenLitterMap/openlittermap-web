<template>
    <div id="hexmap" ref="map" />
</template>

<script>
import L from 'leaflet'

// error when importing Turf from '@turf/turf' and using bbox + aggregate
// https://github.com/Turfjs/turf/issues/1952
import * as turf from '../../../../public/js/turf.js'

import moment from 'moment'

import { categories } from '../../extra/categories'
import { litterkeys } from '../../extra/litterkeys'
import {MIN_ZOOM} from '../../constants';

var map;
var info;
var hexFiltered;

var smokingGroup;
var foodGroup;
var coffeeGroup;
var alcoholGroup;
var softdrinksGroup;
var sanitaryGroup;
var otherGroup;
var coastalGroup;
var brandsGroup;
var dogshitGroup;
var dumpingGroup;
var industrialGroup;

/**
 * The colour for each hex grid
 * This should become proportional to the range of data
 */
function getColor (n)
{
    return n > 60 ? '#800026' :
        n > 20 ? '#BD0026' :
        n > 10 ? '#E31A1C' :
        n > 4  ? '#FD8D3C' :
        n > 2  ? '#FED976' :
        '#FFEDA0';
}

/**
 * Outer-style to give each hex grid
 */
function style (feature)
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

/**
 * Apply these to each hexgrid
 */
function onEachFeature (feature, layer)
{
    layer.on({
        mouseover: highlightFeature,
        mouseout: resetHighlight,
        click: zoomToFeature
    });
}

/**
 * Applied when a hex-grid is hovered
 */
function highlightFeature (e)
{
    let layer = e.target;

    layer.setStyle({
        weight: 5,
        color: '#666',
        dashArray: '',
        fillOpacity: 0.7
    });

    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
        layer.bringToFront();
    }

    info.update(layer.feature.properties);
}

/**
 * When mouseleave on hex-grid
 */
function resetHighlight (e)
{
    hexFiltered.resetStyle(e.target);
    info.update();
}

/**
 * A hexgrid has been pressed
 * Instead of zoom, lets open a dialog box with stats.
 */
function zoomToFeature (e)
{
    // map.fitBounds(e.target.getBounds());
}

export default {
    name: 'CityMap',
    mounted ()
    {
        /** 1. Create map object */
        map = L.map(this.$refs.map, {
            center: this.$store.state.citymap.center, // center_map,
            zoom: this.$store.state.citymap.zoom,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        /** 2. Add attribution to the map */
        const date = new Date();
        const year = date.getFullYear();

        let mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 20,
            minZoom: 1,
            // todo: maxBounds: bounds -> import from MapController -> not yet configured
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

        /** 3. Create hex grid using aggregated data */
        if (this.geojson)
        {
            hexFiltered = L.geoJson(this.aggregate, {
                style,
                onEachFeature,
                filter: function (feature, layer) {
                    if (feature.properties.values.length > 0) {
                        let sum = 0;

                        for (let i = 0; i < feature.properties.values.length; i++) sum += feature.properties.values[i]

                        feature.properties.total = sum;
                    }

                    return feature.properties.values.length > 0;
                }
            }).addTo(map);

            /** 4. Add info/control to the Top-Right */
            info = L.control();
            info.onAdd = function (map)
            {
                this._div = L.DomUtil.create('div', 'info');
                this.update();
                return this._div;
            };

            // Get Counts
            const meterHexGrids = this.$t('locations.cityVueMap.meter-hex-grids');
            const hoverToCount = this.$t('locations.cityVueMap.hover-to-count');
            const piecesOfLitter = this.$t('locations.cityVueMap.pieces-of-litter');
            const hoverOverPolygonsToCount = this.$t('locations.cityVueMap.hover-polygons-to-count');
            const hex = this.hex;

            info.update = function (props) {
                this._div.innerHTML = '<h4>' + hex + ` ${meterHexGrids}</h4>` + (props ?
                    `<b>${hoverToCount} </b><br />` + props.total + ` ${piecesOfLitter}`
                    : `${hoverOverPolygonsToCount}.`);
            };
            info.addTo(map);

            /** 5. Style the legend */
            // Todo - we need to dynamically and statistically reflect the range of available values
            let legend = L.control({position: 'bottomleft'});

            legend.onAdd = function (map) {
                let div = L.DomUtil.create('div', 'info legend'),
                    grades = [1, 3, 6, 10, 20],
                    labels = [],
                    from, to;

                for (let i = 0; i < grades.length; i++) {
                    from = grades[i];
                    to = grades[i + 1];

                    labels.push(
                        '<i style="background:' + getColor(from + 1) + '"></i> ' +
                        from + (to ? '&ndash;' + to : '+')
                    );
                }

                div.innerHTML = labels.join('<br>');
                return div;
            };
            legend.addTo(map);
        }

        /** 6. Loop over geojson data and add to groups */
        this.addDataToLayerGroups();

        /** 7. TODO - Timeslider */

    },
    computed: {

        /**
         * From our input geojson object,
         * 1. Create bounding box
         * 2. Create hexgrid within bounding box
         * 3. Count point-in-polygon to filter out empty values
         */
        aggregate ()
        {
            // Create a bounding box from our set of features
            let bbox = turf.bbox(this.geojson);

            // Create a hexgrid from our data. This needs to be filtered to only show relevant data.
            let hexgrid = turf.hexGrid(bbox, this.hex, 'meters');

            // we need to parse here to avoid copying the object as shallow copies
            // see https://github.com/Turfjs/turf/issues/1914
            hexgrid = JSON.parse(JSON.stringify(hexgrid));

            // To filter the hexgrid, we need to find hex values with point in polygon and remove 0 values
            // "values" will be appended to the hexgrid
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
         * Loop over the geojson
         */
        addDataToLayerGroups ()
        {
            /** 6. Create Groups */
            smokingGroup = new L.LayerGroup();
            foodGroup = new L.LayerGroup();
            coffeeGroup = new L.LayerGroup();
            alcoholGroup = new L.LayerGroup();
            softdrinksGroup = new L.LayerGroup().addTo(map);
            sanitaryGroup = new L.LayerGroup();
            otherGroup = new L.LayerGroup();
            coastalGroup = new L.LayerGroup();
            brandsGroup = new L.LayerGroup();
            dogshitGroup = new L.LayerGroup();
            dumpingGroup = new L.LayerGroup();
            industrialGroup = new L.LayerGroup();

            const groups = {
                smoking: smokingGroup,
                food: foodGroup,
                coffee: coffeeGroup,
                alcohol: alcoholGroup,
                softdrinks: softdrinksGroup,
                sanitary: sanitaryGroup,
                other: otherGroup,
                coastal: coastalGroup,
                brands: brandsGroup,
                dogshit: dogshitGroup,
                industrial: industrialGroup,
                dumping: dumpingGroup
            };

            this.geojson.features.map(i => {

                let name = '';
                let username = '';

                if (i.properties.hasOwnProperty('name') && name) name = i.properties.name;
                if (i.properties.hasOwnProperty('username') && username) username = ' @' + i.properties.username;
                if (name === '' && username === '') name = 'Anonymous';

                // Dynamically add items to the groups + add markers
                categories.map(category => {

                    if (i.properties[category])
                    {
                        let string = '';

                        litterkeys[category].map(item => {

                            if (i.properties[category][item])
                            {
                                string += this.$t('litter.'+[category]+'.'+[item]) + ': ' + i.properties[category][item] + ' <br>';

                                L.marker([i.properties.lat, i.properties.lon])
                                    .addTo(groups[category])
                                    .bindPopup(string
                                        + '<img class="lim" src="'+ i.properties.filename + '"/>'
                                        + '<p>' + this.$t('locations.cityVueMap.taken-on') + ' ' + moment(i.properties.datetime).format('LLL') + ' ' + this.$t('locations.cityVueMap.with-a') + ' ' + i.properties.model + '</p>'
                                        + '<p>' + this.$t('locations.cityVueMap.by') + ': ' + name + username + '</p>'
                                    );
                            }
                        });
                    }
                });
            });

            /** 8. Create overlays toggle menu */
            let overlays = {
                Alcohol: alcoholGroup,
                Brands: brandsGroup,
                Coastal: coastalGroup,
                Coffee: coffeeGroup,
                Dumping: dumpingGroup,
                Food: foodGroup,
                Industrial: industrialGroup,
                Other: otherGroup,
                PetSurprise: dogshitGroup,
                Sanitary: sanitaryGroup,
                Smoking: smokingGroup,
                SoftDrinks: softdrinksGroup,
            };

            /** 9- Add null basemaps and overlays to the map */
            L.control.layers(null, overlays).addTo(map);
        }
    }
}
</script>

<style scoped lang="scss">

    #hexmap {
        height: 100%;
        margin: 0;
        position: relative;
        z-index: 0;
    }

    .leaflet-popup-content {
        margin: 0 20px !important;
    }

    .lim {
        max-width: 100%;
        padding-top: 1em;
    }

</style>
