export const mutations = {

    /**
     * Remove a specific key from errors
     */
    clearCreateAccountError (state, payload)
    {
        delete state.errors[payload];
    },

    /**
     * Valiation has failed.
     */
    createAccountErrors (state, payload)
    {
        state.errors = payload;
    },

    /**
     * The plans have been retrieved from the database
     */
    setPlans (state, payload)
    {
        state.plans = payload;
    }
};
