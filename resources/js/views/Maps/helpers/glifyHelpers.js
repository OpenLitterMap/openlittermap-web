import glify from 'leaflet.glify';
import { popupHelper } from './popup.js';

/**
 * Add glify points to the map
 * @param {Object} pointsGeojson - GeoJSON feature collection
 * @param {L.Map} mapInstance - Leaflet map instance
 * @param {Function} t - Translation function
 * @returns {Object|null} - Glify points layer or null
 */
export function addGlifyPoints(pointsGeojson, mapInstance, t) {
    // Check if we have features
    if (!pointsGeojson.features || pointsGeojson.features.length === 0) {
        return null;
    }

    // Build array for glify - it expects arrays [lng, lat] with longitudeFirst()
    const data = pointsGeojson.features.map((feature) => {
        return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
    });

    // Create glify points
    return glify.points({
        map: mapInstance,
        data: data,
        size: 10,
        color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // 14, 209, 69 / 255
        click: (e, point, xy) => {
            // Find the matching feature
            const feature = pointsGeojson.features.find((f) => {
                return f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1];
            });

            if (!feature) {
                return;
            }

            // Set the photoId in the url when opening a photo
            const url = new URL(window.location.href);
            url.searchParams.set('photo', feature.properties.id);
            window.history.pushState(null, '', url);

            return popupHelper.renderLeafletPopup(feature, e.latlng, t, mapInstance);
        },
    });
}

/**
 * Remove glify points from the map
 * @param {Object} points - Glify points layer
 * @param {L.Map} mapInstance - Leaflet map instance
 */
export function removeGlifyPoints(points, mapInstance) {
    if (!points || !mapInstance) return;

    try {
        if (typeof points.remove === 'function') {
            points.remove();
        }

        // Additional cleanup - remove canvas if it exists
        const canvas = mapInstance.getContainer().querySelector('canvas.glify-canvas');
        if (canvas) {
            canvas.remove();
        }
    } catch (error) {
        console.error('Error removing glify points:', error);
    }
}

/**
 * Initialize glify to use longitude-first order
 */
export function initializeGlify() {
    glify.longitudeFirst();
}
