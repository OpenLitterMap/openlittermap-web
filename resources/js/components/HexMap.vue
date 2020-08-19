<template>
	<div id="map"></div>
</template>

<script>
	export default {
		props: [
			'center_map',
			'map_zoom'
		],
		data() {
			return {
				map: null,
				tileLayer: null,
				layers: []
			};
		},
		mounted() {
			this.initMap();
			this.initLayers();
		},
		methods: {
			initMap() {
				this.map = L.map('map', {
				    center: [23, 31] // this.center_map, // center_map,
				    zoom: 5 // this.map_zoom,
				});
				this.tileLayer = L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				    attribution: 'Map data &copy; ' + '<a href="http://openstreetmap.org">OpenStreetMap</a>' + ' & Contributors',
				    maxZoom: 20,
				    minZoom: 1,
				    // todo: maxBounds: bounds -> import from MapController -> not yet configured 
				}).addTo(map);
			},
			initLayers() {

			}
		}
	}
</script>