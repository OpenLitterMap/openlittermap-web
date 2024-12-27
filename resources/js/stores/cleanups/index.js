import { defineStore } from "pinia";
import { requests } from "./requests.js";

export const useCleanupStore = defineStore("cleanups", {

    state: () => ({
        creating: false,
        joining: false,
        lat: null,
        lon: null,
        geojson: null,
        cleanup: null // selected cleanup
    }),

    actions: {
        ...requests,
    }

});
