<template>
	<div class="global-map-container" @click="closeButtons">
		<l-map :zoom="zoom" :center="center" :minZoom="1" ref="map">
		  	<l-tile-layer :url="url" :attribution="attribution" />

			<v-marker-cluster>
				<l-marker v-for="c in this.$store.state.globalmap.globalMapData" :lat-lng="c.latlng" :key="c.id">
					<l-popup :content="c.text" />
				</l-marker>
			</v-marker-cluster>
		</l-map>

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

<!--	<v-prune-cluster-->
<!--			:items="this.$store.state.globalmap.globalMapData"-->
<!--			:mapRef="this.$refs"-->
<!--			@clickOnItem="doWhateverYouWant"-->
<!--	/>-->
</template>

<script>
import { LMap, LTileLayer, LMarker, LIcon, LPopup } from 'vue2-leaflet'

import Vue2LeafletMarkerCluster from 'vue2-leaflet-markercluster'

import Languages from '../../components/global/Languages'
import GlobalDates from '../../components/global/GlobalDates'

import LiveEvents from '../../components/LiveEvents'
import GlobalInfo from '../../components/global/GlobalInfo'

export default {
	name: 'OldGlobalMap',
	components: {
		LMap,
   		LTileLayer,
   		LMarker,
   		Languages,
   		LPopup,
        LIcon,
   		GlobalDates,
   		LiveEvents,
   		GlobalInfo,
		'v-marker-cluster': Vue2LeafletMarkerCluster,
	},
	created ()
	{
		this.attribution += new Date().getFullYear();
	},
	data ()
	{
		return {
			zoom: 2,
			center: L.latLng(0,0),
			url:'https://{s}.tile.osm.org/{z}/{x}/{y}.png',
			attribution:'Map Data &copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors, Litter data &copy OpenLitterMap & Contributors '
		};
	},
	methods: {

	}
}
</script>

<style>
/*@import "~leaflet/dist/leaflet.css";*/
/*@import "~leaflet.markercluster/dist/MarkerCluster.css";*/
/*@import "~leaflet.markercluster/dist/MarkerCluster.Default.css";*/

	.global-map-container {
		height: calc(100% - 72px);
		margin: 0;
		position: relative;
        z-index: 1;
	}

	.leaflet-popup-content {
		width: 180px !important;
	}

	.lealet-popup {
		left: -106px !important;
	}

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

    .leaflet-pane .leaflet-shadow-pane {
        display: none;
    }

	/*.leaflet-default-icon-path {*/
	/*	background-image: url('/images/vendor/leaflet/dist/marker-icon.png');*/
	/*}*/

	/*.leaflet-default-shadow-path {*/
	/*	background-image: none*/
	/*}*/
</style>
