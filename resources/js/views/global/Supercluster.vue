<template>
    <div class="h100">
        <!-- The map & data -->
        <div id="super" ref="super" />

        <!-- Websockets -->
        <LiveEvents />
    </div>
</template>

<script>
import LiveEvents from '../../components/LiveEvents';
// import GlobalDates from '../../components/global/GlobalDates'

import {
    CLUSTER_ZOOM_THRESHOLD,
    MAX_ZOOM,
    MEDIUM_CLUSTER_SIZE,
    LARGE_CLUSTER_SIZE,
    MIN_ZOOM,
    ZOOM_STEP
} from '../../constants';

import L from 'leaflet';
import moment from 'moment';
import './SmoothWheelZoom.js';
import i18n from '../../i18n'

// Todo - fix this export bug (The request of a dependency is an expression...)
import glify from 'leaflet.glify';

var map;
var markers;
var artMarkers;
var prevZoom = MIN_ZOOM;
var points;

var pointsLayerControls;
var globalLayerControls;

var smokingGroup;
var foodGroup;
var coffeeGroup;
var alcoholGroup;
var softdrinksGroup;
var sanitaryGroup;
var otherGroup;
var coastalGroup;
var brandsGroup;
var dogshitGroup;
var dumpingGroup;
var industrialGroup;

const green_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

const grey_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/grey-dot.jpg',
    iconSize: [13, 10]
});

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
 * On each feature, perform this action
 *
 * This is being performed whenever the user drags the map.
 */
function onEachFeature (feature, layer)
{
    if (feature.properties.cluster)
    {
        // Zoom in cluster when click to it
        layer.on('click', function (e) {
            map.setView(e.latlng, map.getZoom() + ZOOM_STEP);
        });
    }
}

/**
 * On each art point...
 */
function onEachArtFeature (feature, layer)
{
    layer.on('click', function (e)
    {
        const user = (feature.properties.name || feature.properties.username)
            ? `By ${feature.properties.name ? feature.properties.name : ''} ${ feature.properties.username ? '@' + feature.properties.username : ''}`
            : "";

        const team = (feature.properties.team)
            ? `\nTeam ${feature.properties.team}`
            : "";

        L.popup()
            .setLatLng(feature.geometry.coordinates)
            .setContent(
                '<p class="mb5p">Litter Art</p>'
                + '<img src= "' + feature.properties.filename + '" class="mw100" />'
                + '<p>Taken on ' + moment(feature.properties.datetime).format('LLL') +'</p>'
                + user
                + team
            )
            .openOn(map);
    });
}

/**
 * The user dragged or zoomed the map
 **/
async function update ()
{
    const bounds = map.getBounds();

    const bbox = {
        'left': bounds.getWest(),
        'bottom': bounds.getSouth(),
        'right': bounds.getEast(),
        'top': bounds.getNorth(),
    };

    const zoom = Math.round(map.getZoom());

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if (zoom === 2 && zoom === prevZoom) return;
    if (zoom === 3 && zoom === prevZoom) return;
    if (zoom === 4 && zoom === prevZoom) return;
    if (zoom === 5 && zoom === prevZoom) return;

    if (points) points.remove();

    if (zoom < CLUSTER_ZOOM_THRESHOLD)
    {
        map.removeControl(pointsLayerControls);
        map.addControl(globalLayerControls);

        await axios.get('/global/clusters', {
            params: {
                zoom,
                bbox
            }
        })
        .then(response => {
            console.log('get_clusters.update', response);

            markers.clearLayers();
            markers.addData(response.data);
        })
        .catch(error => {
            console.error('get_clusters.update', error);
        });
    }
    else
    {
        map.removeControl(globalLayerControls);
        map.addControl(pointsLayerControls);

        const layers = getActiveLayers();

        await axios.get('/global/points', {
            params: {
                zoom,
                bbox,
                layers
            }
        })
        .then(response => {
            console.log('get_global_points', response);

            // Clear layer if prev layer is cluster.
            if (prevZoom < CLUSTER_ZOOM_THRESHOLD)
            {
                markers.clearLayers();
            }

            const data = response.data.features.map(feature => {
                return [ feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
            });

            // New way using webGL
            points = glify.points({
                map,
                data,
                size: 10,
                color: { r: 0.054, g: 0.819, b: 0.27 }, // 14, 209, 69 / 255
                click: (e, point, xy) => {
                    // return false to continue traversing

                    const f = response.data.features.find(feature => {
                        return feature.geometry.coordinates[0] === point[0]
                            && feature.geometry.coordinates[1] === point[1];
                    });

                    if (f)
                    {
                        let tags = '';

                        if (f.properties.result_string)
                        {
                            let a = f.properties.result_string.split(',');

                            a.pop();

                            a.forEach(i => {
                                let b = i.split(' ');

                                tags += i18n.t('litter.' + b[0]) + ': ' + b[1] + ' ';
                            });
                        }
                        else
                        {
                            tags = i18n.t('litter.not-verified');
                        }

                        const user = (f.properties.name || f.properties.username)
                            ? `By ${f.properties.name ? f.properties.name : ''} ${ f.properties.username ? '@' + f.properties.username : ''}`
                            : "";

                        const team = (f.properties.team)
                            ? `\nTeam ${f.properties.team}`
                            : "";

                        L.popup()
                            .setLatLng(e.latlng)
                            .setContent(
                                '<p class="mb5p">' + tags + ' </p>'
                                + '<img src= "' + f.properties.filename + '" class="mw100" />'
                                + '<p>Taken on ' + moment(f.properties.datetime).format('LLL') +'</p>'
                                + user
                                + team
                            )
                            .openOn(map);
                    }

                },
                // hover: (e, pointOrGeoJsonFeature, xy) => {
                //     // do something when a point is hovered
                //     console.log('hovered');
                // }
            });
        })
        .catch(error => {
            console.error('get_global_points', error);
        });
    }

    prevZoom = zoom;
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
    pointsLayerControls._layerControlInputs.forEach((lyr, index) => {
        if (lyr.checked)
        {
            layers.push(pointsLayerControls._layers[index].name.toLowerCase());
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
    mounted ()
    {
        /** 1. Create map object */
        map = L.map('super', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 1,
        });

        map.scrollWheelZoom = true;

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

        // Empty Layer Group that will receive the clusters data on the fly.
        markers = L.geoJSON(null, {
            pointToLayer: createClusterIcon,
            onEachFeature: onEachFeature,
        }).addTo(map);

        markers.addData(this.$store.state.globalmap.geojson.features);

        artMarkers = L.geoJSON(null, {
            pointToLayer: createArtIcon,
            onEachFeature: onEachArtFeature
        });

        artMarkers.addData(this.$store.state.globalmap.artData.features);

        map.on('moveend', function ()
        {
            update();
        });

        this.createPointGroups();
        this.createGlobalGroups();

        map.on('overlayadd', update);
        map.on('overlayremove', update)
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
         * Layer Controller when above ZOOM_CLUSTER_THRESHOLD
         */
        createPointGroups ()
        {
            /** 8. Create overlays toggle menu */
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

            pointsLayerControls = L.control.layers(null, overlays);
        },

        /**
         * Layer controller when below ZOOM_CLUSTER_THRESHOLD
         */
        createGlobalGroups ()
        {
            globalLayerControls = L.control.layers(null, null).addTo(map);

            globalLayerControls.addOverlay(markers, 'Global');
            globalLayerControls.addOverlay(artMarkers, 'Litter Art');
        }
    }
};
</script>

<style>

#super {
    height: 100%;
    margin: 0;
    position: relative;
}

.leaflet-marker-icon {
    border-radius: 20px;
}

.mb5p {
    margin-bottom: 5px;
}

.mw100 {
    max-width: 100%;
}

.mi {
    height: 100%;
    margin: auto;
    display: flex;
    justify-content: center;
    border-radius: 20px;
}

</style>
