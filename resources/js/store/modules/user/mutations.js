export const mutations = {

    /**
     * The user has been authenticated
     */
    login (state)
    {
        state.auth = true;
    },

    /**
     * Log the user out
     */
    logout (state)
    {
        state.auth = false;
    }

};