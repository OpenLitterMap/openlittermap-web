import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useGlobalMapStore = defineStore('globalMap', {
    state: () => {
        return {
            artData: [],
            clustersGeojson: {
                type: 'FeatureCollection',
                features: [],
            },
            pointsGeojson: {
                type: 'FeatureCollection',
                features: [],
            },
            currentDate: 'today',
            loading: true,
            datesOpen: false,
            langsOpen: false,
            customTagsFound: [],
            // New state for filters
            activeFilters: {
                categories: [],
                litter_objects: [],
                materials: [],
                brands: [],
                custom_tags: [],
            },
        };
    },

    actions: {
        ...requests,

        // Helper to set filters
        setFilters(filters) {
            this.activeFilters = { ...this.activeFilters, ...filters };
        },

        // Helper to clear filters
        clearFilters() {
            this.activeFilters = {
                categories: [],
                litter_objects: [],
                materials: [],
                brands: [],
                custom_tags: [],
            };
        },
    },
});
