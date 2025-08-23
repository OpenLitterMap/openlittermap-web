import L from 'leaflet';
import { removeGlifyPoints } from './glifyHelpers.js';
import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';

// Cluster size constants
const MEDIUM_CLUSTER_SIZE = 100;
const LARGE_CLUSTER_SIZE = 1000;

// Define marker icons for individual points
const green_dot = L.divIcon({
    className: 'verified-marker',
    html: '<div class="verified-dot"></div>',
    iconSize: [8, 8],
    iconAnchor: [4, 4],
});

const grey_dot = L.divIcon({
    className: 'unverified-marker',
    html: '<div class="unverified-dot"></div>',
    iconSize: [8, 8],
    iconAnchor: [4, 4],
});

export const clustersHelper = {
    /**
     * Create cluster icon for map markers
     */
    createClusterIcon: (feature, latLng) => {
        // Check if this is an individual point (not a cluster)
        if (!feature.properties.cluster) {
            return feature.properties.verified === 2
                ? L.marker(latLng, { icon: green_dot })
                : L.marker(latLng, { icon: grey_dot });
        }

        // This is a cluster - use the original cluster logic
        const count = feature.properties.point_count;
        const size = count < MEDIUM_CLUSTER_SIZE ? 'small' : count < LARGE_CLUSTER_SIZE ? 'medium' : 'large';

        const icon = L.divIcon({
            html:
                '<div class="mi"><span class="mx-auto my-auto" style="color: #4a4a4a !important;">' +
                feature.properties.point_count_abbreviated +
                '</span></div>',
            className: 'marker-cluster-' + size,
            iconSize: L.point(40, 40),
        });

        return L.marker(latLng, { icon });
    },

    /**
     * Handle each feature when adding to cluster layer
     */
    onEachFeature: (feature, layer, mapInstance) => {
        // Only add click handler for clusters (not individual points)
        if (feature.properties && (feature.properties.cluster || feature.properties.point_count)) {
            layer.on('click', () => {
                // FIX: Reduced zoom step from +2 to +1 for smoother transition
                const currentZoom = mapInstance.getZoom();
                const targetZoom = Math.min(currentZoom + 1, CLUSTER_ZOOM_THRESHOLD);
                mapInstance.setView([feature.geometry.coordinates[1], feature.geometry.coordinates[0]], targetZoom, {
                    animate: true,
                    duration: 0.5,
                });
            });
        }
    },

    /**
     * Handle cluster view (zoom < CLUSTER_ZOOM_THRESHOLD)
     */
    async handleClusterView({ clustersStore, clusters, zoom, bbox, year, points, mapInstance }) {
        // Remove any remaining glify points
        if (points) {
            removeGlifyPoints(points, mapInstance);
        }

        // Remove photo id and filters from the url when zooming out
        this.cleanupClustersURL();

        try {
            await clustersStore.GET_CLUSTERS({ zoom, bbox, year });

            clusters.clearLayers();
            clusters.addData(clustersStore.clustersGeojson);
        } catch (error) {
            console.error('get clusters error', error);
        }

        return null; // No points in cluster view
    },

    /**
     * Load cluster data
     */
    async loadClusters({ clustersStore, zoom, bbox = null, year = null }) {
        try {
            await clustersStore.GET_CLUSTERS({ zoom, bbox, year });
            return clustersStore.clustersGeojson;
        } catch (error) {
            console.error('Failed to load clusters:', error);
            throw error;
        }
    },

    /**
     * Add clusters to map layer
     */
    addClustersToMap(clusters, clustersData) {
        if (clustersData && clustersData.features.length > 0) {
            clusters.clearLayers();
            clusters.addData(clustersData);
        }
    },

    /**
     * Clear clusters from map
     */
    clearClusters(clusters) {
        if (clusters) {
            clusters.clearLayers();
        }
    },

    /**
     * Remove cluster-related URL parameters
     */
    cleanupClustersURL() {
        const url = new URL(window.location.href);
        url.searchParams.delete('fromDate');
        url.searchParams.delete('toDate');
        url.searchParams.delete('username');
        url.searchParams.delete('photo');
        url.searchParams.delete('page');
        window.history.pushState(null, '', url);
    },

    /**
     * Check if we should show clusters (below cluster zoom threshold)
     */
    shouldShowClusters(zoom) {
        return zoom < CLUSTER_ZOOM_THRESHOLD;
    },

    /**
     * Get cluster data from store
     */
    getClustersData(clustersStore) {
        return clustersStore.clustersGeojson;
    },

    /**
     * Check if zoom level should trigger cluster reload
     */
    shouldReloadClusters(zoom, prevZoom) {
        // Skip reload if just panning at low zoom levels
        if ([2, 3, 4, 5].includes(zoom) && zoom === prevZoom) {
            return false;
        }
        return true;
    },

    /**
     * Handle transition from points to clusters view
     */
    handlePointsToClusterTransition(clusters, points, mapInstance) {
        // Remove any glify points
        if (points) {
            removeGlifyPoints(points, mapInstance);
        }

        // Clear and prepare clusters layer
        clusters.clearLayers();

        // Clean up URL
        this.cleanupClustersURL();

        return null; // Return null points
    },

    /**
     * Handle transition from clusters to points view
     */
    handleClusterToPointsTransition(clusters) {
        // Clear clusters when switching to points view
        clusters.clearLayers();
    },

    /**
     * Get initial cluster parameters from URL
     */
    getClusterFiltersFromURL() {
        const searchParams = new URLSearchParams(window.location.search);
        return {
            year: parseInt(searchParams.get('year')) || null,
        };
    },
};
