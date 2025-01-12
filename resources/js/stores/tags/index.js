import { defineStore } from "pinia";
import { requests } from "./requests.js";

export const useTagsStore = defineStore('tags', {

    state: () => ({
        // All Tags
        tags: [],

        // Parent Categories
        categories: [],


    }),

    actions: {
        ...requests
    }

});
