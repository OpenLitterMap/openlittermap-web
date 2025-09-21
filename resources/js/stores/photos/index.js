import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const usePhotosStore = defineStore('photos', {
    state: () => ({
        // Photos array
        photos: [],

        // Pagination data
        pagination: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: 0,
        },

        // User data
        user: null,

        // Current filters
        currentFilters: {
            tagged: null,
            id: null,
            idOperator: '=',
            tag: null,
            customTag: null,
            dateFrom: null,
            dateTo: null,
            perPage: 25,
        },

        // Stats (separate from photos for caching)
        untaggedStats: {
            totalPhotos: 0,
            totalTags: 0,
            leftToTag: 0,
            taggedPercentage: 0,
        },

        // Previous custom tags
        previousCustomTags: [],

        // Loading states
        loading: {
            photos: false,
            stats: false,
        },

        // Error handling
        error: null,
    }),

    getters: {
        currentPage: (state) => state.pagination.current_page,
        lastPage: (state) => state.pagination.last_page,
        perPage: (state) => state.pagination.per_page,
        total: (state) => state.pagination.total,
    },

    actions: {
        ...requests,

        /**
         * Fetch both photos and stats (for initial load)
         */
        async fetchUntaggedData(page = 1, filters = {}) {
            this.currentFilters = { ...this.currentFilters, ...filters };
            // Fetch photos and stats in parallel
            await Promise.all([this.GET_USERS_PHOTOS(page, this.currentFilters), this.GET_UNTAGGED_STATS()]);
        },

        /**
         * Just fetch photos (for pagination)
         */
        async fetchPhotosOnly(page = 1, filters = {}) {
            this.loading.photos = true;
            this.currentFilters = { ...this.currentFilters, ...filters };
            try {
                await this.GET_USERS_PHOTOS(page, this.currentFilters);
            } finally {
                this.loading.photos = false;
            }
        },

        /**
         * Just fetch stats (for refresh after tagging)
         */
        async fetchStatsOnly() {
            this.loading.stats = true;
            try {
                await this.GET_UNTAGGED_STATS();
            } finally {
                this.loading.stats = false;
            }
        },

        /**
         * Clear all data
         */
        clearData() {
            this.photos = [];
            this.pagination = {
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 0,
            };
            this.user = null;
            this.currentFilters = {
                tagged: null,
                id: null,
                idOperator: '=',
                tag: null,
                customTag: null,
                dateFrom: null,
                dateTo: null,
                perPage: 25,
            };
            this.untaggedStats = {
                totalPhotos: 0,
                totalTags: 0,
                leftToTag: 0,
                taggedPercentage: 0,
            };
            this.previousCustomTags = [];
            this.error = null;
        },
    },
});
