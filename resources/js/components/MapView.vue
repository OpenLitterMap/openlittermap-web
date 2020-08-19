<template>
	<div id="map"></div>
</template>

<script>
import Leaflet from 'leaflet'

export default {

	ready() {
		var map = L.map('map').setView([53.987, -8.765], 13);
		L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    		attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
    		maxZoom: 18,
   			minZoom: 13,
    		// todo: maxBounds: bounds -> import from MapController -> not yet configured 
		}).addTo(map);
	}

}
</script>

<style>
    #map {
        height: 100%;
        width: 100%;
        margin-top: -24px;
    }

    /* default legend, layer styling from leaflet template */
    .info {
        padding: 6px 8px;
        font: 14px/16px Arial, Helvetica, sans-serif;
        background: white;
        background: rgba(255,255,255,0.8);
        box-shadow: 0 0 15px rgba(0,0,0,0.2);
        border-radius: 5px;
    }
    .info h4 {
        margin: 0 0 5px;
        color: #777;
    }
    .legend {
        text-align: left;
        line-height: 18px;
        color: #555;
    }
    .legend i {
        width: 18px;
        height: 18px;
        float: left;
        margin-right: 8px;
        opacity: 0.7;
    }
</style>