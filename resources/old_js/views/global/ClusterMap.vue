<template>
    <div class="h100">
        <div id="map" ref="map" />
    </div>
</template>

<script>

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

export default {
    name: 'ClusterMap',
    props: ['clustersUrl', 'pointsUrl'],
    data() {
        return {
            map: null,
            clusters: [],
            points: [],
            prevZoom: MIN_ZOOM,
            pointsLayerController: null,
            pointsControllerShowing: false,
            grey_dot: null,
            green_dot: null
        }
    },
    async mounted ()
    {
        await this.setup();
    },
    watch: {
        async clustersUrl ()
        {
            // Cleaning is needed since this map
            // doesn't clean up after itself properly xD
            this.map.remove();
            this.map = null;
            this.prevZoom = MIN_ZOOM;

            if (this.points?.remove) {
                this.points.resetVertices();
                this.points.remove();
            }
            this.clusters.clearLayers();

            this.clusters = null;
            this.points = null;
            this.pointsLayerController = null;
            this.pointsControllerShowing = false;

            await this.setup();
        }
    },
    methods: {
        async setup()
        {
            /** 1. Create map object */
            this.map = L.map('map', {
                center: [0, 0],
                zoom: MIN_ZOOM,
                scrollWheelZoom: false,
                smoothWheelZoom: true,
                smoothSensitivity: 1,
            });

            this.map.scrollWheelZoom = true;

            const year = (new Date()).getFullYear();

            /** 2. Add tiles, attribution, set limits */
            const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
                maxZoom: MAX_ZOOM,
                minZoom: MIN_ZOOM
            }).addTo(this.map);

            this.map.attributionControl.addAttribution('Litter data &copy OpenLitterMap & Contributors ' + year + ' Clustering @ MapBox');

            // Empty Layer Group that will receive the clusters data on the fly.
            this.clusters = L.geoJSON(null, {
                pointToLayer: this.createClusterIcon,
                onEachFeature: this.onEachFeature,
            }).addTo(this.map);

            await this.getClusters(2, null);

            this.map.on('moveend', this.update);
            this.map.on('overlayadd', this.update);
            this.map.on('overlayremove', this.update);
            this.map.on('popupopen', mapHelper.scrollPopupToBottom);
            this.map.on('zoom', () => {
                if (this.points?.remove) {
                    this.points.remove();
                }
            });

            this.green_dot = L.icon({
                iconUrl: './images/vendor/leaflet/dist/dot.png',
                iconSize: [10, 10]
            });

            this.grey_dot = L.icon({
                iconUrl: './images/vendor/leaflet/dist/grey-dot.jpg',
                iconSize: [13, 10]
            });
        },

        /**
         * Makes a request to the given clusters endpoint
         * and fills the clusters object
         *
         * @param zoom
         * @param bbox
         * @returns {Promise<void>}
         */
        async getClusters (zoom, bbox)
        {
            await axios.get(this.clustersUrl, {
                params: {
                    zoom,
                    bbox
                }
            })
                .then(response =>
                {
                    console.log('get_map_clusters', response);

                    this.clusters.clearLayers();
                    this.clusters.addData(response.data);
                })
                .catch(error =>
                {
                    console.error('get_map_clusters', error);
                })
                .finally(() => this.$emit('loading-complete'));
        },

        /**
         * Makes a request to the given points endpoint
         * and fills the points object
         *
         * @param zoom
         * @param bbox
         * @param layers
         */
        async getPoints (zoom, bbox, layers)
        {
            await axios.get(this.pointsUrl, {
                params: {
                    zoom,
                    bbox,
                    layers
                }
            })
                .then(response =>
                {
                    console.log('get_map_points', response);

                    // Clear layer if prev layer is cluster.
                    if (this.prevZoom < CLUSTER_ZOOM_THRESHOLD)
                    {
                        this.clusters.clearLayers();
                    }

                    const data = response.data.features.map(feature =>
                    {
                        return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
                    });

                    // New way using webGL
                    this.points = glify.points({
                        map: this.map,
                        data,
                        size: 10,
                        color: {r: 0.054, g: 0.819, b: 0.27, a: 1}, // 14, 209, 69 / 255
                        click: (e, point, xy) =>
                        {
                            const feature = response.data.features.find(f =>
                            {
                                return f.geometry.coordinates[0] === point[0]
                                    && f.geometry.coordinates[1] === point[1];
                            });

                            if (!feature)
                            {
                                return;
                            }

                            return this.renderLeafletPopup(feature, e.latlng)
                        },
                    });
                })
                .catch(error =>
                {
                    console.error('get_map_points', error);
                });
        },

        /**
         * The user dragged or zoomed the map, or changed a category
         */
        async update ()
        {
            const bounds = this.map.getBounds();

            const bbox = {
                'left': bounds.getWest(),
                'bottom': bounds.getSouth(),
                'right': bounds.getEast(),
                'top': bounds.getNorth()
            };

            const zoom = Math.round(this.map.getZoom());

            // We don't want to make a request at zoom level 2-5 if the user is just panning the map.
            // At these levels, we just load all global data for now
            if (zoom === this.prevZoom && [2, 3, 4, 5].indexOf(zoom) >= 0) return;

            // Remove points when zooming out
            if (this.points?.remove)
            {
                this.clusters.clearLayers();
                this.points.remove();
            }

            // Get Clusters or Points
            if (zoom < CLUSTER_ZOOM_THRESHOLD)
            {
                await this.getClusters(zoom, bbox);
            }
            else
            {
                this.createPointGroups()

                const layers = this.getActiveLayers();

                await this.getPoints(zoom, bbox, layers);
            }

            this.prevZoom = zoom;
        },

        /**
         * Create the cluster or point icon to display for each feature
         */
        createClusterIcon (feature, latlng)
        {
            if (!feature.properties.cluster)
            {
                return (feature.properties.verified === 2)
                    ? L.marker(latlng, { icon: this.green_dot })
                    : L.marker(latlng, { icon: this.grey_dot });
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
        },

        /**
         * Layer Controller when above ZOOM_CLUSTER_THRESHOLD
         */
        createPointGroups ()
        {
            if (!this.pointsControllerShowing)
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

                this.pointsLayerController = L.control.layers(null, overlays).addTo(this.map);

                this.pointsControllerShowing = true;
            }
        },

        /**
         * Zoom to a cluster when it is clicked
         */
        onEachFeature (feature, layer)
        {
            if (feature.properties.cluster)
            {
                const self = this;
                layer.on('click', function (e)
                {
                    const zoomTo = ((self.map.getZoom() + ZOOM_STEP) > MAX_ZOOM)
                        ? MAX_ZOOM
                        : (self.map.getZoom() + ZOOM_STEP);

                    self.map.flyTo(e.latlng, zoomTo, {
                        animate: true,
                        duration: 2
                    });
                });
            }
        },

        /**
         * Get any active layers
         *
         * @return layers|null
         */
        getActiveLayers ()
        {
            let layers = [];

            // This is not ideal but it works as the indexes are in the same order
            this.pointsLayerController._layerControlInputs.forEach((lyr, index) => {
                if (lyr.checked)
                {
                    // temp fix to rename petsurprise from map to the dogshit table
                    const name = (this.pointsLayerController._layers[index].name.toLowerCase() === 'petsurprise')
                        ? 'dogshit'
                        : this.pointsLayerController._layers[index].name.toLowerCase();

                    layers.push(name);
                }
            });

            return (layers.length > 0)
                ? layers
                : null;
        },

        /**
         * Helper method to create a Popup
         *
         * @param feature
         * @param latLng
         */
        renderLeafletPopup (feature, latLng)
        {
            L.popup(mapHelper.popupOptions)
                .setLatLng(latLng)
                .setContent(mapHelper.getMapImagePopupContent(feature.properties))
                .openOn(this.map);
        }
    }
};
</script>

<style lang="css" scoped>

    #map {
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

</style>
