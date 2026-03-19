import { defineStore } from 'pinia';
import axios from 'axios';
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
            // Pagination state
            pointsPagination: {
                current_page: 1,
                last_page: 1,
                per_page: 300,
                total: 0,
                has_more: false,
            },
            isLoadingMorePoints: false,
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

        // Reset pagination when filters change
        resetPagination() {
            this.pointsPagination = {
                current_page: 1,
                last_page: 1,
                per_page: 300,
                total: 0,
                has_more: false,
            };
            this.pointsGeojson = {
                type: 'FeatureCollection',
                features: [],
            };
        },

        // Load more points (for pagination)
        async loadMorePoints(params) {
            if (this.isLoadingMorePoints || !this.pointsPagination.has_more) return;

            this.isLoadingMorePoints = true;
            const nextPage = this.pointsPagination.current_page + 1;

            try {
                await this.GET_POINTS({
                    ...params,
                    page: nextPage,
                    append: true,
                });
            } finally {
                this.isLoadingMorePoints = false;
            }
        },
    },
});
