import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useWorldStore = defineStore('world', {
    state: () => ({
        countryName: '',
        globalLeaders: [],
        hex: null,
        level: {
            previousXp: 0,
            nextXp: 0,
        },
        locations: [], // counties, states, cities
        littercoin: 0, // owed to users
        minDate: null,
        maxDate: null,
        previousLevelInt: 0,
        progressPercent: 0,
        sortLocationsBy: 'most-data',
        stateName: '',
        totalLitterInt: 0,
        total_litter: 0,
        total_photos: 0,

        // For WorldCup, SortLocations, components
        selectedLocationId: 0,
        locationTabKey: 0,

        // For History page
        countryNames: [],
    }),

    actions: {
        ...requests,

        setSortLocationsBy(sort) {
            this.sortLocationsBy = sort;
        },

        /**
         * When a slider on city/options moves, update the min-date, max-date and hex-size
         */
        updateCitySlider({ index, dates, hex }) {
            this.locations[index].minDate = dates[0];
            this.locations[index].maxDate = dates[1];
            this.locations[index].hex = hex;
        },
    },
});
