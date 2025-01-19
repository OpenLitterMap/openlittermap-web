import { defineStore } from 'pinia';
import { requests } from './requests.js';
import { mutations } from './mutations.js';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        // All Tags
        tags: [],

        // Parent Categories
        categories: [],

        // Objects for Categories
        objectsForCategory: {},
    }),

    actions: {
        ...requests,
        ...mutations,
    },
});
