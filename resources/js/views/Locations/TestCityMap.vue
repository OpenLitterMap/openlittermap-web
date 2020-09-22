<template>
    <div id="map" ref="map" />
</template>

<script>
import * as turf from "@turf/turf";

var info;
var hexFiltered;

var smokingGroup;
var foodGroup;
var coffeeGroup;
var alcoholGroup;
// var drugsGroup;
var softdrinksGroup;
var sanitaryGroup;
var otherGroup;
var coastalGroup;
// var pathwayGroup;
// var artGroup;
var brandsGroup;
var dogshitGroup;
var dumpingGroup;
var industrialGroup;

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

function getColor (n)
{
    return n > 60 ? '#800026' :
        n > 20 ? '#BD0026' :
        n > 10 ? '#E31A1C' :
        n > 4  ? '#FD8D3C' :
        n > 2  ? '#FED976' :
        '#FFEDA0';
}

function onEachFeature(feature, layer)
{
    layer.on({
        mouseover: highlightFeature,
        mouseout: resetHighlight,
        click: zoomToFeature
    });
}

function highlightFeature (e)
{
    var layer = e.target;

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

function resetHighlight (e)
{
    hexFiltered.resetStyle(e.target);
    info.update();
}

function zoomToFeature (e)
{
    map.fitBounds(e.target.getBounds());
}


export default {
    name: "TestCityMap",
    mounted ()
    {
        /** 1. Create map object */
        const map = L.map(this.$refs.map, {
            center: this.$store.state.citymap.center, // center_map,
            zoom: this.$store.state.citymap.zoom
        });

        /** 2. Add attribution to the map */
        const date = new Date();
        const year = date.getFullYear();

        let mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 20,
            minZoom: 1,
            // todo: maxBounds: bounds -> import from MapController -> not yet configured
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

        /** 3. Create hex grid using aggregated data */
        hexFiltered = L.geoJson(this.aggregate, {
            style: style,
            onEachFeature: onEachFeature,
            filter: function(feature, layer) {
                if (feature.properties.values.length > 0) {
                    var sum = 0;
                    for(var i=0; i < feature.properties.values.length; i++) {
                        sum += feature.properties.values[i]
                    }
                    feature.properties.total = sum;
                }
                return feature.properties.values.length > 0;
            }
        }).addTo(map);

        /** 4. Add info/control to the Top-Right */
        info = L.control();
        info.onAdd = function (map) {
            this._div = L.DomUtil.create('div', 'info');
            this.update();
            return this._div;
        };

        // Get Counts
        info.update = function (props) {
            this._div.innerHTML = '<h4>' + this.hex + ' meter hex grids</h4>' +  (props ?
                '<b>Hover over to count'+'</b><br />' + props.total + ' pieces of litter'
                : 'Hover over polygons to count.');
        };
        info.addTo(map);


        /** 5. Style the legend */
        // needs to dynamically and statistically significantly reflect input variables
        let legend = L.control({ position: 'bottomleft' });

        legend.onAdd = function (map)
        {
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

        /** 6. Create Groups */
        smokingGroup = new L.LayerGroup();
        foodGroup = new L.LayerGroup();
        coffeeGroup = new L.LayerGroup();
        alcoholGroup = new L.LayerGroup();
        // drugsGroup = new L.LayerGroup();
        softdrinksGroup = new L.LayerGroup().addTo(map);
        sanitaryGroup = new L.LayerGroup();
        otherGroup = new L.LayerGroup();
        coastalGroup = new L.LayerGroup();
        // pathwayGroup = new L.LayerGroup();
        // artGroup = new L.LayerGroup();
        brandsGroup = new L.LayerGroup();
        dogshitGroup = new L.LayerGroup();
        dumpingGroup = new L.LayerGroup();
        industrialGroup = new L.LayerGroup();
        // trashdogGroup = new L.LayerGroup();

        /** 7. Loop over geojson data and add to groups */
        this.addDataToLayerGroups();

        /** 8. Create overlays toggle menu */
        var overlays = {
            Alcohol: alcoholGroup,
            // Art: artGroup,
            Brands: brandsGroup,
            Coastal: coastalGroup,
            Coffee: coffeeGroup,
            Dumping: dumpingGroup,
            // Drugs: drugsGroup,
            Food: foodGroup,
            Industrial: industrialGroup,
            Other: otherGroup,
            // Pathway: pathwayGroup,
            PetSurprise: dogshitGroup,
            Sanitary: sanitaryGroup,
            Smoking: smokingGroup,
            SoftDrinks: softdrinksGroup,
            // TrashDog: trashdogGroup
        };

        /** 9- Add null basemaps and overlays to the map */
        L.control.layers(null, overlays).addTo(map);

        /** 10 - TODO - Timeslider */

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

        }
    }
}
</script>

<style scoped>

    #map {
        height: calc(98.5vh - 72px);
        margin: 0;
        position: relative;
    }

</style>
