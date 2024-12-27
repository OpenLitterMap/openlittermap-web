import { defineStore } from "pinia";
import { requests } from "./requests.js";

export const useGlobalStore = defineStore("global", {

    state: () => {
        return {
            artData: [],
            currentDate: 'today',
            loading: true, // reload component
            datesOpen: false, // change dates box on global map
            langsOpen: false,
            geojson: [],
            customTagsFound: []
        }
    },

    actions: {
        ...requests,
    }

});
