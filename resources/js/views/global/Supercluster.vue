<template>
    <div style="height: 100%;" @click="closeButtons">
        <div id="super" ref="super" />

        <!-- Change language -->
        <Languages />

        <!-- Load / change data -->
        <!-- First request made here -->
        <global-dates />

        <!-- Call to Action -->
        <global-info />

        <!-- Live Events -->
        <live-events />
    </div></template>

<script>
import Languages from '../../components/global/Languages'
import GlobalDates from '../../components/global/GlobalDates'
import LiveEvents from '../../components/LiveEvents'
import GlobalInfo from '../../components/global/GlobalInfo'

import L from 'leaflet' // make sure to load leaflet before marker-cluster

var map;
var markers;

const single_icon = L.icon({
    iconUrl: './images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

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
 * The user dragged or zoomed the map
 */
async function update ()
{
    const bounds = map.getBounds();

    let bbox = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
    let zoom = map.getZoom();

    console.log({ zoom });

    await axios.get('clusters', {
        params: {
            zoom, bbox
        }
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

export default {
    name: 'Supercluster',
    components: {
        Languages,
        GlobalDates,
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

        map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year);

        // Empty Layer Group that will receive the clusters data on the fly.
        markers = L.geoJSON(null, {
            pointToLayer: createClusterIcon
        }).addTo(map);

        // // Create clusters object
        // index = new Supercluster({
        //     log: true,
        //     radius: 40,
        //     maxZoom: 16
        // });
        //
        // console.log('loading...');
        //
        // index.load(this.$store.state.globalmap.geojson.features);
        //
        // let clusters = index.getClusters([-180, -85, 180, 85], 2);
        //
        // markers.addData(clusters);

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

    .mi {
        height: 100%;
        margin: auto;
        display: flex;
        justify-content: center;
        border-radius: 20px;
    }

</style>
