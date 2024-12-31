import { defineStore } from "pinia";
import { requests } from "./requests.js";

export const useGlobalMapStore = defineStore("globalMap", {

    state: () => {
        return {
            artData: [],
            clustersGeojson: {
                type: 'FeatureCollection',
                features: []
            },
            pointsGeojson: {
                type: 'FeatureCollection',
                features: []
            },
            currentDate: 'today',
            loading: true, // reload component
            datesOpen: false, // change dates box on global map
            langsOpen: false,
            customTagsFound: []
        }
    },

    actions: {
        ...requests,
    }

});
