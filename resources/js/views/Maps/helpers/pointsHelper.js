import { CLUSTER_ZOOM_THRESHOLD } from './constants.js';
import { addGlifyPoints, removeGlifyPoints, clearGlifyReferences } from './glifyHelpers.js';
import { popupHelper } from './popup.js';
import { urlHelper } from './urlHelper.js';

/**
 * Request deduplication and management
 */
class RequestManager {
    constructor() {
        this.activeRequests = new Map();
        this.requestHashes = new Map();
    }

    /**
     * Generate a hash for request deduplication
     */
    generateRequestHash(params) {
        const { zoom, bbox, year, fromDate, toDate, username, tagFilter, page } = params;
        return JSON.stringify({
            z: Math.round(zoom),
            b: [
                Math.round(bbox.left * 1000),
                Math.round(bbox.bottom * 1000),
                Math.round(bbox.right * 1000),
                Math.round(bbox.top * 1000),
            ],
            y: year,
            f: fromDate,
            t: toDate,
            u: username,
            tf: tagFilter ? `${tagFilter.type}:${tagFilter.id}` : null,
            p: page,
        });
    }

    /**
     * Check if we should skip this request (duplicate in flight)
     */
    shouldSkipRequest(hash) {
        return this.activeRequests.has(hash);
    }

    /**
     * Register a new request
     */
    registerRequest(hash, controller) {
        this.activeRequests.set(hash, controller);
    }

    /**
     * Clear a completed request
     */
    clearRequest(hash) {
        this.activeRequests.delete(hash);
    }

    /**
     * Abort all active requests
     */
    abortAll() {
        this.activeRequests.forEach((controller) => {
            if (controller && typeof controller.abort === 'function') {
                controller.abort();
            }
        });
        this.activeRequests.clear();
    }
}

const requestManager = new RequestManager();

export const pointsHelper = {
    /**
     * Handle points view with proper abort signal support
     */
    async handlePointsView({
        mapInstance,
        pointsStore,
        clusters,
        prevZoom,
        zoom,
        bbox,
        year,
        fromDate,
        toDate,
        username,
        tagFilter = null,
        t,
        page = 1,
        abortSignal = null,
    }) {
        // Clear cluster layer if transitioning from cluster mode
        if (prevZoom < CLUSTER_ZOOM_THRESHOLD) {
            clusters.clearLayers();
        }

        // Generate request hash for deduplication
        const requestHash = requestManager.generateRequestHash({
            zoom,
            bbox,
            year,
            fromDate,
            toDate,
            username,
            tagFilter,
            page,
        });

        // Check if this exact request is already in flight
        if (requestManager.shouldSkipRequest(requestHash)) {
            console.log('Skipping duplicate request');
            return null;
        }

        // Create abort controller if not provided
        const controller = abortSignal ? { signal: abortSignal } : new AbortController();
        const signal = controller.signal || controller;

        // Register this request
        requestManager.registerRequest(requestHash, controller);

        try {
            const layers = [];

            // Add abort signal support to store request
            const response = await pointsStore.GET_POINTS({
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username,
                tagFilter,
                page,
                signal, // Pass abort signal to store
            });

            // Check if request was aborted
            if (signal.aborted) {
                return null;
            }

            // Add the new points
            const points = addGlifyPoints(pointsStore.pointsGeojson, mapInstance, t);

            // Check for photo in URL using centralized helper
            const photoId = urlHelper.getPhotoIdFromURL();

            if (photoId && !signal.aborted) {
                const feature = pointsStore.pointsGeojson?.features?.find(
                    (f) => f.properties?.id === photoId
                );

                if (feature) {
                    requestAnimationFrame(() => {
                        if (!signal.aborted) {
                            popupHelper.renderLeafletPopup(
                                feature,
                                [feature.geometry.coordinates[1], feature.geometry.coordinates[0]],
                                t,
                                mapInstance
                            );
                        }
                    });
                } else {
                    // Fallback: fetch the individual photo if not in current page
                    pointsHelper.fetchAndShowPhoto(photoId, t, mapInstance, signal);
                }
            }

            return points;
        } catch (error) {
            if (error.name === 'AbortError') {
                console.log('Points request aborted');
                return null;
            }
            console.error('Error loading points:', error);
            throw error;
        } finally {
            // Clear request from tracking
            requestManager.clearRequest(requestHash);
        }
    },

    /**
     * Load points data for pagination with abort support
     */
    async loadPointsData({
        mapInstance,
        pointsStore,
        zoom,
        bbox,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        tagFilter = null,
        page = 1,
        abortSignal = null,
    }) {
        const requestHash = requestManager.generateRequestHash({
            zoom,
            bbox,
            year,
            fromDate,
            toDate,
            username,
            tagFilter,
            page,
        });

        if (requestManager.shouldSkipRequest(requestHash)) {
            console.log('Skipping duplicate pagination request');
            return null;
        }

        const controller = abortSignal ? { signal: abortSignal } : new AbortController();
        const signal = controller.signal || controller;

        requestManager.registerRequest(requestHash, controller);

        try {
            const layers = [];

            await pointsStore.GET_POINTS({
                zoom,
                bbox,
                layers,
                year,
                fromDate,
                toDate,
                username,
                tagFilter,
                page,
                signal,
            });

            if (signal.aborted) {
                return null;
            }

            // Add the new points with translation fallback
            const points = addGlifyPoints(pointsStore.pointsGeojson, mapInstance, null);

            // Check if we should open a popup after loading points
            const photoId = urlHelper.getPhotoIdFromURL();

            if (photoId && !signal.aborted) {
                const feature = pointsStore.pointsGeojson?.features?.find(
                    (f) => f.properties?.id === photoId
                );

                if (feature) {
                    requestAnimationFrame(() => {
                        if (!signal.aborted) {
                            popupHelper.renderLeafletPopup(
                                feature,
                                [feature.geometry.coordinates[1], feature.geometry.coordinates[0]],
                                null,
                                mapInstance
                            );
                        }
                    });
                } else {
                    pointsHelper.fetchAndShowPhoto(photoId, null, mapInstance, signal);
                }
            }

            return points;
        } catch (error) {
            if (error.name === 'AbortError') {
                return null;
            }
            console.error('Load points data error:', error);
            throw error;
        } finally {
            requestManager.clearRequest(requestHash);
        }
    },

    /**
     * Clear points from map with proper cleanup
     */
    clearPoints(points, mapInstance) {
        if (points) {
            // Ensure proper WebGL cleanup
            removeGlifyPoints(points, mapInstance);
            clearGlifyReferences();
        }
        return null;
    },

    /**
     * Load statistics for points in current view with abort support
     */
    async loadPointsStats({
        mapInstance,
        zoom,
        year = null,
        fromDate = null,
        toDate = null,
        username = null,
        tagFilter = null,
        abortSignal = null,
    }) {
        const bounds = mapInstance.getBounds();
        const bbox = {
            left: bounds.getWest(),
            bottom: bounds.getSouth(),
            right: bounds.getEast(),
            top: bounds.getNorth(),
        };

        // Generate hash for stats request
        const requestHash = requestManager.generateRequestHash({
            zoom,
            bbox,
            year,
            fromDate,
            toDate,
            username,
            tagFilter,
            page: 0, // Stats don't have pages
        });

        if (requestManager.shouldSkipRequest(requestHash)) {
            console.log('Skipping duplicate stats request');
            return null;
        }

        const controller = abortSignal ? { signal: abortSignal } : new AbortController();
        const signal = controller.signal || controller;

        requestManager.registerRequest(requestHash, controller);

        // Build stats request parameters
        const params = new URLSearchParams({
            zoom: zoom.toString(),
            'bbox[left]': bbox.left.toString(),
            'bbox[bottom]': bbox.bottom.toString(),
            'bbox[right]': bbox.right.toString(),
            'bbox[top]': bbox.top.toString(),
        });

        // Add optional filters
        if (year) params.append('year', year.toString());
        if (fromDate) params.append('from', fromDate);
        if (toDate) params.append('to', toDate);
        if (username) params.append('username', username);

        // Apply tag filter
        if (tagFilter) {
            const filterMap = {
                category: 'categories[0]',
                object: 'litter_objects[0]',
                brand: 'brands[0]',
                material: 'materials[0]',
                contributor: 'username',
            };
            const paramKey = filterMap[tagFilter.type];
            if (paramKey) {
                params.append(paramKey, tagFilter.id);
            }
        }

        try {
            const response = await fetch(`/api/points/stats?${params.toString()}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                signal,
            });

            if (signal.aborted) {
                return null;
            }

            if (response.ok) {
                const data = await response.json();
                return data.data || null;
            } else {
                console.error('Failed to load points stats:', response.status);
                return null;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return null;
            }
            console.error('Error loading points stats:', error);
            return null;
        } finally {
            requestManager.clearRequest(requestHash);
        }
    },

    /**
     * Fallback: fetch a single photo by ID and render its popup.
     * Used when the photo isn't in the current page of GeoJSON results.
     */
    async fetchAndShowPhoto(photoId, t, mapInstance, signal) {
        try {
            const response = await fetch(`/api/points/${photoId}`, {
                headers: { Accept: 'application/json' },
                signal,
            });

            if (!response.ok || signal?.aborted) return;

            const photo = await response.json();
            if (!photo.lat || !photo.lon) return;

            const feature = {
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [photo.lon, photo.lat] },
                properties: photo,
            };

            requestAnimationFrame(() => {
                if (!signal?.aborted) {
                    popupHelper.renderLeafletPopup(
                        feature,
                        [photo.lat, photo.lon],
                        t,
                        mapInstance
                    );
                }
            });
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.warn('Could not fetch photo for popup:', error);
            }
        }
    },

    /**
     * Check if we should show points (above cluster zoom threshold)
     */
    shouldShowPoints(zoom) {
        return zoom >= CLUSTER_ZOOM_THRESHOLD;
    },

    /**
     * Get points data from store
     */
    getPointsData(pointsStore) {
        return pointsStore.pointsGeojson;
    },

    /**
     * Get pagination data from store with multiple fallback locations
     */
    getPaginationData(pointsStore) {
        // Primary location
        if (pointsStore.pointsPagination) {
            return pointsStore.pointsPagination;
        }

        // Secondary location
        if (pointsStore.pagination) {
            return pointsStore.pagination;
        }

        // Check in geojson meta (API returns 'page' not 'current_page')
        if (pointsStore.pointsGeojson?.meta?.page !== undefined) {
            return {
                current_page: pointsStore.pointsGeojson.meta.page || 1,
                last_page: pointsStore.pointsGeojson.meta.last_page || 1,
                per_page: pointsStore.pointsGeojson.meta.per_page || 300,
                total: pointsStore.pointsGeojson.meta.total || 0,
            };
        }

        // Check if pagination is at root of pointsGeojson (API returns 'page' not 'current_page')
        if (pointsStore.pointsGeojson?.page !== undefined) {
            return {
                current_page: pointsStore.pointsGeojson.page || 1,
                last_page: pointsStore.pointsGeojson.last_page || 1,
                per_page: pointsStore.pointsGeojson.per_page || 300,
                total: pointsStore.pointsGeojson.total || 0,
            };
        }

        return null;
    },

    /**
     * Get filters from URL using centralized helper
     */
    getFiltersFromURL() {
        return urlHelper.stateManager.getFiltersFromURL();
    },

    /**
     * Check if pagination should be reset based on movement/filter changes
     */
    shouldResetPagination(prevState, currentState) {
        if (!prevState || !currentState) return false;

        // Reset if filters changed
        if (JSON.stringify(prevState.filters) !== JSON.stringify(currentState.filters)) {
            return true;
        }

        // Reset if moved more than 50% of viewport
        if (prevState.bbox && currentState.bbox) {
            const viewportWidth = Math.abs(prevState.bbox.right - prevState.bbox.left);
            const viewportHeight = Math.abs(prevState.bbox.top - prevState.bbox.bottom);

            const deltaX = Math.abs(currentState.bbox.left - prevState.bbox.left);
            const deltaY = Math.abs(currentState.bbox.bottom - prevState.bbox.bottom);

            if (deltaX > viewportWidth * 0.5 || deltaY > viewportHeight * 0.5) {
                return true;
            }
        }

        // Reset if zoom changed significantly (more than 1 level)
        if (Math.abs((prevState.zoom || 0) - (currentState.zoom || 0)) > 1) {
            return true;
        }

        return false;
    },

    /**
     * Get popup options for points
     */
    getPopupOptions() {
        return popupHelper.popupOptions;
    },

    /**
     * Get popup content for a point feature
     */
    getPopupContent(properties, url, t) {
        return popupHelper.getContent(properties, url, t);
    },

    /**
     * Cleanup all active requests
     */
    cleanup() {
        requestManager.abortAll();
    },
};

export default pointsHelper;
