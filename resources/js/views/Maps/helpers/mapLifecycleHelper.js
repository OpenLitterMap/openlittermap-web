import L from 'leaflet';
import { MIN_ZOOM, MAX_ZOOM } from './constants.js';
import { clustersHelper } from './clustersHelper.js';
import { pointsHelper } from './pointsHelper.js';

export const mapLifecycleHelper = {
    /**
     * Initialize the map and all its components
     */
    async initializeMap({ clustersStore, $loading, t }) {
        const urlParams = new URLSearchParams(window.location.search);
        const clusterFilters = clustersHelper.getClusterFiltersFromURL();
        const initialPage = parseInt(urlParams.get('page')) || 1;

        // Load initial cluster data
        await clustersHelper.loadClusters({
            clustersStore,
            zoom: 2,
            year: clusterFilters.year,
        });

        // Create map instance
        const mapInstance = L.map('openlittermap', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 2,
        });

        // Set initial zoom level
        let currentZoom = MIN_ZOOM;

        // Check for instant load parameters
        const shouldLoadInstantly = urlParams.get('load') === 'true';
        const hasLocationParams = urlParams.get('lat') && urlParams.get('lon') && urlParams.get('zoom');

        if (shouldLoadInstantly && hasLocationParams) {
            const lat = parseFloat(urlParams.get('lat'));
            const lon = parseFloat(urlParams.get('lon'));
            const zoom = parseFloat(urlParams.get('zoom'));

            mapInstance.setView([lat, lon], zoom, { animate: false });
            currentZoom = zoom;
        }

        // Add tile layer
        const mapLink = '<a href="https://openstreetmap.org">OpenStreetMap</a>';
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; ' + mapLink + ' & Contributors',
            maxZoom: MAX_ZOOM,
            minZoom: MIN_ZOOM,
        }).addTo(mapInstance);

        // Initialize clusters layer
        const clusters = L.geoJSON(null, {
            pointToLayer: clustersHelper.createClusterIcon,
            onEachFeature: (feature, layer) => clustersHelper.onEachFeature(feature, layer, mapInstance),
        });

        // Add initial cluster data if available
        const clustersData = clustersHelper.getClustersData(clustersStore);
        if (clustersData?.features?.length > 0) {
            clustersHelper.addClustersToMap(clusters, clustersData);
            mapInstance.addLayer(clusters);
        }

        return {
            mapInstance,
            clusters,
            currentZoom,
            currentPage: initialPage,
        };
    },

    /**
     * Cleanup map and all related resources
     */
    cleanup({
        mapInstance,
        clusters,
        points,
        updateTimeout,
        currentPointsRequest,
        currentStatsRequest,
        router,
        route,
    }) {
        // Cancel any pending requests
        if (updateTimeout) {
            clearTimeout(updateTimeout);
        }

        if (currentPointsRequest) {
            currentPointsRequest.abort();
        }

        if (currentStatsRequest) {
            currentStatsRequest.abort();
        }

        if (mapInstance) {
            // Remove event listeners
            mapInstance.off('moveend');
            mapInstance.off('popupclose');
            mapInstance.off('zoom');

            // Remove glify points if present
            if (points) {
                pointsHelper.clearPoints(points, mapInstance);
            }

            // Remove clusters
            clustersHelper.clearClusters(clusters);

            // Remove the map
            mapInstance.remove();
        }

        // Remove all params from the URL
        router.replace({ path: route.path });
    },
};
