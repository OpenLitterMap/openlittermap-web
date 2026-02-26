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
        brands: [],
        types: [],
        categoryObjects: [],
        categoryObjectTypes: [],
    }),

    getters: {
        /**
         * Look up CLO id from category + object ids.
         */
        getCloId: (state) => (categoryId, objectId) => {
            const clo = state.categoryObjects.find(
                (co) => co.category_id === categoryId && co.litter_object_id === objectId,
            );
            return clo?.id ?? null;
        },

        /**
         * Get valid types for a given CLO id.
         */
        getTypesForClo: (state) => (cloId) => {
            if (!cloId) return [];
            const typeIds = state.categoryObjectTypes
                .filter((cot) => cot.category_litter_object_id === cloId)
                .map((cot) => cot.litter_object_type_id);
            return state.types.filter((t) => typeIds.includes(t.id));
        },
    },

    actions: {
        ...requests,
        ...mutations,
    },
});
