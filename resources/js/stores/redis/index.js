import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useRedisStore = defineStore('redis', {
    state: () => ({
        // Overview data
        users: [],
        global: {
            categories: { total: 0, unique: 0, top10: {} },
            objects: { total: 0, unique: 0, top10: {} },
            materials: { total: 0, unique: 0, top10: {} },
            brands: { total: 0, unique: 0, top10: {} },
        },
        timeSeries: {}, // { "2024-01": { photos: 0, xp: 0 } }
        geo: {
            countries: { locations: 0, totalPhotos: 0 },
            states: { locations: 0, totalPhotos: 0 },
            cities: { locations: 0, totalPhotos: 0 },
        },
        stats: {
            usedMemory: 'N/A',
            totalKeys: 0,
            connectedClients: 0,
            uptime: 0,
        },

        // User detail data
        selectedUser: null,
        selectedUserMetrics: {},
        selectedUserRaw: {},

        // Performance data
        performanceData: null,
        keyAnalysis: null,

        // UI state
        loading: false,
        error: null,
        lastUpdated: null,
        autoRefresh: false,
        refreshInterval: null,
        activeTab: 'overview', // 'overview' | 'performance' | 'keys'

        // Filters and settings
        userLimit: 50,
        sortBy: 'uploads', // 'uploads' | 'xp' | 'streak'
        sortOrder: 'desc', // 'asc' | 'desc'
    }),

    persist: {
        enabled: true,
        strategies: [
            {
                key: 'redis',
                storage: localStorage,
                paths: ['autoRefresh', 'userLimit', 'sortBy', 'sortOrder'],
            },
        ],
    },

    getters: {
        /**
         * Get sorted users based on current sort settings
         */
        sortedUsers: (state) => {
            const users = [...state.users];
            return users.sort((a, b) => {
                const aVal = a[state.sortBy];
                const bVal = b[state.sortBy];
                return state.sortOrder === 'desc' ? bVal - aVal : aVal - bVal;
            });
        },

        /**
         * Get formatted time series data for charts
         */
        timeSeriesChartData: (state) => {
            const months = Object.keys(state.timeSeries).sort();
            return {
                labels: months,
                datasets: [
                    {
                        label: 'Photos',
                        data: months.map((month) => state.timeSeries[month]?.photos || 0),
                    },
                    {
                        label: 'XP',
                        data: months.map((month) => state.timeSeries[month]?.xp || 0),
                    },
                ],
            };
        },

        /**
         * Check if data is stale (older than 5 minutes)
         */
        isDataStale: (state) => {
            if (!state.lastUpdated) return true;
            const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
            return new Date(state.lastUpdated) < fiveMinutesAgo;
        },

        /**
         * Get total metrics across all dimensions
         */
        totalMetrics: (state) => {
            return {
                totalItems: Object.values(state.global).reduce((sum, metric) => sum + metric.total, 0),
                uniqueItems: Object.values(state.global).reduce((sum, metric) => sum + metric.unique, 0),
                totalUsers: state.users.length,
                totalUploads: state.users.reduce((sum, user) => sum + user.uploads, 0),
                totalXp: state.users.reduce((sum, user) => sum + user.xp, 0),
            };
        },
    },

    actions: {
        /**
         * Clear error state
         */
        clearError() {
            this.error = null;
        },

        /**
         * Set active tab
         */
        setActiveTab(tab) {
            this.activeTab = tab;
        },

        /**
         * Update sort settings
         */
        updateSort(sortBy, sortOrder = null) {
            this.sortBy = sortBy;
            if (sortOrder) {
                this.sortOrder = sortOrder;
            } else {
                // Toggle order if same column
                if (this.sortBy === sortBy) {
                    this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
                }
            }
        },

        /**
         * Toggle auto-refresh
         */
        toggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;

            if (this.autoRefresh) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        },

        /**
         * Start auto-refresh interval
         */
        startAutoRefresh() {
            if (this.refreshInterval) return;

            this.refreshInterval = setInterval(() => {
                this.FETCH_REDIS_DATA();
            }, 10000); // 10 seconds
        },

        /**
         * Stop auto-refresh interval
         */
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },

        /**
         * Reset store to initial state
         */
        resetStore() {
            this.stopAutoRefresh();
            this.$reset();
        },

        /**
         * Initialize store with data
         */
        initializeData(data) {
            this.users = data.users || [];
            this.global = data.global || this.global;
            this.timeSeries = data.timeSeries || {};
            this.geo = data.geo || this.geo;
            this.stats = data.stats || this.stats;
            this.lastUpdated = new Date();
        },

        /**
         * Set selected user data
         */
        setSelectedUser(userData) {
            this.selectedUser = userData.user;
            this.selectedUserMetrics = userData.metrics;
            this.selectedUserRaw = userData.raw;
        },

        /**
         * Clear selected user
         */
        clearSelectedUser() {
            this.selectedUser = null;
            this.selectedUserMetrics = {};
            this.selectedUserRaw = {};
        },

        /**
         * Set performance data
         */
        setPerformanceData(data) {
            this.performanceData = data;
        },

        /**
         * Set key analysis data
         */
        setKeyAnalysis(data) {
            this.keyAnalysis = data;
        },

        ...requests,
    },
});
