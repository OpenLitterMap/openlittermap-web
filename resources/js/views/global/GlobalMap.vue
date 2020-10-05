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
        const map = L.map(this.$refs.map, {
            center: [0,0],
            zoom: 1,
        });

        const date = new Date();
        const year = date.getFullYear();

        /** 2. Add tiles, attribution, set limits */
        const mapLink = '<a href="http://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 18,
            minZoom: 2,
        }).addTo(map);
        map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year);


        // Create groups
        // let artGroup = new L.LayerGroup();

        // Create clusters object
        // let clusters = L.markerClusterGroup().addTo(map);
        //
        // // Loop over points : // can we vectorize this with numpy integration?
        // for (let i = 0; i < global.features.length; i++)
        // {
        //     const lat = global.features[i].geometry.coordinates[1];
        //     const lon = global.features[i].geometry.coordinates[0];
        //
        //     let a = '';
        //
        //     // Default global marker
        //     L.marker([lat, lon]).addTo(clusters).bindPopup(a + ' '
        //         + '<p>' + global.features[i].properties.result_string + '</p>'
        //         + '<p>Taken on ' + moment(global.features[i].properties.datetime).format('LLL') + '</p>'
        //         + '<img style="height: 100px;" src="' + global.features[i].properties.filename + '"/>'
        //         + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
        //     );
        //
        //     // todo - Extract Specific Features (art)
        //     // let artString = '';
        //     // if(global.features[i].properties.art != 'null') {
        //     //     L.marker([lat, lon]).addTo(artGroup).bindPopup('Litter Art!' + '<br>'
        //     //         + '<p>Taken on ' + global.features[i].properties.datetime + '</p>'
        //     //         + '<img style="height: 100px;" src="' + global.features[i].properties.filename + '"/>'
        //     //         + '<p>Lat, Lon: ' + lat + ', ' + lon + '</p>'
        //     //     );
        //     // }
        // }

        // todo - Add the cluster & overlays to the map
        // let overlays = {
        //     Global: clusters,
        //     Art: artGroup,
        // };

        // map.addLayer(clusters);
    },

    computed: {

        /**
         * All global map data
         */
        global ()
        {
            return this.$store.state.globalmap.globalMapData;
        }
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

    }
}
</script>

<style scoped>

    #globalmap {
        height: 100%;
        margin: 0;
        position: relative;
    }

</style>
