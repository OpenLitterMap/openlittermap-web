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
    },

    /**
     * Set the country (when clicking into it)
     */
    setCountry (state, payload)
    {
        state.country = payload;
    },

    /**
     * Set the state (when clicking into it)
     */
    setState (state, payload)
    {
        state.state = payload;
    },

    /**
     * Update States + parent country
     */
    setStates (state, payload)
    {
        state.locations = payload.states;
        state.country = payload.country;
    },

    /**
     * Update Cities + parent country, state
     */
    setCities (state, payload)
    {
        state.locations = payload.cities;
        state.country = payload.country;
        state.state = payload.state;
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
