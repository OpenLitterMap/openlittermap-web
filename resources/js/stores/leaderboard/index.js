import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useLeaderboardStore = defineStore('leaderboard', {
    state: () => ({
        currentPage: 1,
        hasNextPage: false,
        total: 0,
        activeUsers: 0,
        totalUsers: 0,
        currentUserRank: null,
        loading: false,
        error: null,

        currentFilters: {
            timeFilter: 'all-time',
            locationType: null,
            locationId: null,
        },

        leaderboard: [],
        countries: [],
        states: [],
        cities: [],
    }),

    actions: {
        ...requests,
    },
});
