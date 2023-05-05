<template>
    <div class="map-container">
        <div id="map" ref="map"/>
    </div>
</template>

<script>
import L from 'leaflet';
import 'leaflet-timedimension';
import 'leaflet-timedimension/dist/leaflet.timedimension.control.css';
import { mapHelper } from '../../maps/mapHelpers';
import { MIN_ZOOM, MAX_ZOOM } from '../../constants';

export default {
    name: 'TagsViewer',
    data () {
        return {
            geojson: null,
            map: null,
            pointsLayer: null,
            timeLayer: null,
            player: null
        }
    },
    async mounted () {
        await this.load();

        /** 1. Create map object */
        this.map = L.map('map', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        this.flyToLocationFromURL();

        // /** 2. Add attribution to the map */
        const date = new Date();
        const year = date.getFullYear();
        let mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: MAX_ZOOM,
            minZoom: MIN_ZOOM,
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

        this.pointsLayer = L.geoJSON(this.geojson, {
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

        this.map.on('moveend', this.updateLocationInURL);
        this.map.on('popupopen', mapHelper.scrollPopupToBottom);
    },
    methods: {
        async load () {
            const searchParams = new URLSearchParams(window.location.search);
            const customTag = searchParams.get('custom_tag');
            const brand = searchParams.get('brand');
            const customTags = searchParams.get('custom_tags');

            await axios.get('/tags-search', {
                params: {
                    custom_tag: customTag,
                    custom_tags: customTags,
                    brand
                }
            })
            .then(response =>
            {
                this.geojson = response.data;
            })
            .catch(error =>
            {
                console.error('get_tags', error);
            });
        },

        /**
         * Goes to the location and zoom given in the URL
         * Params are: lat, lon, zoom
         */
        flyToLocationFromURL ()
        {
            const urlParams = new URLSearchParams(window.location.search);
            let latitude = parseFloat(urlParams.get('lat') || 0);
            let longitude = parseFloat(urlParams.get('lon') || 0);
            let zoom = parseFloat(urlParams.get('zoom') || MIN_ZOOM);

            // Validate lat, lon, and zoom level
            latitude = (latitude < -85 || latitude > 85) ? 0 : latitude;
            longitude = (longitude < -180 || longitude > 180) ? 0 : longitude;
            zoom = (zoom < MIN_ZOOM || zoom > MAX_ZOOM) ? MIN_ZOOM : zoom;

            if (latitude === 0 && longitude === 0 && zoom === MIN_ZOOM) return;

            const latLng = [latitude, longitude];

            this.map.flyTo(latLng, zoom, {
                animate: true,
                duration: 5
            });
        },

        /**
         * Simply updates the URL
         * with the current map location and zoom
         */
        updateLocationInURL ()
        {
            const location = this.map.getCenter();

            const url = new URL(window.location.href);
            url.searchParams.set('lat', location.lat);
            url.searchParams.set('lon', location.lng);
            url.searchParams.set('zoom', this.map.getZoom());

            window.history.pushState(null, '', url);
        },
    }
}
</script>

<style scoped>
.map-container {
    height: calc(100% - 72px);
    margin: 0;
    position: relative;
    z-index: 1;
}

#map {
    height: 100%;
    margin: 0;
    position: relative;
}
</style>
