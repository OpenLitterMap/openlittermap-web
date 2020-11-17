import { init } from './init'

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
     * List of countries to choose global flag in settings
     */
    flags_countries (state, payload)
    {
        state.countries = payload;
    },

    /**
     * User object from HomeController@index if auth
     */
    initUser (state, payload)
    {
        state.user = payload;

        if (payload.id === 1) state.admin = true;
        if (payload.id === 3768) state.admin = true;
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
        state.admin = false;
    },

    /**
     * The user wants to change a privacy setting
     */
    privacy (state, payload)
    {
        state.user[payload.column] = payload.v;
    },

    /**
     * Reset state, when the user logs out
     */
    resetState (state)
    {
        Object.assign(state, init);
    },

    /**
     * Does the user want to receive emails? Data from backend
     */
    toggle_email_sub (state, payload)
    {
        state.user.emailsub = payload;
    },

    /**
     * Default value of litter picked up from backend
     */
    toggle_litter_picked_up (state, payload)
    {
        state.user.items_remaining = payload;
    }

};
