import glify from 'leaflet.glify';
import { popupHelper } from './popup.js';
import { Category } from './Category.js';
import { urlHelper } from './urlHelper.js';

/**
 * Glify Points Manager
 * Manages WebGL-based point rendering with proper cleanup and memory management
 */
class GlifyPointsManager {
    constructor() {
        this.currentInstance = null;
        this.currentData = null;
        this.currentMap = null;
        this.translationFn = null;
        this.featureIndex = new Map();
        this.isInitialized = false;
    }

    /**
     * Initialize glify with proper settings
     */
    initialize() {
        if (!this.isInitialized) {
            glify.longitudeFirst();
            this.isInitialized = true;
        }
    }

    /**
     * Clean up WebGL resources properly
     */
    cleanupWebGL(canvas) {
        if (!canvas) return;

        try {
            const gl = canvas.getContext('webgl') || canvas.getContext('webgl2');
            if (gl) {
                // Clear all WebGL state
                gl.clearColor(0, 0, 0, 0);
                gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

                // Lose context to force cleanup
                const loseContext = gl.getExtension('WEBGL_lose_context');
                if (loseContext) {
                    loseContext.loseContext();
                }
            }
        } catch (error) {
            console.warn('WebGL cleanup error:', error);
        }
    }

    /**
     * Remove current instance with full cleanup
     */
    removeCurrentInstance() {
        if (!this.currentInstance) return;

        try {
            // Get canvas before removal for cleanup
            const canvas = this.currentInstance.canvas;

            // Remove from map
            if (typeof this.currentInstance.remove === 'function') {
                this.currentInstance.remove();
            }

            // Clean WebGL context
            if (canvas) {
                this.cleanupWebGL(canvas);

                // Remove from DOM
                if (canvas.parentNode) {
                    canvas.parentNode.removeChild(canvas);
                }
            }
        } catch (error) {
            console.error('Error removing glify instance:', error);
        } finally {
            this.currentInstance = null;
        }
    }

    /**
     * Create click handler with proper feature lookup
     */
    createClickHandler(features, map, t) {
        return (e, point, xy) => {
            // Find feature by coordinates
            const feature = features.find(
                (f) => f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1]
            );

            if (!feature) {
                console.warn('Could not find feature for clicked point');
                return;
            }

            // Update URL with photo ID
            urlHelper.stateManager.updatePhotoId(feature.properties.id, false);

            // Render popup
            return popupHelper.renderLeafletPopup(feature, e.latlng, t, map);
        };
    }

    /**
     * Add points with optimized rendering
     */
    addPoints(pointsGeojson, mapInstance, t) {
        // Initialize if needed
        this.initialize();

        // Validate input
        if (!pointsGeojson?.features?.length) {
            return null;
        }

        // Clean up existing instance
        this.removeCurrentInstance();

        // Store references
        this.currentData = pointsGeojson;
        this.currentMap = mapInstance;
        this.translationFn = t;

        // Build data arrays
        const coords = [];
        const features = [];

        pointsGeojson.features.forEach((feature) => {
            coords.push([feature.geometry.coordinates[0], feature.geometry.coordinates[1]]);
            features.push(feature);
        });

        // Create new glify instance
        try {
            this.currentInstance = glify.points({
                map: mapInstance,
                data: coords,
                size: 10,
                color: { r: 0.054, g: 0.819, b: 0.27, a: 1 },
                click: this.createClickHandler(features, mapInstance, t),
                // Performance optimizations
                pane: 'overlayPane',
                opacity: 1,
                className: 'glify-points-layer',
            });

            return this.currentInstance;
        } catch (error) {
            console.error('Error creating glify points:', error);
            this.currentInstance = null;
            return null;
        }
    }

    /**
     * Highlight points by category with proper color mapping
     */
    highlightByCategory(category, objectKey = null) {
        if (!this.currentData || !this.currentMap) return null;

        // Remove current instance
        this.removeCurrentInstance();

        // No filter - show all with default color
        if (!category && !objectKey) {
            return this.addPoints(this.currentData, this.currentMap, this.translationFn);
        }

        // Filter features
        const filteredCoords = [];
        const filteredFeatures = [];

        this.currentData.features.forEach((feature) => {
            let shouldInclude = false;

            if (objectKey) {
                shouldInclude = this.hasObject(feature, category, objectKey);
            } else if (category) {
                shouldInclude = this.hasCategory(feature, category);
            }

            if (shouldInclude) {
                filteredCoords.push([feature.geometry.coordinates[0], feature.geometry.coordinates[1]]);
                filteredFeatures.push(feature);
            }
        });

        // Only render if there are matching points
        if (filteredCoords.length === 0) {
            console.log(`No points found for filter - Category: "${category}", Object: "${objectKey}"`);
            return null;
        }

        // Get category color
        const color = Category.getRGB(category);

        try {
            this.currentInstance = glify.points({
                map: this.currentMap,
                data: filteredCoords,
                size: 12, // Slightly larger for highlighted points
                color: color,
                click: this.createClickHandler(filteredFeatures, this.currentMap, this.translationFn),
                pane: 'overlayPane',
                opacity: 1,
                className: 'glify-points-highlighted',
            });

            console.log(`Rendered ${filteredCoords.length} highlighted points`);
            return this.currentInstance;
        } catch (error) {
            console.error('Error creating highlighted points:', error);
            return null;
        }
    }

    /**
     * Check if feature has specific object
     */
    hasObject(feature, category, objectKey) {
        if (!feature.properties?.summary?.tags) return false;

        const tags = feature.properties.summary.tags;

        if (tags[category] && tags[category][objectKey]) {
            const quantity = tags[category][objectKey].quantity || 0;
            return quantity > 0;
        }

        return false;
    }

    /**
     * Check if feature has category
     */
    hasCategory(feature, category) {
        if (!feature.properties?.summary?.tags) return false;

        const tags = feature.properties.summary.tags;

        if (tags[category]) {
            // Check if category has any objects
            return Object.keys(tags[category]).length > 0;
        }

        return false;
    }

    /**
     * Clear all references
     */
    clearAll() {
        this.removeCurrentInstance();
        this.currentData = null;
        this.currentMap = null;
        this.translationFn = null;
        this.featureIndex.clear();
    }

    /**
     * Get memory usage estimate
     */
    getMemoryEstimate() {
        if (!this.currentData) return 0;

        // Rough estimate: 100 bytes per feature + overhead
        return (this.currentData.features.length * 100) / 1024 / 1024; // MB
    }

    /**
     * Check WebGL availability
     */
    static checkWebGLSupport() {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('webgl2');
            return !!gl;
        } catch (e) {
            return false;
        }
    }
}

// Create singleton instance
const glifyManager = new GlifyPointsManager();

// Export legacy interface for compatibility
export function addGlifyPoints(pointsGeojson, mapInstance, t) {
    return glifyManager.addPoints(pointsGeojson, mapInstance, t);
}

export function highlightPointsByCategory(category, mapInstance, objectKey = null) {
    return glifyManager.highlightByCategory(category, objectKey);
}

export function highlightPointsByObject(objectData, mapInstance) {
    if (!objectData) {
        return glifyManager.highlightByCategory(null, null);
    }
    return glifyManager.highlightByCategory(objectData.category, objectData.objectKey);
}

export function removeGlifyPoints(points, mapInstance) {
    // If called with specific instance, try to remove it
    if (points && points !== glifyManager.currentInstance) {
        try {
            if (typeof points.remove === 'function') {
                points.remove();
            }
        } catch (error) {
            console.error('Error removing glify points:', error);
        }
    }

    // Always clear the manager's instance
    glifyManager.removeCurrentInstance();
}

export function clearGlifyReferences() {
    glifyManager.clearAll();
}

export function initializeGlify() {
    glifyManager.initialize();
}

// Export manager for advanced usage
export { glifyManager };

// Check WebGL support on module load
if (!GlifyPointsManager.checkWebGLSupport()) {
    console.warn('WebGL not supported. Glify points may not render correctly.');
}

export default {
    addGlifyPoints,
    highlightPointsByCategory,
    highlightPointsByObject,
    removeGlifyPoints,
    clearGlifyReferences,
    initializeGlify,
    glifyManager,
};
