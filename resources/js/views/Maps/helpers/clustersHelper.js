import L from 'leaflet';
import { removeGlifyPoints } from './glifyHelpers.js';
import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { urlHelper } from './urlHelper.js';

// Cluster size constants
const MEDIUM_CLUSTER_SIZE = 100;
const LARGE_CLUSTER_SIZE = 1000;

// Cache for marker icons to avoid recreation
const markerIconCache = {
    verified: null,
    unverified: null,
    getVerifiedIcon() {
        if (!this.verified) {
            this.verified = L.divIcon({
                className: 'verified-marker',
                html: '<div class="verified-dot"></div>',
                iconSize: [8, 8],
                iconAnchor: [4, 4],
            });
        }
        return this.verified;
    },
    getUnverifiedIcon() {
        if (!this.unverified) {
            this.unverified = L.divIcon({
                className: 'unverified-marker',
                html: '<div class="unverified-dot"></div>',
                iconSize: [8, 8],
                iconAnchor: [4, 4],
            });
        }
        return this.unverified;
    },
};

export const clustersHelper = {
    /**
     * Create cluster icon for map markers with caching
     */
    createClusterIcon: (feature, latLng) => {
        // Check if this is an individual point (not a cluster)
        if (!feature.properties.cluster) {
            return feature.properties.verified === 2
                ? L.marker(latLng, { icon: markerIconCache.getVerifiedIcon() })
                : L.marker(latLng, { icon: markerIconCache.getUnverifiedIcon() });
        }

        // This is a cluster - use optimized cluster logic
        const count = feature.properties.point_count;
        const size = count < MEDIUM_CLUSTER_SIZE ? 'small' : count < LARGE_CLUSTER_SIZE ? 'medium' : 'large';

        const colors = {
            small: { outer: 'rgba(181,226,140,0.6)', inner: 'rgba(110,204,57,0.6)' },
            medium: { outer: 'rgba(241,211,87,0.6)', inner: 'rgba(240,194,12,0.6)' },
            large: { outer: 'rgba(253,156,115,0.6)', inner: 'rgba(241,128,23,0.6)' },
        };
        const c = colors[size];

        const icon = L.divIcon({
            html: `<div style="width:40px;height:40px;border-radius:50%;background:${c.outer};display:flex;align-items:center;justify-content:center;"><div style="width:30px;height:30px;border-radius:50%;background:${c.inner};display:flex;align-items:center;justify-content:center;"><span style="color:#4a4a4a;font-weight:500;font-size:11px;line-height:1;">${
                feature.properties.point_count_abbreviated
            }</span></div></div>`,
            className: 'marker-cluster',
            iconSize: L.point(40, 40),
            iconAnchor: L.point(20, 20),
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
                const currentZoom = mapInstance.getZoom();
                // More reasonable zoom step for better UX
                const targetZoom = Math.min(currentZoom + 1, CLUSTER_ZOOM_THRESHOLD);

                mapInstance.setView([feature.geometry.coordinates[1], feature.geometry.coordinates[0]], targetZoom, {
                    animate: true,
                    duration: 0.5,
                });
            });
        }
    },

    /**
     * Handle cluster view with abort support
     */
    async handleClusterView({
        globalMapStore,
        clustersStore,
        clusters,
        zoom,
        bbox,
        year,
        points,
        mapInstance,
        abortSignal = null,
    }) {
        // Use the correct store name based on what's provided
        const store = clustersStore || globalMapStore;

        // Remove any remaining glify points
        if (points) {
            removeGlifyPoints(points, mapInstance);
        }

        // Clean up URL when zooming out
        this.cleanupClustersURL();

        try {
            await store.GET_CLUSTERS({
                zoom,
                bbox,
                year,
                signal: abortSignal,
            });

            // Check if request was aborted
            if (abortSignal?.aborted) {
                return null;
            }

            clusters.clearLayers();
            const data = store.clustersGeojson || store.clusters;
            if (data) {
                clusters.addData(data);
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Error loading clusters:', error);
            }
        }

        return null; // No points in cluster view
    },

    /**
     * Load cluster data with abort support
     */
    async loadClusters({ clustersStore, globalMapStore, zoom, bbox = null, year = null, abortSignal = null }) {
        const store = clustersStore || globalMapStore;

        try {
            await store.GET_CLUSTERS({
                zoom,
                bbox,
                year,
                signal: abortSignal,
            });
            return store.clustersGeojson || store.clusters;
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Failed to load clusters:', error);
            }
            throw error;
        }
    },

    /**
     * Add clusters to map layer efficiently
     */
    addClustersToMap(clusters, clustersData) {
        if (clustersData && clustersData.features && clustersData.features.length > 0) {
            // Use requestAnimationFrame for smoother rendering
            requestAnimationFrame(() => {
                clusters.clearLayers();
                clusters.addData(clustersData);
            });
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
     * Remove cluster-related URL parameters using centralized helper
     */
    cleanupClustersURL() {
        const url = new URL(window.location.href);

        // Remove points-specific filters when in cluster view
        ['fromDate', 'toDate', 'username', 'photo', 'photoId', 'page'].forEach((param) => {
            url.searchParams.delete(param);
        });

        urlHelper.stateManager.commitURL(url, true); // Replace state
    },

    /**
     * Check if we should show clusters (below cluster zoom threshold)
     */
    shouldShowClusters(zoom) {
        return zoom < CLUSTER_ZOOM_THRESHOLD;
    },

    /**
     * Get cluster data from store with fallback
     */
    getClustersData(store) {
        // Handle both possible property names
        return store.clustersGeojson || store.clusters || null;
    },

    /**
     * Check if zoom level should trigger cluster reload
     */
    shouldReloadClusters(zoom, prevZoom) {
        // Skip reload if just panning at very low zoom levels
        if (zoom <= 5 && zoom === prevZoom) {
            return false;
        }
        return true;
    },

    /**
     * Handle transition from points to clusters view
     */
    handlePointsToClusterTransition(clusters, points, mapInstance) {
        // Remove any glify points with proper cleanup
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
        if (clusters) {
            clusters.clearLayers();
        }
    },

    /**
     * Get initial cluster parameters from URL
     */
    getClusterFiltersFromURL() {
        return urlHelper.stateManager.getFiltersFromURL();
    },

    /**
     * Calculate optimal cluster radius based on zoom
     */
    getClusterRadius(zoom) {
        // Dynamic cluster radius based on zoom level
        if (zoom <= 5) return 80;
        if (zoom <= 10) return 60;
        if (zoom <= 15) return 40;
        return 20;
    },

    /**
     * Preload adjacent zoom levels for smoother transitions
     */
    async preloadAdjacentZoomLevels({ store, currentZoom, bbox, year }) {
        const preloadZooms = [];

        // Preload one level up and down
        if (currentZoom > 2) preloadZooms.push(currentZoom - 1);
        if (currentZoom < CLUSTER_ZOOM_THRESHOLD - 1) preloadZooms.push(currentZoom + 1);

        // Use requestIdleCallback for low-priority preloading
        for (const zoom of preloadZooms) {
            if (window.requestIdleCallback) {
                window.requestIdleCallback(() => {
                    this.loadClusters({
                        clustersStore: store,
                        zoom,
                        bbox,
                        year,
                    }).catch(() => {
                        // Silently fail for preload requests
                    });
                });
            }
        }
    },
};

export default clustersHelper;
