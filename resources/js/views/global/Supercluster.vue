<template>
    <div class="h100">
        <!-- The map & data -->
        <div
            id="openlittermap"
            ref="openlittermap"
        />

        <!-- Websockets -->
        <LiveEvents
            @fly-to-location="updateUrlPhotoIdAndFlyToLocation"
        />
    </div>
</template>

<script>
import LiveEvents from '../../components/LiveEvents';

import {
    CLUSTER_ZOOM_THRESHOLD,
    MAX_ZOOM,
    MEDIUM_CLUSTER_SIZE,
    LARGE_CLUSTER_SIZE,
    MIN_ZOOM,
    ZOOM_STEP
} from '../../constants';

import L from 'leaflet';
import './SmoothWheelZoom.js';

// Todo - fix this export bug (The request of a dependency is an expression...)
import glify from 'leaflet.glify';
import { mapHelper } from '../../maps/mapHelpers';
import dropdown from './select-dropdown';

var map;
var clusters;
var merchants;
var litterArtPoints;
var points;
var prevZoom = MIN_ZOOM;
var cleanups;
var userId = null;

var pointsLayerController;
var globalLayerController;
var pointsControllerShowing = false;
var globalControllerShowing = false;

const green_dot = L.icon({
    iconUrl: '/images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

const grey_dot = L.icon({
    iconUrl: '/images/vendor/leaflet/dist/grey-dot.jpg',
    iconSize: [13, 10]
});

/**
 * Create the point to display for each piece of Litter Art
 */
function createArtIcon (feature, latlng)
{
    const x = [latlng.lng, latlng.lat];

    return (feature.properties.verified === 2)
        ? L.marker(x, { icon: green_dot })
        : L.marker(x, { icon: grey_dot });
}

/**
 * Icon to use for displaying Cleanups
 */
function createCleanupIcon (feature, latlng)
{
    return L.marker(latlng, { icon: green_dot });
}

/**
 * Create the cluster or point icon to display for each feature
 */
function createClusterIcon (feature, latlng)
{
    if (!feature.properties.cluster)
    {
        return (feature.properties.verified === 2)
            ? L.marker(latlng, { icon: green_dot })
            : L.marker(latlng, { icon: grey_dot });
    }

    const count = feature.properties.point_count;
    const size = (count < MEDIUM_CLUSTER_SIZE)
        ? 'small'
            : count < LARGE_CLUSTER_SIZE
            ? 'medium'
            : 'large';

    const icon = L.divIcon({
        html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, { icon });
}

/**
 * Layer controller when below ZOOM_CLUSTER_THRESHOLD
 */
function createGlobalGroups ()
{
    if (pointsControllerShowing)
    {
        map.removeControl(pointsLayerController);

        pointsControllerShowing = false;
    }

    if (!globalControllerShowing)
    {
        globalLayerController = L.control.layers(null, null).addTo(map);

        globalLayerController.addOverlay(clusters, 'Global');
        globalLayerController.addOverlay(litterArtPoints, 'Litter Art');

        if (cleanups) {
            globalLayerController.addOverlay(cleanups, 'Cleanups');
        }

        if (merchants) {
            globalLayerController.addOverlay(merchants, 'Merchants');
        }

        globalControllerShowing = true;
    }
}

/**
 * Layer Controller when above ZOOM_CLUSTER_THRESHOLD
 */
function createPointGroups ()
{
    if (globalControllerShowing)
    {
        map.removeControl(globalLayerController)

        globalControllerShowing = false;
    }

    if (!pointsControllerShowing)
    {
        const overlays = {
            Alcohol: new L.LayerGroup(),
            Brands: new L.LayerGroup(),
            Coastal: new L.LayerGroup(),
            Coffee: new L.LayerGroup(),
            Dumping: new L.LayerGroup(),
            Food: new L.LayerGroup(),
            Industrial: new L.LayerGroup(),
            Other: new L.LayerGroup(),
            PetSurprise: new L.LayerGroup(),
            Sanitary: new L.LayerGroup(),
            Smoking: new L.LayerGroup(),
            SoftDrinks: new L.LayerGroup(),
        };

        pointsLayerController = L.control.layers(null, overlays).addTo(map);

        pointsControllerShowing = true;
    }
}

/**
 * Zoom to a cluster when it is clicked
 */
function onEachFeature (feature, layer)
{
    if (feature.properties.cluster)
    {
        layer.on('click', function (e)
        {
            const zoomTo = ((map.getZoom() + ZOOM_STEP) > MAX_ZOOM)
                ? MAX_ZOOM
                : (map.getZoom() + ZOOM_STEP);

            map.flyTo(e.latlng, zoomTo, {
                animate: true,
                duration: 2
            });
        });
    }
}

/**
 * On each art point...
 *
 * Todo: Smooth zoom to that piece
 */
function onEachArtFeature (feature, layer)
{
    layer.on('click', function (e)
    {
        map.flyTo(feature.geometry.coordinates, 14, {
            animate: true,
            duration: 10
        });

        const url = new URL(window.location.href);
        url.searchParams.set('lat', feature.geometry.coordinates[0]);
        url.searchParams.set('lon', feature.geometry.coordinates[1]);
        url.searchParams.set('zoom', CLUSTER_ZOOM_THRESHOLD);
        url.searchParams.set('photo', feature.properties.photo_id);

        L.popup(mapHelper.popupOptions)
            .setLatLng(feature.geometry.coordinates)
            .setContent(mapHelper.getMapImagePopupContent(feature.properties, url.toString()))
            .openOn(map);
    });
}

/**
 * On each cleanup in this.$store.state.cleanups.geojson.features
 */
function onEachCleanup (feature, layer)
{
    layer.on('click', function (e)
    {
        const latLng = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];

        map.flyTo(latLng, 14, {
            animate: true,
            duration: 10
        });

        const content = mapHelper.getCleanupContent(feature.properties, userId);

        L.popup(mapHelper.popupOptions)
            .setLatLng(latLng)
            .setContent(content)
            .openOn(map);
    });
}

/**
 * On each cleanup in this.$store.state.cleanups.geojson.features
 */
function onEachMerchant (feature, layer)
{
    layer.on('click', function (e)
    {
        const latLng = [feature.geometry.coordinates[1], feature.geometry.coordinates[0]];

        map.flyTo(latLng, 14, {
            animate: true,
            duration: 10
        });

        const content = mapHelper.getMerchantContent(feature.properties, userId);

        L.popup(mapHelper.popupOptions)
            .setLatLng(latLng)
            .setContent(content)
            .openOn(map);

            // Initialize Swiper
            const mySwiper = new Swiper('.swiper-container', {
                navigation: {
                    prevEl: '#prevButton',
                    nextEl: '#nextButton',
                },
            });
    });
}

/**
 * Get any active layers
 *
 * @return layers|null
 */
function getActiveLayers ()
{
    let layers = [];

    // This is not ideal but it works as the indexes are in the same order
    pointsLayerController._layerControlInputs.forEach((lyr, index) => {
        if (lyr.checked)
        {
            // temp fix to rename petsurprise from map to the dogshit table
            const name = (pointsLayerController._layers[index].name.toLowerCase() === 'petsurprise')
                ? 'dogshit'
                : pointsLayerController._layers[index].name.toLowerCase();

            layers.push(name);
        }
    });

    return (layers.length > 0)
        ? layers
        : null;
}

export default {
    name: 'Supercluster',
    components: {
        LiveEvents
    },
    props: {
        'activeLayer': {
            default: 'clusters',
            required: false
        }
    },
    data () {
        return {
            visiblePoints: []
        }
    },
    mounted () {
        /** 0: Hack! Bind variable outside of vue scope */
        window.olm_map = this;

        /** 1. Create map object */
        map = L.map('openlittermap', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        map.scrollWheelZoom = true;

        this.flyToLocationFromURL();

        const date = new Date();
        const year = date.getFullYear();

        /** 2. Add tiles, attribution, set limits */
        const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: MAX_ZOOM,
            minZoom: MIN_ZOOM
        }).addTo(map);

        map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year + ' Clustering @ MapBox');

        // Clusters
        clusters = L.geoJSON(null, {
            pointToLayer: createClusterIcon,
            onEachFeature: onEachFeature,
        })

        if (this.$store.state.globalmap.geojson?.features) {
            clusters.addData(this.$store.state.globalmap.geojson.features);
        }

        if (this.activeLayer === "clusters") {
            clusters.addTo(map);
        }

        // Art
        litterArtPoints = L.geoJSON(null, {
            pointToLayer: createArtIcon,
            onEachFeature: onEachArtFeature
        });

        if (this.$store.state.globalmap?.artData?.features) {
            litterArtPoints.addData(this.$store.state.globalmap.artData.features);
        }

        // Cleanups
        if (this.$store.state.cleanups.geojson)
        {
            cleanups = L.geoJSON(this.$store.state.cleanups.geojson, {
                onEachFeature: onEachCleanup,
                pointToLayer: createCleanupIcon
            });
        }

        // When we are viewing Cleanups and the map is clicked,
        // We want to extract the coordinates
        if (this.activeLayer === "cleanups")
        {
            cleanups.addTo(map);

            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;

                window.olm_map.$store.commit('setCleanupLocation', {
                    lat,
                    lng
                });
            });

            if (this.$route.params.hasOwnProperty('invite_link'))
            {
                const cleanup = this.$store.state.cleanups.cleanup;
                const latLng = [cleanup.lat, cleanup.lon];

                map.flyTo(latLng, 16, {
                    animate: true,
                    duration: 5
                });

                const userId = (this.$store.state.user.auth)
                    ? this.$store.state.user.user.id
                    : null;

                L.popup(mapHelper.popupOptions)
                    .setLatLng(latLng)
                    .setContent(mapHelper.getCleanupContent(cleanup, userId))
                    .openOn(map);
            }
        }

        // For Cleanups, we need to know if the current userId has joined a cleanup
        if (this.$store.state.user.auth) {
            userId = this.$store.state.user.user.id;
        }

        // Merchants
        if (Object.keys(this.$store.state.merchants.geojson).length > 0)
        {
            merchants = L.geoJSON(this.$store.state.merchants.geojson, {
                onEachFeature: onEachMerchant,
                pointToLayer: createCleanupIcon
            });
        }

        // When we are viewing Cleanups and the map is clicked,
        // We want to extract the coordinates
        if (this.activeLayer === "merchants")
        {
            merchants.addTo(map);

            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;

                window.olm_map.$store.commit('setMerchantLocation', {
                    lat,
                    lng
                });
            });
        }

        // For Cleanups, we need to know if the current userId has joined a cleanup
        if (this.$store.state.user.auth) {
            userId = this.$store.state.user.user.id;
        }

        map.on('moveend', this.update);

        createGlobalGroups();

        map.on('overlayadd', this.update);
        map.on('overlayremove', this.update);
        map.on('popupopen', mapHelper.scrollPopupToBottom);
        map.on('popupclose', () => {
            const url = new URL(window.location.href);
            url.searchParams.delete('photo');
            window.history.pushState(null, '', url);
        });
        map.on('zoom', () => {
            if (points?.remove) {
                points.remove();
            }
        });

        this.setupYearDropdown();
    },
    methods: {
        /**
         * The user dragged or zoomed the map, or changed a category
         */
        async update ()
        {
            this.updateLocationInURL();

            const bounds = map.getBounds();

            const bbox = {
                'left': bounds.getWest(),
                'bottom': bounds.getSouth(),
                'right': bounds.getEast(),
                'top': bounds.getNorth()
            };

            const zoom = Math.round(map.getZoom());

            // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
            // At these levels, we just load all global data for now
            if (zoom === 2 && zoom === prevZoom) return;
            if (zoom === 3 && zoom === prevZoom) return;
            if (zoom === 4 && zoom === prevZoom) return;
            if (zoom === 5 && zoom === prevZoom) return;

            // Remove points when zooming out
            if (points)
            {
                clusters.clearLayers();
                points.remove();
            }

            // Get the year from url
            const searchParams = new URLSearchParams(window.location.search);
            const year = parseInt(searchParams.get('year')) || null;
            const fromDate = searchParams.get('fromDate') || null;
            const toDate = searchParams.get('toDate') || null;
            const username = searchParams.get('username') || null;

            // Get Clusters or Points
            if (zoom < CLUSTER_ZOOM_THRESHOLD)
            {
                createGlobalGroups();

                // Remove photo id and filters from the url when zooming out
                const url = new URL(window.location.href);
                url.searchParams.delete('fromDate');
                url.searchParams.delete('toDate');
                url.searchParams.delete('username');
                url.searchParams.delete('photo');
                window.history.pushState(null, '', url);

                await axios.get('/global/clusters', {
                    params: {
                        zoom,
                        bbox,
                        year
                    }
                })
                .then(response => {
                    console.log('get_clusters.update', response);

                    clusters.clearLayers();
                    clusters.addData(response.data);
                })
                .catch(error => {
                    console.error('get_clusters.update', error);
                });
            }
            else
            {
                createPointGroups()

                const layers = getActiveLayers();

                await axios.get('/global/points', {
                    params: {
                        zoom,
                        bbox,
                        layers,
                        year,
                        fromDate,
                        toDate,
                        username
                    }
                })
                .then(response => {
                    console.log('get_global_points', response);

                    this.visiblePoints = response.data.features;

                    // Clear layer if prev layer is cluster.
                    if (prevZoom < CLUSTER_ZOOM_THRESHOLD)
                    {
                        clusters.clearLayers();
                    }

                    const data = response.data.features.map(feature => {
                        return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
                    });

                    // New way using webGL
                    points = glify.points({
                        map,
                        data,
                        size: 10,
                        color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // 14, 209, 69 / 255
                        click:  (e, point, xy) => {
                            const feature = response.data.features.find(f => {
                                return f.geometry.coordinates[0] === point[0]
                                    && f.geometry.coordinates[1] === point[1];
                            });

                            if (!feature) {
                                return;
                            }

                            // Set the photo id in the url when opening a photo
                            const url = new URL(window.location.href);
                            url.searchParams.set('photo', feature.properties.photo_id);
                            window.history.pushState(null, '', url);

                            return this.renderLeafletPopup(feature, e.latlng)
                        },
                    });

                    // If there is a photo id in the url, open it
                    let urlParams = new URLSearchParams(window.location.search);
                    let photoId = parseInt(urlParams.get('photo'));
                    if (photoId) {
                        if (!this.visiblePoints.length) return;
                        const feature = this.visiblePoints.find(f => f.properties.photo_id === photoId);
                        if (feature) {
                            this.renderLeafletPopup(
                                feature,
                                [feature.geometry.coordinates[0], feature.geometry.coordinates[1]]
                            )
                        }
                    }
                })
                .catch(error => {
                    console.error('get_global_points', error);
                });
            }

            prevZoom = zoom;
        },

        /**
         * Helper method to create a Popup
         *
         * @param feature
         * @param latLng
         */
        renderLeafletPopup (feature, latLng)
        {
            const url = new URL(window.location.href);
            url.searchParams.set('lat', feature.geometry.coordinates[0]);
            url.searchParams.set('lon', feature.geometry.coordinates[1]);
            url.searchParams.set('zoom', CLUSTER_ZOOM_THRESHOLD);
            url.searchParams.set('photo', feature.properties.photo_id);

            L.popup(mapHelper.popupOptions)
                .setLatLng(latLng)
                .setContent(mapHelper.getMapImagePopupContent(feature.properties, url.toString()))
                .openOn(map);
        },

        /**
         * Goes to the location and zoom given in the URL
         * Params are: lat, lon, zoom, photo
         */
        flyToLocationFromURL ()
        {
            let urlParams = new URLSearchParams(window.location.search);
            let latitude = parseFloat(urlParams.get('lat') || 0);
            let longitude = parseFloat(urlParams.get('lon') || 0);
            let zoom = parseFloat(urlParams.get('zoom') || MIN_ZOOM);
            let photoId = parseInt(urlParams.get('photo'));

            // Validate lat, lon, and zoom level
            latitude = (latitude < -85 || latitude > 85) ? 0 : latitude;
            longitude = (longitude < -180 || longitude > 180) ? 0 : longitude;
            zoom = (zoom < 2 || zoom > MAX_ZOOM) ? MIN_ZOOM : zoom;

            if (latitude === 0 && longitude === 0 && zoom === 2) return;

            this.flyToLocation({latitude, longitude, zoom, photoId});
        },

        /**
         * Updates the url with the photoId
         * and goes to the location
         */
        updateUrlPhotoIdAndFlyToLocation (location)
        {
            const zoom = Math.round(map.getZoom());
            const url = new URL(window.location.href);
            url.searchParams.set('photo', location.photoId);
            window.history.pushState(null, '', url);

            const flyDistanceInMeters = map.distance(
                map.getCenter(),
                [location.latitude, location.longitude]
            )

            // If we're viewing points and moving within 2km
            if (zoom >= CLUSTER_ZOOM_THRESHOLD && flyDistanceInMeters <= 2000) {
                this.flyToLocation({...location, duration: 1});
            } else {
                this.flyToLocation(location);
            }
        },

        /**
         * Goes to the location provided
         */
        flyToLocation (location)
        {
            const latLng = [location.latitude, location.longitude];
            const zoom = location.photoId && Math.round(location.zoom) < CLUSTER_ZOOM_THRESHOLD
                ? CLUSTER_ZOOM_THRESHOLD
                : location.zoom;

            map.flyTo(latLng, zoom, {
                animate: true,
                duration: location.duration ?? 5
            });
        },

        /**
         * Simply updates the URL
         * with the current map location and zoom
         */
        updateLocationInURL ()
        {
            const location = map.getCenter();

            const url = new URL(window.location.href);
            url.searchParams.set('lat', location.lat);
            url.searchParams.set('lon', location.lng);
            url.searchParams.set('zoom', map.getZoom());

            window.history.pushState(null, '', url);
        },

        /**
         * Initializes the dropdown to select
         * the year for which to show clusters, and points
         */
        setupYearDropdown ()
        {
            dropdown.initialize();

            let years = [
                {label: 'All Time', value: '*'}
            ];

            for (let y = new Date().getFullYear(); y >= 2017; y--)
            {
                years.push({label: y.toString(), value: y.toString()});
            }

            let selectedYear = parseInt((new URLSearchParams(window.location.search)).get('year')) || '*';

            L.control.select({
                position: 'topleft',
                selectedDefault: selectedYear.toString(),
                items: years,
                onSelect: (function (year)
                {
                    const url = new URL(window.location.href);

                    if (year === '*')
                    {
                        url.searchParams.delete('year');
                    }
                    else
                    {
                        url.searchParams.set('year', year);
                    }

                    // reload the site
                    window.history.pushState(null, '', url);
                    window.location.reload();
                })
            }).addTo(map);
        }
    }
};
</script>

<style lang="scss" src="./select-dropdown.scss"></style>

<style>

    #openlittermap {
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

    .leaflet-control {
        pointer-events: visiblePainted !important;
    }

    .leaflet-cleanup-container {
        padding: 1em 2em;
    }

    .leaflet-cleanup-container p {
        margin: 10px 0 !important;
    }

    .updated-by-admin {
        margin-top: 5px !important;
        margin-bottom: 0 !important;
        margin-right: 2em;
        font-size: 13px !important;
        font-style: italic !important;
    }

</style>
