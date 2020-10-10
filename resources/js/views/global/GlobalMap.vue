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

// import Supercluster from 'supercluster'
import moment from 'moment'

var map;
var clusters = L.markerClusterGroup(); // .addTo(map);

// var index;
// var markers;
//
// function createClusterIcon (feature, latlng)
// {
//     console.log({ feature })
//     if (! feature.properties.cluster) return L.marker(latlng);
//
//     let count = feature.properties.point_count;
//     let size = count < 100 ? 'small' : count < 1000 ? 'medium' : 'large';
//
//     let icon = L.divIcon({
//         html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
//         // className: 'marker-cluster marker-et-' + size,
//         className: 'marker-cluster-' + size,
//         iconSize: L.point(40, 40)
//     });
//
//     return L.marker(latlng, {
//         icon: icon
//     });
// }

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

    await axios.get('/global/bbox', {
        params: {
            top: bbox[3], // top
            bottom: bbox[1], // bottom
            left: bbox[0], // west
            right: bbox[2], // east
            zoom
        }
    })
    .then(response => {
        console.log('update_global_map', response.data);

        clusters.clearLayers();

        // Loop over points
        response.data.geojson.features.map(i => {
            let a = '';
            // Default global marker
            L.marker([i.properties.lat, i.properties.lon]).addTo(clusters).bindPopup(a + ' '
                + '<p>' + i.properties.result_string + '</p>'
                + '<p>Taken on ' + moment(i.properties.datetime).format('LLL') + '</p>'
                + '<img style="height: 100px;" src="' + i.properties.filename + '"/>'
                + '<p>Lat, Lon: ' + i.properties.lat + ', ' + i.properties.lon + '</p>'
            );
        });

        // clusters.addLayers(response.data.geojson);

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

        // Create groups
        // let artGroup = new L.LayerGroup();

        // Create clusters object
        // index = new Supercluster({
        //     radius: 40,
        //     maxZoom: 16
        // });
        //
        // index.load(this.geojson.features);
        // // index.getClusters([-180, -85, 180, 85], 2);
        // console.log({ index });
        //
        // // Empty Layer Group that will receive the clusters data on the fly.
        // markers = L.geoJSON(null, {
        //     pointToLayer: createClusterIcon
        // }).addTo(map);
        //
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
        },

        /**
         *
         */
        vue_update ()
        {
            console.log('ASDASD');
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

</style>
