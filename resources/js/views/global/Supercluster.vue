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
import Supercluster from 'supercluster'

var map;
var index;
var markers;

function createClusterIcon (feature, latlng)
{
    // console.log({ feature })
    if (! feature.properties.cluster) return L.marker(latlng);

    let count = feature.properties.point_count;
    let size = count < 100 ? 'small' : count < 1000 ? 'medium' : 'large';

    let icon = L.divIcon({
        html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
        // className: 'marker-cluster marker-et-' + size,
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, {
        icon: icon
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
        map = L.map(this.$refs.map, {
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

        // Create clusters object
        index = new Supercluster({
            radius: 40,
            maxZoom: 16
        });

        index.load(this.geojson.features);
        index.getClusters([-180, -85, 180, 85], 2);
        console.log({ index });

        // Empty Layer Group that will receive the clusters data on the fly.
        markers = L.geoJSON(null, {
            pointToLayer: createClusterIcon
        }).addTo(map);
    }
}
</script>

<style scoped>

</style>
