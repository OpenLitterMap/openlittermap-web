import { defineStore } from "pinia";
import { requests } from "./requests.js";

export const useMerchantStore = defineStore("merchants", {

    state: () => ({
        geojson: {},
        merchant: {
            lat: 0,
            lon: 0
        }
    }),

    actions: {
        ...requests,
    }

});
