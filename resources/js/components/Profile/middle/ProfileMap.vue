<template>
    <div class="profile-card" style="padding: 0 !important;">
        <fullscreen ref="fullscreen" @change="fullscreenChange" class="profile-map-container">

            <button class="btn-map-fullscreen" @click="toggle">
                <i class="fa fa-expand"/>
            </button>

            <div id="hexmap" ref="hexmap"/>
        </fullscreen>
    </div>
</template>

<script>
import L from 'leaflet';
import 'leaflet-timedimension'
import "leaflet-timedimension/dist/leaflet.timedimension.control.css"
import {mapHelper} from '../../../maps/mapHelpers';

export default {
    name: 'ProfileMap',
    async mounted() {
        /** 1. Create map object */
        this.map = L.map('hexmap', {
            center: [0, 0],
            zoom: 2,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        // /** 2. Add attribution to the map */
        const date = new Date();
        const year = date.getFullYear();

        let mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: 20,
            minZoom: 1,
        }).addTo(this.map);

        this.map.attributionControl.addAttribution('Litter data &copy; OpenLitterMap & Contributors ' + year);

        // Time player settings
        let timeDimension = new L.TimeDimension({});
        this.map.timeDimension = timeDimension;
        this.player = new L.TimeDimension.Player({
            transitionTime: 1000,
            loop: true
        }, timeDimension);
        this.player.on('play', () => {
            if (this.map?.hasLayer(this.pointsLayer)) {
                this.map.removeLayer(this.pointsLayer);
            }
        })

        this.map.addControl(new L.Control.TimeDimension({
            player: this.player,
            timeDimension: timeDimension,
            timeSliderDragUpdate: true,
            loopButton: true,
            autoPlay: false,
            minSpeed: 5,
            maxSpeed: 100,
        }));
    },
    data() {
        return {
            map: null,
            loading: true,
            fullscreen: false,
            pointsLayer: null,
            timeLayer: null,
            player: null
        };
    },
    computed: {
        /**
         * From backend api request
         */
        geojson() {
            return this.$store.state.user.geojson.features;
        }
    },
    watch: {
        geojson (newVal) {
            if (this.pointsLayer) this.pointsLayer.remove();
            if (this.timeLayer) this.timeLayer.remove();
            if (this.player) this.player.stop();

            this.pointsLayer = L.geoJSON(newVal, {
                pointToLayer: (feature, latLng) => {
                    return L.marker([latLng.lng, latLng.lat])
                },
                onEachFeature: (feature, layer) => {
                    layer.on('click', (e) => {
                        L.popup(mapHelper.popupOptions)
                            .setLatLng(feature.geometry.coordinates)
                            .setContent(mapHelper.getMapImagePopupContent(feature.properties))
                            .openOn(this.map);
                    });
                }
            });

            this.timeLayer = L.timeDimension.layer.geoJson(this.pointsLayer, {
                updateTimeDimension: true,
                updateTimeDimensionMode: 'replace',
            });

            this.pointsLayer.addTo(this.map);
            this.timeLayer.addTo(this.map);
        }
    },
    methods: {
        fullscreenChange(fullscreen) {
            this.fullscreen = fullscreen
        },

        toggle() {
            this.$refs['fullscreen'].toggle() // recommended
        },
    }
};
</script>

<style lang="css" scoped>

#hexmap {
    height: 100%;
    margin: 0;
    position: relative;
}

.btn-map-fullscreen {
    position: absolute;
    top: 1em;
    right: 1em;
    z-index: 1234;
}

/* remove padding on mobile */
.profile-map-container {
    height: 100%;
    position: relative;
}
</style>
