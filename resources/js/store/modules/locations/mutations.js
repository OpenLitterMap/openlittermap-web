import { init } from './init'

export const mutations = {

    /**
     * Reset state, when the user logs out
     */
    resetLocations (state)
    {
        Object.assign(state, init);
    },

    /**
     * Update countries + global metadata
     */
    setCountries (state, payload)
    {
        state.locations = payload.countries;
        state.globalLeaders = payload.globalLeaders;
        state.total_litter = payload.total_litter;
        state.total_photos = payload.total_photos;
        state.level.previousXp = payload.previousXp;
        state.level.nextXp = payload.nextXp;
        state.littercoin = payload.littercoin;
    },

    setGlobalLeaders (state, payload)
    {
        state.globalLeaders = payload;
    },

    /**
     * Set the country name (when clicking into it)
     */
    countryName (state, payload)
    {
        state.countryName = payload;
    },

    /**
     * Set the state name (when clicking into it)
     */
    stateName (state, payload)
    {
        state.stateName = payload;
    },

    /**
     * Update States + parent country
     */
    setLocations (state, payload)
    {
        state.locations = payload;
    },

    /**
     * When a slider on city/options moves, update the min-date, max-date and hex-size
     */
    updateCitySlider (state, payload)
    {
        let locations = [...state.locations];

        locations[payload.index].minDate = payload.dates[0];
        locations[payload.index].maxDate = payload.dates[1];
        locations[payload.index].hex = payload.hex;

        state.locations = locations;
    },

    /**
     * Increment the total_photos value
     */
    incrementTotalPhotos (state, payload = 1)
    {
        state.total_photos += payload;
    },

    /**
     * Decrement the total_photos value
     */
    decrementTotalPhotos (state, payload = 1)
    {
        state.total_photos -= payload;
    },

    /**
     * Increment the total_litter value
     */
    incrementTotalLitter (state, payload)
    {
        state.total_litter += payload;
    },

    /**
     * Decrement the total_litter value
     */
    decrementTotalLitter (state, payload)
    {
        state.total_litter -= payload;
    },

    /**
     * Change how to sort the order of the locations on the LitterWorldCup
     */
    setSortLocationsBy (state, payload)
    {
        state.sortLocationsBy = payload;
    },

    /**
     * One of the tabs has been selected
     *
     * We need to know which locationId we showing for the Leaderboard
     */
    setSelectedLocationId (state, payload)
    {
        state.selectedLocationId = payload;
    },

    updateLocationTabKey (state)
    {
        state.locationTabKey++;
    }
};
