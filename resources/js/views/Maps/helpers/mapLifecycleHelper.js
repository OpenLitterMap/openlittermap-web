import L from 'leaflet';
import { MIN_ZOOM, MAX_ZOOM } from './constants.js';
import { clustersHelper } from './clustersHelper.js';
import { pointsHelper } from './pointsHelper.js';
import { urlHelper } from './urlHelper.js';
import { mapEventHelper } from './mapEventHelper.js';

/**
 * WebGL Context Manager for handling context loss/restoration
 */
class WebGLContextManager {
    constructor() {
        this.contexts = new WeakMap();
        this.restorationCallbacks = new Map();
    }

    /**
     * Register a canvas for WebGL monitoring
     */
    registerCanvas(canvas, restorationCallback) {
        if (!canvas) return;

        const gl = canvas.getContext('webgl') || canvas.getContext('webgl2');
        if (!gl) return;

        this.contexts.set(canvas, gl);

        canvas.addEventListener('webglcontextlost', (e) => {
            e.preventDefault();
            console.warn('WebGL context lost');

            if (restorationCallback) {
                this.restorationCallbacks.set(canvas, restorationCallback);
            }
        });

        canvas.addEventListener('webglcontextrestored', () => {
            console.log('WebGL context restored');

            const callback = this.restorationCallbacks.get(canvas);
            if (callback) {
                callback();
                this.restorationCallbacks.delete(canvas);
            }
        });
    }

    /**
     * Force cleanup of WebGL context
     */
    cleanupCanvas(canvas) {
        if (!canvas) return;

        const gl = this.contexts.get(canvas);
        if (gl) {
            const loseContext = gl.getExtension('WEBGL_lose_context');
            if (loseContext) {
                loseContext.loseContext();
            }
            this.contexts.delete(canvas);
        }

        this.restorationCallbacks.delete(canvas);
    }

    /**
     * Cleanup all registered canvases
     */
    cleanupAll() {
        this.contexts = new WeakMap();
        this.restorationCallbacks.clear();
    }
}

const webGLManager = new WebGLContextManager();

export const mapLifecycleHelper = {
    /**
     * Initialize the map and all its components
     */
    async initializeMap({ clustersStore, $loading, t }) {
        // Normalize any legacy photo parameters first
        urlHelper.normalizePhotoParam();

        const clusterFilters = clustersHelper.getClusterFiltersFromURL();
        const initialPage = urlHelper.stateManager.getFiltersFromURL().page;
        const locationParams = urlHelper.stateManager.getLocationFromURL();

        // Load initial cluster data
        await clustersHelper.loadClusters({
            clustersStore,
            zoom: 2,
            year: clusterFilters.year,
        });

        // Create map instance with optimized settings
        const mapInstance = L.map('openlittermap', {
            center: [0, 0],
            zoom: MIN_ZOOM,
            scrollWheelZoom: false,
            smoothWheelZoom: true,
            smoothSensitivity: 2,
            preferCanvas: false, // Use SVG for better performance with many points
            renderer: L.svg(),
            zoomAnimation: true,
            fadeAnimation: true,
            markerZoomAnimation: true,
        });

        // Set initial view based on URL parameters (position is always restored, load gates data fetching)
        let currentZoom = MIN_ZOOM;
        if (locationParams.lat || locationParams.lon) {
            const lat = Math.max(-85, Math.min(85, locationParams.lat));
            const lon = Math.max(-180, Math.min(180, locationParams.lon));
            const zoom = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, locationParams.zoom));

            mapInstance.setView([lat, lon], zoom, { animate: false });
            currentZoom = zoom;
        }

        // Add tile layer with proper attribution
        const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="https://openstreetmap.org">OpenStreetMap</a> & Contributors',
            maxZoom: MAX_ZOOM,
            minZoom: MIN_ZOOM,
            updateWhenIdle: false,
            updateWhenZooming: false,
            keepBuffer: 2,
            tileSize: 256,
        });

        tileLayer.addTo(mapInstance);

        // Initialize clusters layer with optimized rendering
        const clusters = L.geoJSON(null, {
            pointToLayer: clustersHelper.createClusterIcon,
            onEachFeature: (feature, layer) => clustersHelper.onEachFeature(feature, layer, mapInstance),
        });

        // Always add clusters layer to the map so subsequent loads render
        const clustersData = clustersHelper.getClustersData(clustersStore);
        if (clustersData?.features?.length > 0) {
            clustersHelper.addClustersToMap(clusters, clustersData);
        }
        mapInstance.addLayer(clusters);

        // Register for WebGL monitoring if Glify will be used
        mapInstance.on('layeradd', (e) => {
            if (e.layer?.canvas) {
                webGLManager.registerCanvas(e.layer.canvas, () => {
                    // Reload points if WebGL context is restored
                    console.log('Reloading points after context restoration');
                    mapInstance.fire('moveend');
                });
            }
        });

        return {
            mapInstance,
            clusters,
            currentZoom,
            currentPage: initialPage,
        };
    },

    /**
     * Cleanup map and all related resources with proper resource management
     */
    cleanup({ mapInstance, clusters, points, router, route, preserveUrlParams = false }) {
        // Cancel all pending operations first
        mapEventHelper.forceCancel();
        pointsHelper.cleanup();

        // Clean up WebGL contexts
        webGLManager.cleanupAll();

        if (mapInstance) {
            // Remove all event listeners
            mapInstance.off('moveend');
            mapInstance.off('popupclose');
            mapInstance.off('zoom');
            mapInstance.off('error');
            mapInstance.off('layeradd');

            // Clean up Glify points with proper WebGL cleanup
            if (points) {
                try {
                    pointsHelper.clearPoints(points, mapInstance);
                } catch (error) {
                    console.warn('Error clearing points during cleanup:', error);
                }
            }

            // Clear all layers
            mapInstance.eachLayer((layer) => {
                if (layer !== mapInstance._container) {
                    try {
                        mapInstance.removeLayer(layer);
                    } catch (error) {
                        console.warn('Error removing layer during cleanup:', error);
                    }
                }
            });

            // Remove clusters
            if (clusters) {
                try {
                    clustersHelper.clearClusters(clusters);
                } catch (error) {
                    console.warn('Error clearing clusters during cleanup:', error);
                }
            }

            // Close any open popups
            mapInstance.closePopup();

            // Remove the map instance
            try {
                mapInstance.remove();
            } catch (error) {
                console.warn('Error removing map instance:', error);
            }
        }

        // Handle URL cleanup
        if (router && route) {
            if (preserveUrlParams) {
                // Keep filter parameters, only clear map-specific ones
                urlHelper.stateManager.clearParamGroup('map');
                urlHelper.stateManager.clearParamGroup('view');
            } else {
                // Full URL reset
                router.replace({ path: route.path });
            }
        }
    },

    /**
     * Save map state for potential restoration
     */
    saveMapState(mapInstance) {
        if (!mapInstance) return null;

        const center = mapInstance.getCenter();
        const zoom = mapInstance.getZoom();
        const bounds = mapInstance.getBounds();

        return {
            center: { lat: center.lat, lng: center.lng },
            zoom: zoom,
            bounds: {
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
            },
            filters: urlHelper.stateManager.getFiltersFromURL(),
            timestamp: Date.now(),
        };
    },

    /**
     * Restore map state from saved data
     */
    restoreMapState(mapInstance, savedState) {
        if (!mapInstance || !savedState) return;

        // Check if saved state is recent (within 5 minutes)
        const isRecent = Date.now() - savedState.timestamp < 5 * 60 * 1000;

        if (isRecent) {
            mapInstance.setView([savedState.center.lat, savedState.center.lng], savedState.zoom, { animate: false });
        }
    },

    /**
     * Check map health and attempt recovery if needed
     */
    async checkMapHealth(mapInstance) {
        if (!mapInstance) return false;

        try {
            // Check if map is still valid
            const center = mapInstance.getCenter();
            const zoom = mapInstance.getZoom();

            if (isNaN(center.lat) || isNaN(center.lng) || isNaN(zoom)) {
                console.error('Map state corrupted, attempting recovery');

                // Reset to default view
                mapInstance.setView([0, 0], MIN_ZOOM, { animate: false });
                return false;
            }

            // Check for WebGL context loss
            const glifyLayers = [];
            mapInstance.eachLayer((layer) => {
                if (layer.canvas) {
                    const gl = layer.canvas.getContext('webgl') || layer.canvas.getContext('webgl2');
                    if (gl && gl.isContextLost()) {
                        glifyLayers.push(layer);
                    }
                }
            });

            if (glifyLayers.length > 0) {
                console.warn(`Found ${glifyLayers.length} layers with lost WebGL context`);

                // Remove and re-add affected layers
                glifyLayers.forEach((layer) => {
                    mapInstance.removeLayer(layer);
                });

                // Trigger reload
                mapInstance.fire('moveend');
                return false;
            }

            return true;
        } catch (error) {
            console.error('Map health check failed:', error);
            return false;
        }
    },

    /**
     * Get performance metrics for monitoring
     */
    getPerformanceMetrics(mapInstance) {
        if (!mapInstance) return null;

        const metrics = {
            layerCount: 0,
            visibleFeatures: 0,
            memoryUsage: null,
            webglContexts: 0,
        };

        // Count layers and features
        mapInstance.eachLayer((layer) => {
            metrics.layerCount++;

            if (layer.getLayers) {
                try {
                    metrics.visibleFeatures += layer.getLayers().length;
                } catch (e) {
                    // Some layers don't support getLayers
                }
            }

            if (layer.canvas) {
                metrics.webglContexts++;
            }
        });

        // Get memory usage if available
        if (performance.memory) {
            metrics.memoryUsage = {
                used: Math.round(performance.memory.usedJSHeapSize / 1048576), // MB
                total: Math.round(performance.memory.totalJSHeapSize / 1048576), // MB
            };
        }

        return metrics;
    },

    /**
     * Optimize map for mobile devices
     */
    optimizeForMobile(mapInstance) {
        if (!mapInstance || !window.matchMedia('(max-width: 768px)').matches) {
            return;
        }

        // Reduce tile buffer for mobile
        mapInstance.eachLayer((layer) => {
            if (layer.options && layer.options.keepBuffer !== undefined) {
                layer.options.keepBuffer = 1;
            }
        });

        // Disable animations on mobile for better performance
        mapInstance.options.zoomAnimation = false;
        mapInstance.options.fadeAnimation = false;
        mapInstance.options.markerZoomAnimation = false;

        console.log('Map optimized for mobile device');
    },
};

export default mapLifecycleHelper;
