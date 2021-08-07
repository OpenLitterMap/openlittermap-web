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
     * When the user successfully creates a team, we need to decrement the remaining teams on the frontend
     */
    decrementUsersRemainingTeams (state)
    {
        state.user.remaining_teams--;
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

        if (window.Laravel.jsPermissions.roles.includes('admin') || window.Laravel.jsPermissions.roles.includes('superadmin'))
        {
            state.admin = true;
        }

        if (window.Laravel.jsPermissions.roles.includes('helper'))
        {
            state.helper = true;
        }
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
        state.helper = false;
    },

    /**
     * If the user created a team and this is their first team,
     * we want to set their active_team to the new team_id
     */
    usersActiveTeam (state, payload)
    {
        state.user.active_team = payload;
    },

    /**
     * The user wants to change a column in the users table
     */
    userColumnUpdate (state, payload)
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
    },

    /**
     * Users map data for the given time-period
     */
    usersGeojson (state, payload)
    {
        state.geojson = payload;
    },

    /**
     * On Profile.vue, get the total number of users and the current users rank
     */
    usersPosition (state, payload)
    {
        state.position = payload.usersPosition;
        state.totalUsers = payload.totalUsers;
        state.totalPhotos = payload.totalPhotos;
        state.totalTags = payload.totalTags;

        state.photoPercent = Math.round(payload.photoPercent * 100, 2);
        state.tagPercent = Math.round(payload.tagPercent * 100, 2);

        state.requiredXp = payload.requiredXp;
    },

    // /**
    //  * The user has just created and joined a team
    //  */
    // userJoinTeam (state, payload)
    // {
    //     state.user['team'] = payload;
    // }

};
