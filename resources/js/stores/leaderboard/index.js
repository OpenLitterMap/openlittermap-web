import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useLeaderboardStore = defineStore('leaderboard', {
    state: () => ({
        currentPage: 1,
        hasNextPage: false,

        currentFilters: {
            timeFilter: 'all-time',
            locationType: null,
            locationId: null,
        },

        // array of users in the leaderboard
        leaderboard: [],

        // locationId: array
        country: {},
        state: {},
        city: {},

        selectedLocationId: null,
        locationTabKey: 0,
    }),

    actions: {
        ...requests,
    },
});
