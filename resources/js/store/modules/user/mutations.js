export const mutations = {

    /**
     * Settings.details
     */
    changeUserEmail (state, payload)
    {
        state.user.email = payload;
    },

    /**
     * Settings.details
     */
    changeUserName (state, payload)
    {
        state.user.name = payload;
    },

    /**
     * Settings.details
     */
    changeUserUsername (state, payload)
    {
        state.user.username = payload;
    },

    /**
     * Settings
     */
    deleteUserError (state, payload)
    {
        delete state.errors[payload];
    },

    /**
     * An error has been received when making a login request
     */
    errorLogin (state, payload)
    {
        state.errorLogin = payload;
    },

    /**
     * Todo - refactor all user errors (Login, Signup, Settings)
     */
    errors (state, payload)
    {
        state.errors = payload;
    },

    /**
     * User object from HomeController@index if auth
     */
    initUser (state, payload)
    {
        state.user = payload;
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
    },

    /**
     *
     */
    show_name_createdby (state, payload)
    {
        state.user.show_name_createdby = payload;
    }

};
