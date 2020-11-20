export const mutations = {

    /**
     * Delete an error with payload key
     */
    clearTeamsError (state, payload)
    {
        delete state.errors[payload];
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
