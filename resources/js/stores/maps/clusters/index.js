import { defineStore } from 'pinia';
import axios from 'axios';

export const useClustersStore = defineStore('clusters', {
    state: () => ({
        // Cluster data in GeoJSON format
        clustersGeojson: {
            type: 'FeatureCollection',
            features: [],
        },

        // Loading and error states
        loading: false,
        error: null,

        // Current view bounds and zoom
        currentBounds: null,
        currentZoom: null,

        // Filters
        year: null,
    }),

    getters: {
        // Get cluster count
        clusterCount: (state) => {
            return state.clustersGeojson?.features?.length || 0;
        },

        // Get total points across all clusters
        totalPoints: (state) => {
            if (!state.clustersGeojson?.features) return 0;

            return state.clustersGeojson.features.reduce((sum, feature) => {
                return sum + (feature.properties?.point_count || 1);
            }, 0);
        },

        // Check if we have clusters for current bounds
        hasClustersForBounds: (state) => (bounds, zoom) => {
            if (!state.currentBounds || !state.clustersGeojson) return false;

            return (
                Math.abs(state.currentBounds.left - bounds.left) < 0.001 &&
                Math.abs(state.currentBounds.right - bounds.right) < 0.001 &&
                Math.abs(state.currentBounds.top - bounds.top) < 0.001 &&
                Math.abs(state.currentBounds.bottom - bounds.bottom) < 0.001 &&
                state.currentZoom === zoom
            );
        },
    },

    actions: {
        /**
         * Get clusters for the global map
         */
        async GET_CLUSTERS({ zoom, year, bbox = null, signal = null }) {
            this.loading = true;
            this.error = null;

            try {
                const params = { zoom };

                // Add optional parameters
                if (year) params.year = year;
                if (bbox) params.bbox = bbox;

                const config = { params };
                if (signal) config.signal = signal;

                const response = await axios.get('/api/clusters', config);

                console.log('GET_CLUSTERS response:', {
                    status: response.status,
                    features: response.data?.features?.length || 0,
                });

                this.clustersGeojson = response.data;

                // Update current bounds and zoom
                if (bbox) {
                    this.currentBounds = bbox;
                }
                this.currentZoom = zoom;

                return response.data;
            } catch (error) {
                console.error('GET_CLUSTERS error:', error);
                this.error = error.message;
                throw error;
            } finally {
                this.loading = false;
            }
        },

        /**
         * Clear clusters data
         */
        CLEAR_CLUSTERS() {
            this.clustersGeojson = {
                type: 'FeatureCollection',
                features: [],
            };
            this.currentBounds = null;
            this.currentZoom = null;
            this.error = null;
        },

        /**
         * Set year filter
         */
        SET_YEAR_FILTER(year) {
            this.year = year;
        },

        /**
         * Update bounds without fetching new data
         */
        UPDATE_BOUNDS(bounds, zoom) {
            this.currentBounds = bounds;
            this.currentZoom = zoom;
        },
    },
});
