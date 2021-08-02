<template>
    <div style="height: 100%;" @click="closeButtons">
        <div id="super" ref="super" />

        <!-- Change language -->
        <!-- <Languages />-->

        <!-- Change data -->
        <!-- <global-dates />-->

        <!-- Call to Action -->
        <!-- <global-info />-->

        <!-- Live Events -->
        <live-events />
    </div>
</template>

<script>
import Languages from '../../components/global/Languages';
// import GlobalDates from '../../components/global/GlobalDates'
import LiveEvents from '../../components/LiveEvents';
// import GlobalInfo from '../../components/global/GlobalInfo'
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
var prevZoom = MIN_ZOOM;
var points;

var layerControls;
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
        return feature.properties.verified === 2
            ? L.marker(latlng, { icon: green_dot })
            : L.marker(latlng, { icon: grey_dot });
    }

    let count = feature.properties.point_count;
    let size = count < MEDIUM_CLUSTER_SIZE ? 'small' : count < LARGE_CLUSTER_SIZE ? 'medium' : 'large';

    let icon = L.divIcon({
        html: '<div class="mi"><span class="ma">' + feature.properties.point_count_abbreviated + '</span></div>',
        className: 'marker-cluster-' + size,
        iconSize: L.point(40, 40)
    });

    return L.marker(latlng, { icon });
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
 * The user dragged or zoomed the map
 *
 * Todo: remove glify points when the user moves the map, and is above zoom threshold
 */
async function update (layers = null)
{
    const bounds = map.getBounds();

    let bbox = {
        'left': bounds.getWest(),
        'bottom': bounds.getSouth(),
        'right': bounds.getEast(),
        'top': bounds.getNorth(),
    };

    let zoom = Math.round(map.getZoom());

    // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
    // At these levels, we just load all global data for now
    if (zoom === 2 && zoom === prevZoom) return;
    if (zoom === 3 && zoom === prevZoom) return;
    if (zoom === 4 && zoom === prevZoom) return;
    if (zoom === 5 && zoom === prevZoom) return;

    // If the zoom is less than 17, we want to load cluster data
    if (zoom < CLUSTER_ZOOM_THRESHOLD)
    {
        map.removeControl(layerControls);

        await axios.get('clusters', {
            params: {
                zoom,
                bbox
            }
        })
        .then(response => {
            console.log('get_clusters.update', response);

            markers.clearLayers();
            markers.addData(response.data);

            // if (points) points.remove();
        })
        .catch(error => {
            console.error('get_clusters.update', error);
        });
    }
    // otherwise, get point data
    else
    {
        map.addControl(layerControls);
        if (points) points.remove();

        await axios.get('global-points', {
            params: {
                zoom,
                bbox,
                layers
            },
        })
        .then(response => {
            console.log('get_global_points', response);

            // Clear layer if prev layer is cluster.
            if (prevZoom < CLUSTER_ZOOM_THRESHOLD)
            {
                markers.clearLayers();
            }

            const data = response.data.features.map(feature => {
                return [ feature.geometry.coordinates[1], feature.geometry.coordinates[0]];
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
                        return feature.geometry.coordinates[0] === point[1]
                            && feature.geometry.coordinates[1] === point[0];
                    });

                    // console.log({ f });

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
    prevZoom = zoom; // hold previous zoom
}

/**
 * A Layer has been toggled
 *
 * Make a get request with the active layers
 *
 * Todo - find a better way of getting the active layers
 */
function changeLayers ()
{
    let layers = [];

    // This is not ideal but it works as the indexes are in the same order
    layerControls._layerControlInputs.forEach((lyr, index) => {
        if (lyr.checked)
        {
            layers.push(layerControls._layers[index].name.toLowerCase());
        }
    });

    update(layers);
}

export default {
    name: 'Supercluster',
    components: {
        Languages,
        // GlobalDates,
        LiveEvents,
        // GlobalInfo
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

        map.on('moveend', function ()
        {
            update();
        });

        this.createGroups();

        map.on('overlayadd', changeLayers);
        map.on('overlayremove', changeLayers)
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
         * Add layer toggle to the map
         */
        createGroups ()
        {
            smokingGroup = new L.LayerGroup();
            foodGroup = new L.LayerGroup();
            coffeeGroup = new L.LayerGroup();
            alcoholGroup = new L.LayerGroup();
            softdrinksGroup = new L.LayerGroup();
            sanitaryGroup = new L.LayerGroup();
            otherGroup = new L.LayerGroup();
            coastalGroup = new L.LayerGroup();
            brandsGroup = new L.LayerGroup();
            dogshitGroup = new L.LayerGroup();
            dumpingGroup = new L.LayerGroup();
            industrialGroup = new L.LayerGroup();

            /** 8. Create overlays toggle menu */
            let overlays = {
                Alcohol: alcoholGroup,
                Brands: brandsGroup,
                Coastal: coastalGroup,
                Coffee: coffeeGroup,
                Dumping: dumpingGroup,
                Food: foodGroup,
                Industrial: industrialGroup,
                Other: otherGroup,
                PetSurprise: dogshitGroup,
                Sanitary: sanitaryGroup,
                Smoking: smokingGroup,
                SoftDrinks: softdrinksGroup,
            };

            // Added when zoom above
            layerControls = L.control.layers(null, overlays);
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
