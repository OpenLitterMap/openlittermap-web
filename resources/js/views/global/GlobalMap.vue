<template>
    <div style="height: 100%;" @click="closeButtons">
        <div id="globalmap" ref="map" />

        <!-- Change language -->
        <Languages />

        <!-- Load / change data -->
        <!-- First request made here -->
        <global-dates />

        <!-- Call to Action -->
        <global-info />

        <!-- Live Events -->
        <live-events />
    </div>
</template>

<script>
import Languages from '../../components/global/Languages'
import GlobalDates from '../../components/global/GlobalDates'
import LiveEvents from '../../components/LiveEvents'
import GlobalInfo from '../../components/global/GlobalInfo'

import L from 'leaflet' // make sure to load leaflet before marker-cluster
import 'leaflet.markercluster'

import moment from 'moment'

var map;
var clusters = L.markerClusterGroup(); // .addTo(map);

/**
 * Get data within the bounding box
 *
 * @param bbox = [float, float, float, float] ( W, S, E, N )
 * @param zoom = int (0-18)
 */
async function update ()
{
    let bounds = map.getBounds();
    let bbox = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
    let zoom = map.getZoom();

    // At zoom 17, we want to get the geojson object with individual points
    console.log({ zoom });

    //  backend clustering
    await axios.get('/global/clusters', {
        params: {
            top: bbox[3], // top
            bottom: bbox[1], // bottom
            left: bbox[0], // west
            right: bbox[2], // east
            zoom
        }
    })
    .then(async response => {
        console.log('update_global_map', response.data);

        clusters.clearLayers();

        clusters = L.markerClusterGroup({
            maxClusterRadius: 120,
            iconCreateFunction: function (cluster) {

                let markers = cluster.getAllChildMarkers();

                let n = markers[0].options.title;

                return L.divIcon({ html: n, className: 'mycluster', iconSize: L.point(40, 40) });
            },
            //Disable all of the defaults:
            spiderfyOnMaxZoom: false, showCoverageOnHover: false, zoomToBoundsOnClick: false
        });

        // don't know why this is async, but empty hulls are throwing errors without await
        for await (let i of response.data.hulls)
        {
            if (i.hasOwnProperty('lat'))
            {
                L.marker([i.lat, i.lon], {title: i.count}).addTo(clusters);
            }
        }

        map.addLayer(clusters);
    })
    .catch(error => {
        console.log('error.update_global_map', error);
    });

}

export default {
    name: 'GlobalMap',
    components: {
        Languages,
        GlobalDates,
        LiveEvents,
        GlobalInfo
    },
    mounted ()
    {
        /** 1. Create map object */
        map = L.map(this.$refs.map, {
            center: [0,0],
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

        // Later - Create groups
        // let artGroup = new L.LayerGroup();

        // Update the displayed clusters after user pan / zoom.
        map.on('moveend', function () {
            update();
        });

        // Loop over points
        this.$store.state.globalmap.geojson.features.map(i => {

            let a = '';
            // Default global marker
            L.marker([i.properties.lat, i.properties.lon]).addTo(clusters).bindPopup(a + ' '
                + '<p>' + i.properties.result_string + '</p>'
                + '<p>Taken on ' + moment(i.properties.datetime).format('LLL') + '</p>'
                + '<img style="height: 100px;" src="' + i.properties.filename + '"/>'
                + '<p>Lat, Lon: ' + i.properties.lat + ', ' + i.properties.lon + '</p>'
            );

            // todo - Extract Specific Features (art)
            // let artString = '';
            // if(i.properties.art != 'null') {
            //     L.marker([lat, lon]).addTo(artGroup).bindPopup('Litter Art!' + '<br>'
            //         + '<p>Taken on ' + i.properties.datetime + '</p>'
            //         + '<img style="height: 100px;" src="' + i.properties.filename + '"/>'
            //         + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
            //     );
            // }
        });

        // todo - Add the cluster & overlays to the map
        // let overlays = {
        //     Global: clusters,
        //     Art: artGroup,
        // };

        map.addLayer(clusters);

        this.$store.commit('globalLoading', false);
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

<style scoped lang="scss">

    #globalmap {
        height: 100%;
        margin: 0;
        position: relative;
    }

    .mi {
        height: 100%;
        margin: auto;
        display: flex;
        justify-content: center;
        border-radius: 20px;
    }

    .mycluster {
        background-color: green !important;
        height: 2em !important;
        width: 2em !important;
    }

</style>
