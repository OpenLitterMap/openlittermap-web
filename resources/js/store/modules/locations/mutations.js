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
        state.globalLeaders = JSON.parse(payload.globalLeaders);
        state.total_litter = payload.total_litter;
        state.total_photos = payload.total_photos;
        state.level.previousXp = payload.previousXp;
        state.level.nextXp = payload.nextXp;
        state.littercoin = payload.littercoin;
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
    }
};
