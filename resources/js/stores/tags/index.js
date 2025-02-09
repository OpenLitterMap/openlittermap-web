import { defineStore } from 'pinia';
import { requests } from './requests.js';
import { mutations } from './mutations.js';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        // All Tags in their nested format
        // Category -> Object -> Materials
        groupedTags: [],

        // Non-tested tags in their native format
        categories: [],
        objects: [],
        materials: [],
    }),

    actions: {
        ...requests,
        ...mutations,
    },
});
