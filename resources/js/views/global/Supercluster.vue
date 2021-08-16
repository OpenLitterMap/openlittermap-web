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
var clusters;
var litterArtPoints;
var heatmapLayer;
var points;
var prevZoom = MIN_ZOOM;

var pointsLayerController;
var globalLayerController;
var pointsControllerShowing = false;
var globalControllerShowing = false;

const green_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/dot.png',
    iconSize: [10, 10]
});

const grey_dot = L.icon({
    iconUrl: './images/vendor/leaflet/dist/grey-dot.jpg',
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
        layer.on('click', function (e) {

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

        const user = (feature.properties.name || feature.properties.username)
            ? `By ${feature.properties.name ? feature.properties.name : ''} ${ feature.properties.username ? '@' + feature.properties.username : ''}`
            : "";

        const team = (feature.properties.team)
            ? `\nTeam ${feature.properties.team}`
            : "";

        // todo - increase the dimensions of each art image
        L.popup()
            .setLatLng(feature.geometry.coordinates)
            .setContent(
                '<img src= "' + feature.properties.filename + '" class="litter-img art-litter-image" />'
                    + '<div class="litter-img-container">'
                        + '<p>Taken on ' + moment(feature.properties.datetime).format('LLL') +'</p>'
                        + user
                        + team
                    + '</div>'
            )
            .openOn(map);
    });
}

/**
 * The user dragged or zoomed the map, or changed a category
 */
async function update ()
{
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

    // Get Clusters or Points
    if (zoom < CLUSTER_ZOOM_THRESHOLD)
    {
        createGlobalGroups();

        await axios.get('/global/clusters', {
            params: {
                zoom,
                bbox
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
                layers
            }
        })
        .then(response => {
            console.log('get_global_points', response);

            // Clear layer if prev layer is cluster.
            if (prevZoom < CLUSTER_ZOOM_THRESHOLD)
            {
                clusters.clearLayers();
            }

            const data = response.data.features.map(feature => {
                return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
            });

            points = glify.points({
                map,
                data,
                size: 10,
                color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // 14, 209, 69 / 255
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
                                '<img src= "' + f.properties.filename + '" class="litter-img" />'
                                    + '<div class="litter-img-container">'
                                        + '<p class="mb5p">' + tags + ' </p>'
                                        + '<p>Taken on ' + moment(f.properties.datetime).format('LLL') +'</p>'
                                        + user
                                        + team
                                    + '</div>'
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
    pointsLayerController._layerControlInputs.forEach((lyr, index) => {
        if (lyr.checked)
        {
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
        clusters = L.geoJSON(null, {
            pointToLayer: createClusterIcon,
            onEachFeature: onEachFeature,
        }).addTo(map);

        clusters.addData(this.$store.state.globalmap.geojson.features);

        litterArtPoints = L.geoJSON(null, {
            pointToLayer: createArtIcon,
            onEachFeature: onEachArtFeature
        });

        litterArtPoints.addData(this.$store.state.globalmap.artData.features);

        map.on('moveend', function ()
        {
            update();
        });

        createGlobalGroups();

        map.on('overlayadd', update);
        map.on('overlayremove', update)
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

    .leaflet-pop-content-wrapper {
        padding: 0 !important;
    }

    .leaflet-popup-content {
        margin: 0 !important;
    }

    .litter-img-container {
        padding: 0 1em 1em 1em;
    }

    .litter-img {
        border-top-left-radius: 6px;
        border-top-right-radius: 6px;
        max-width: 100%;
    }

    .art-litter-image {

    }

</style>
