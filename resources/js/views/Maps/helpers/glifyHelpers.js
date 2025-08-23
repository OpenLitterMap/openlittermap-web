import glify from 'leaflet.glify';
import { popupHelper } from './popup.js';
import { Category } from './Category.js';

// Store reference to current glify points instance
let currentGlifyInstance = null;
let currentPointsData = null;
let currentMapInstance = null;
let currentTranslationFn = null;

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

    // Store the points data and map instance for later use
    currentPointsData = pointsGeojson;
    currentMapInstance = mapInstance;
    currentTranslationFn = t;

    // Build array for glify with index mapping
    const data = [];
    const featuresByIndex = [];

    pointsGeojson.features.forEach((feature, idx) => {
        data.push([feature.geometry.coordinates[0], feature.geometry.coordinates[1]]);
        featuresByIndex[idx] = feature; // Store by index for reliable lookup
    });

    // Create glify points
    currentGlifyInstance = glify.points({
        map: mapInstance,
        data: data,
        size: 10,
        color: { r: 0.054, g: 0.819, b: 0.27, a: 1 },
        click: (e, point, xy) => {
            // glify provides the index in the click event
            const idx = data.findIndex((d) => d[0] === point[0] && d[1] === point[1]);
            const feature = featuresByIndex[idx];

            if (!feature) {
                console.warn('Could not find feature for clicked point');
                return;
            }

            // Set the photoId in the url when opening a photo
            const url = new URL(window.location.href);
            url.searchParams.set('photo', feature.properties.id);
            window.history.replaceState(null, '', url); // Use replaceState for cleaner history

            return popupHelper.renderLeafletPopup(feature, e.latlng, t, mapInstance);
        },
    });

    return currentGlifyInstance;
}

/**
 * Highlight points by category or object
 * @param {string|null} category - Category key to highlight, or null to reset
 * @param {L.Map} mapInstance - Leaflet map instance
 * @param {string|null} objectKey - Optional specific object key to highlight
 */
export function highlightPointsByCategory(category, mapInstance, objectKey = null) {
    if (!currentPointsData) return;

    // Use the stored map instance if the passed one is null
    const map = mapInstance || currentMapInstance;
    if (!map) return;

    // Remove current glify points if they exist
    if (currentGlifyInstance) {
        removeGlifyPoints(currentGlifyInstance, map);
        currentGlifyInstance = null;
    }

    if (!category && !objectKey) {
        // No category or object highlighted - show all points with default green
        const data = currentPointsData.features.map((feature) => {
            return [feature.geometry.coordinates[0], feature.geometry.coordinates[1]];
        });

        currentGlifyInstance = glify.points({
            map: map,
            data: data,
            size: 10,
            color: { r: 0.054, g: 0.819, b: 0.27, a: 1 }, // Default green
            click: (e, point, xy) => {
                // Find the matching feature
                const feature = currentPointsData.features.find((f) => {
                    return f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1];
                });

                if (!feature) {
                    return;
                }

                // Set the photoId in the url when opening a photo
                const url = new URL(window.location.href);
                url.searchParams.set('photo', feature.properties.id);
                window.history.pushState(null, '', url);

                return popupHelper.renderLeafletPopup(feature, e.latlng, currentTranslationFn, map);
            },
        });
    } else {
        // Filter data based on category and/or object
        const filteredData = [];
        const filteredFeatures = [];

        currentPointsData.features.forEach((feature) => {
            let shouldInclude = false;

            if (objectKey) {
                // Check for specific object within the category
                shouldInclude = checkPointHasObject(feature, category, objectKey);
            } else if (category) {
                // Check for category only
                shouldInclude = checkPointCategory(feature, category);
            }

            if (shouldInclude) {
                filteredData.push([feature.geometry.coordinates[0], feature.geometry.coordinates[1]]);
                filteredFeatures.push(feature);
            }
        });

        console.log(`Filter - Category: "${category}", Object: "${objectKey}" - ${filteredData.length} points found`);

        // Only create glify points if there are matching features
        if (filteredData.length > 0) {
            // Get the category color using the Category class
            const categoryColor = Category.getRGB(category);

            currentGlifyInstance = glify.points({
                map: map,
                data: filteredData,
                size: 12, // Slightly larger for highlighted points
                color: categoryColor, // Use category-specific color
                click: (e, point, xy) => {
                    // Find the matching feature from filtered features
                    const feature = filteredFeatures.find((f) => {
                        return f.geometry.coordinates[0] === point[0] && f.geometry.coordinates[1] === point[1];
                    });

                    if (!feature) {
                        return;
                    }

                    // Set the photoId in the url when opening a photo
                    const url = new URL(window.location.href);
                    url.searchParams.set('photo', feature.properties.id);
                    window.history.pushState(null, '', url);

                    return popupHelper.renderLeafletPopup(feature, e.latlng, currentTranslationFn, map);
                },
            });
        }
    }

    return currentGlifyInstance;
}

/**
 * Check if a point has a specific object within a category
 * @param {Object} feature - GeoJSON feature
 * @param {string} category - Category key
 * @param {string} objectKey - Object key
 * @returns {boolean} - Whether the point has the specific object
 */
function checkPointHasObject(feature, category, objectKey) {
    if (!feature.properties || !category || !objectKey) return false;

    const props = feature.properties;

    console.log(`Checking point ${props.id} for category: "${category}", object: "${objectKey}"`);

    // Check if summary.tags exists and has the category
    if (props.summary && props.summary.tags) {
        console.log('Available categories in this point:', Object.keys(props.summary.tags));

        if (props.summary.tags[category]) {
            const categoryData = props.summary.tags[category];
            console.log(`Objects in ${category}:`, Object.keys(categoryData));

            // Check if the specific object exists in this category
            if (categoryData[objectKey]) {
                // Check if it has a quantity > 0
                const quantity = categoryData[objectKey].quantity || 0;
                const hasObject = quantity > 0;
                if (hasObject) {
                    console.log(`✓ Point ${props.id} has ${category}.${objectKey}: quantity ${quantity}`);
                }
                return hasObject;
            } else {
                console.log(`✗ Object "${objectKey}" not found in category "${category}"`);
            }
        } else {
            console.log(`✗ Category "${category}" not found in this point`);
        }
    }

    return false;
}

/**
 * Check if a point belongs to a specific category
 * @param {Object} feature - GeoJSON feature
 * @param {string} category - Category key
 * @returns {boolean} - Whether the point belongs to the category
 */
function checkPointCategory(feature, category) {
    if (!feature.properties || !category) return false;

    const props = feature.properties;

    // Check if summary.tags exists and has the category
    if (props.summary && props.summary.tags && props.summary.tags[category]) {
        // Category exists in tags - check if it has any objects
        const categoryData = props.summary.tags[category];
        const hasData = Object.keys(categoryData).length > 0;

        if (hasData) {
            console.log(`Point ${props.id} matches category ${category}`);
        }

        return hasData;
    }

    return false;
}

/**
 * Highlight points by specific object
 * @param {Object|null} objectData - Object containing category and objectKey, or null to reset
 * @param {L.Map} mapInstance - Leaflet map instance
 */
export function highlightPointsByObject(objectData, mapInstance) {
    if (!objectData) {
        // Reset to show all points
        return highlightPointsByCategory(null, mapInstance, null);
    }

    // Use the highlight function with both category and object
    return highlightPointsByCategory(objectData.category, mapInstance, objectData.objectKey);
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
 * Clear stored references
 */
export function clearGlifyReferences() {
    currentGlifyInstance = null;
    currentPointsData = null;
    currentMapInstance = null;
    currentTranslationFn = null;
}

/**
 * Initialize glify to use longitude-first order
 */
export function initializeGlify() {
    glify.longitudeFirst();
}
