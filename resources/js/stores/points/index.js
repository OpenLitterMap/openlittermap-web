import { defineStore } from 'pinia';
import { requests } from './requests.js';

// points/index.js
export const usePointsStore = defineStore('points', {
    state: () => {
        return {
            // Main points data (GeoJSON format)
            pointsGeojson: null,

            // Category-specific data storage
            categoryData: {},

            // Loading and error states
            loading: false,
            error: null,

            // Current map bounds and zoom for caching
            currentBounds: null,
            currentZoom: null,

            // Time range data for statistics
            timeRanges: {},
        };
    },

    getters: {
        // Get points count for current data
        pointsCount: (state) => {
            return state.pointsGeojson?.features?.length || 0;
        },

        // Get category-specific points count
        getCategoryPointsCount: (state) => (category) => {
            return state.categoryData[category]?.features?.length || 0;
        },

        // Check if data exists for current bounds
        hasDataForBounds: (state) => (bounds, zoom) => {
            if (!state.currentBounds || !state.pointsGeojson) return false;

            // Simple bounds comparison (you might want to make this more sophisticated)
            return (
                Math.abs(state.currentBounds.left - bounds.left) < 0.001 &&
                Math.abs(state.currentBounds.right - bounds.right) < 0.001 &&
                Math.abs(state.currentBounds.top - bounds.top) < 0.001 &&
                Math.abs(state.currentBounds.bottom - bounds.bottom) < 0.001 &&
                state.currentZoom === zoom
            );
        },

        // Get time range for a specific category
        getTimeRange:
            (state) =>
            (category = 'all') => {
                return state.timeRanges[category] || null;
            },
    },

    actions: {
        ...requests,

        // Calculate time range from features
        calculateTimeRange(features, category = 'all') {
            if (!features || features.length === 0) return null;

            const dates = features
                .map((f) => f.properties.datetime)
                .filter((d) => d)
                .sort();

            if (dates.length === 0) return null;

            const timeRange = {
                earliest: dates[0],
                latest: dates[dates.length - 1],
                count: features.length,
            };

            this.timeRanges[category] = timeRange;
            return timeRange;
        },

        // Update current bounds for caching
        updateCurrentBounds(bounds, zoom) {
            this.currentBounds = bounds;
            this.currentZoom = zoom;
        },

        // Manual state setters for debugging
        setLoading(loading) {
            console.log('Setting loading to:', loading);
            this.loading = loading;
        },

        setError(error) {
            console.log('Setting error to:', error);
            this.error = error;
        },

        setPointsGeojson(data) {
            console.log('Setting pointsGeojson to:', data?.features?.length || 0, 'features');
            this.pointsGeojson = data;
        },
    },
});
