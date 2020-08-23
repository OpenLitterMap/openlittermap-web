export const mutations = {

    /**
     * An error has been received when making a login request
     */
    errorLogin (state, payload)
    {
        state.errorLogin = payload;
    },

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
