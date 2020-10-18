<template>
    <div style="height: 100%;" @click="closeButtons">
        <div id="super" ref="super" />

        <!-- Change language -->
        <Languages />

        <!-- Change data -->
        <!-- <global-dates />-->

        <!-- Call to Action -->
        <global-info />

        <!-- Live Events -->
        <live-events />
    </div>
</template>

<script>
import Languages from '../../components/global/Languages'
// import GlobalDates from '../../components/global/GlobalDates'
import LiveEvents from '../../components/LiveEvents'
import GlobalInfo from '../../components/global/GlobalInfo'

import L from 'leaflet' // make sure to load leaflet before marker-cluster
import moment from 'moment'

var map;
var markers;
var prevZoom = 2;

const single_icon = L.icon({
    iconUrl: './images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

/**
 * Create the cluster or point icon to display for each feature
 */
function createClusterIcon (feature, latlng)
{
    if (! feature.properties.cluster) return L.marker(latlng, { icon: single_icon });

    let count = feature.properties.point_count;
    let size = count < 100 ? 'small' : count < 1000 ? 'medium' : 'large';

    let icon = L.divIcon({
        html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, { icon });
}

/**
 * On each feature, perform this action
 */
function onEachFeature (feature, layer)
{
    if (feature.properties.cluster) console.log(layer);

    else
    {
        layer.bindPopup(
            '<p class="mb5p">' + feature.properties.result_string + ' </p>'
            + '<img src= "' + feature.properties.filename + '" class="mw100" />'
            + '<p>Taken on ' + moment(feature.properties.datetime).format('LLL') +'</p>'
        );
    }
}

/**
 * The user dragged or zoomed the map
 */
async function update ()
{
    const bounds = map.getBounds();

    let bbox = {'left': bounds.getWest(), 'bottom': bounds.getSouth(), 'right': bounds.getEast(), 'top': bounds.getNorth()};
    let zoom = map.getZoom();

    console.log({ zoom });

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if (zoom === 2 && zoom === prevZoom) return;
    if (zoom === 3 && zoom === prevZoom) return;
    if (zoom === 4 && zoom === prevZoom) return;
    if (zoom === 5 && zoom === prevZoom) return;

    prevZoom = zoom; // hold previous zoom

    // If the zoom is less than 17, we want to load cluster data
    if (zoom < 17)
    {
        await axios.get('clusters', {
            params: { zoom, bbox }
        })
        .then(response => {
            console.log('get_clusters.update', response);

            markers.clearLayers();
            markers.addData(response.data);
        })
        .catch(error => {
            console.error('get_clusters.update', error);
        });
    }

    else
    {
        await axios.get('global-points', {
            params: { zoom, bbox }
        })
        .then(response => {
            console.log('get_global_points', response);

            markers.clearLayers();
            markers.addData(response.data);
        })
        .catch(error => {
            console.error('get_global_points', error);
        });
    }
}

export default {
    name: 'Supercluster',
    components: {
        Languages,
        // GlobalDates,
        LiveEvents,
        GlobalInfo
    },
    mounted ()
    {
        /** 1. Create map object */
        map = L.map('super', {
            center: [0, 0],
            zoom: 2,
        });

        const date = new Date();
        const year = date.getFullYear();

        /** 2. Add tiles, attribution, set limits */
        const mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 18,
            minZoom: 2
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year + ' Clustering @ MapBox');

        // Empty Layer Group that will receive the clusters data on the fly.
        markers = L.geoJSON(null, {
            pointToLayer: createClusterIcon,
            onEachFeature: onEachFeature
        }).addTo(map);

        markers.addData(this.$store.state.globalmap.geojson.features);

        map.on('moveend', function () {
            update();
        });

        // todo - getClusterExpansionZoom(clusterId);
    },

    methods: {

        /**
         * Close dates and language dropdowns
         */
        closeButtons ()
        {
            this.$store.commit('closeDatesButton');
            this.$store.commit('closeLangsButton');
        }
    }
}
</script>

<style>

    #super {
        height: 100%;
        margin: 0;
        position: relative;
    }

    .leaflet-marker-icon {
        border-radius: 20px;
    }

    .mb5p {
        margin-bottom: 5px;
    }

    .mw100 {
        max-width: 100%;
    }

    .mi {
        height: 100%;
        margin: auto;
        display: flex;
        justify-content: center;
        border-radius: 20px;
    }

</style>
