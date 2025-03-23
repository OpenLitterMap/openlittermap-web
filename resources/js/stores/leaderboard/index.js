import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useLeaderboardStore = defineStore('leaderboard', {
    state: () => ({
        currentPage: 1,
        hasNextPage: false,

        // array of users in the leaderboard
        leaderboard: [],

        // locationId: array
        country: {},
        state: {},
        city: {},
    }),

    actions: {
        ...requests,
    },
});
