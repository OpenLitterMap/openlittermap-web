export const mutations = {

    /**
     * The plans have been retrieved from the database
     */
    setPlans (state, payload)
    {
        state.plans = payload;
    }

};