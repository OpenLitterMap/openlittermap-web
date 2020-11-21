export const mutations = {

    /**
     * Delete an error with payload key
     */
    clearTeamsError (state, payload)
    {
        delete state.errors[payload];
    },

    /**
     * Change what team component the user is viewing
     */
    teamComponent (state, payload)
    {
        state.component_type = payload;
    },

    /**
     * There was a problem creating a new team
     */
    teamErrors (state, payload)
    {
        state.errors = payload;
    },

    /**
     * Init team.types from database
     */
    teamTypes (state, payload)
    {
        state.types = payload;
    }
}
