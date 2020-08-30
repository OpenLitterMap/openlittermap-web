export const mutations = {

    /**
     * An error has been received when making a login request
     */
    errorLogin (state, payload)
    {
        state.errorLogin = payload;
    },

    /**
     * Todo - refactor all user errors (Login, Signup, Settings) to this.
     */
    errors (state, payload)
    {
        state.errors = payload;
    },

    /**
     * The user has been authenticated
     */
    login (state)
    {
        console.log('LOGIN COMMIT');
        state.auth = true;
        // update user
    },

    /**
     * Log the user out
     */
    logout (state)
    {
        state.auth = false;
    }

};
