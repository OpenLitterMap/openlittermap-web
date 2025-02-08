import { defineStore } from 'pinia';
import { requests } from './requests.js';
import { mutations } from './mutations.js';

export const useTagsStore = defineStore('tags', {
    state: () => ({
        // All Tags in their nested format
        // Category -> Object -> TagType -> Materials
        groupedTags: [],

        // Non-tested tags in their native format
        categories: [],
        objects: [],
        tagTypes: [],
        materials: [],

        // Objects for Categories
        objectsForCategory: {},
    }),

    actions: {
        ...requests,
        ...mutations,
    },
});
