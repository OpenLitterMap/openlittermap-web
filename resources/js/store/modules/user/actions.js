import routes from '../../../routes'
import Vue from "vue";
import i18n from "../../../i18n";

export const actions = {

    /**
     * The user wants to change their password
     * 1. Validate old password
     * 2. Validate new password
     * 3. Change password & return success
     */
    async CHANGE_PASSWORD (context, payload)
    {
        await axios.patch('/settings/details/password', {
            oldpassword: payload.oldpassword,
            password: payload.password,
            password_confirmation: payload.password_confirmation
        })
        .then(response => {
            console.log('change_password', response);

            // success
        })
        .catch(error => {
            console.log('error.change_password', error.response.data);

            // update errors. user.js
            context.commit('errors', error.response.data.errors);
        });
    },

    /**
     * Throwing an await method here from router.beforeEach allows Vuex to init before vue-router returns auth false.
     */
    CHECK_AUTH (context)
    {
        // console.log('CHECK AUTH');
    },

    /**
     *
     */
    async DELETE_ACCOUNT (context, payload)
    {
        await axios.post('/settings/delete', {
            password: payload
        })
        .then(response => {
            console.log('delete_account', response);

            // success
        })
        .catch(error => {
            console.log('error.delete_account', error.response.data);

            // update errors

        });
    },

    /**
     * Send the user an email containing a CSV with all of their data
     */
    async DOWNLOAD_MY_DATA (context)
    {
        const title = i18n.t('notifications.success');
        const body = 'Your download is being processed and will be emailed to you.'

        await axios.get('/user/profile/download')
            .then(response => {
                console.log('download_my_data', response);

                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            })
            .catch(error => {
                console.error('download_my_data', error);
            });
    },

    /**
     * When we log in, we need to dispatch a request to get the current user
     */
    async GET_CURRENT_USER (context)
    {
        await axios.get('/current-user')
        .then(response => {
            console.log('get_current_user', response);

            context.commit('initUser', response.data);
            context.commit('set_default_litter_presence', response.data.items_remaining);
        })
        .catch(error => {
            console.log('error.get_current_user', error);
        });
    },

    /**
     *
     */
    async GET_COUNTRIES_FOR_FLAGS (context)
    {
        await axios.get('/settings/flags/countries')
            .then(response => {
                console.log('flags_countries', response);

                context.commit('flags_countries', response.data);
            })
            .catch(error => {
                console.log('error.flags_countries', error);
            });
    },

    /**
     * Get the total number of users, and the current users rank (1st, 2nd...)
     *
     * and more
     */
    async GET_USERS_PROFILE_DATA (context)
    {
        await axios.get('/user/profile/index')
            .then(response => {
                console.log('get_users_position', response);

                context.commit('usersPosition', response.data);
            })
            .catch(error => {
                console.error('get_users_position', error);
            });
    },

    /**
     * Get the geojson data for the users Profile/ProfileMap
     */
    async GET_USERS_PROFILE_MAP_DATA (context, payload)
    {
        await axios.get('/user/profile/map', {
            params: {
                period: payload.period,
                start: payload.start + ' 00:00:00',
                end: payload.end + ' 23:59:59'
            }
        })
        .then(response => {
            console.log('get_users_profile_map_data', response);

            context.commit('usersGeojson', response.data.geojson);
        })
        .catch(error => {
            console.error('get_users_profile_map_data', error);
        });
    },

    /**
     * Try to log the user in
     * Todo - return the user object
     */
    async LOGIN (context, payload)
    {
        await axios.post('/login', {
            email: payload.email,
            password: payload.password
        })
        .then(response => {
            console.log('login_success', response);

            context.commit('hideModal');
            context.commit('login');

            window.location.href = '/upload'; // we need to force page refresh to put CSRF token in the session
        })
        .catch(error => {
            console.log('error.login', error.response.data);

            context.commit('errorLogin', error.response.data.email);
        });
    },

    /**
     * Try to log the user out
     */
    async LOGOUT (context)
    {
        await axios.get('/logout')
            .then(response => {
                console.log('logout', response);

                context.commit('logout');

                // this will reset state for all objects
                context.commit('resetState');

                window.location.href = '/';
            })
            .catch(error => {
                console.log('error.logout', error);
            });
    },

    /**
     * Save all privacy settings on Privacy.vue
     */
    async SAVE_PRIVACY_SETTINGS (context)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.privacy-updated');

        await axios.post('/settings/privacy/update', {
            show_name_maps: context.state.user.show_name_maps,
            show_username_maps: context.state.user.show_username_maps,
            show_name: context.state.user.show_name,
            show_username: context.state.user.show_username,
            show_name_createdby: context.state.user.show_name_createdby,
            show_username_createdby:  context.state.user.show_username_createdby
        })
        .then(response => {
            console.log('save_privacy_settings', response);

            /* improve css */
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });
        })
        .catch(error => {
            console.log('error.save_privacy_settings', error);
        });
    },

    /**
     * Change value of user wants to receive emails eg updates
     */
    async TOGGLE_EMAIL_SUBSCRIPTION (context)
    {
        let title = i18n.t('notifications.success');
        let sub = i18n.t('notifications.settings.subscribed');
        let unsub = i18n.t('notifications.settings.unsubscribed');

        await axios.post('/settings/email/toggle')
            .then(response => {
                console.log('toggle_email_subscription', response);

                if (response.data.sub)
                {
                    /* improve css */
                    Vue.$vToastify.success({
                        title,
                        body: sub,
                        position: 'top-right'
                    });
                }

                else
                {
                    /* improve css */
                    Vue.$vToastify.success({
                        title,
                        body: unsub,
                        position: 'top-right'
                    });
                }

                context.commit('toggle_email_sub', response.data.sub);
            })
            .catch(error => {
                console.log(error);
            })
    },

    /**
     * Toggle the setting of litter picked up or still there
     */
    async TOGGLE_LITTER_PICKED_UP_SETTING (context)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.litter-toggled');

        await axios.post('/settings/toggle')
            .then(response => {
                console.log('toggle_litter', response);

                if (response.data.message === 'success')
                {
                    context.commit('toggle_litter_picked_up', response.data.value);

                    /* improve css */
                    Vue.$vToastify.success({
                        title,
                        body,
                        position: 'top-right'
                    });
                }
            })
            .catch(error => {
                console.log(error);
            });
    },

    /**
     * The user wants to update name, email, username
     */
    async UPDATE_DETAILS (context)
    {
        const title = i18n.t('notifications.success');
        // todo - translate this
        const body  = 'Your information has been updated'

        await axios.post('/settings/details', {
            name: context.state.user.name,
            email: context.state.user.email,
            username: context.state.user.username
        })
        .then(response => {
            console.log('update_details', response);

            /* improve this */
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });
        })
        .catch(error => {
            console.log('error.update_details', error);

            // update errors. user.js
            context.commit('errors', error.response.data.errors);
        });
    },

    /**
     * Update the flag the user can show on the Global leaderboard
     */
    async UPDATE_GLOBAL_FLAG (context, payload)
    {
        let title = i18n.t('notifications.success');
        let body  = i18n.t('notifications.settings.flag-updated');

        await axios.post('/settings/save-flag', {
            country: payload
        })
        .then(response => {
            console.log(response);

            /* improve this */
            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });
        })
        .catch(error => {
            console.log(error);
        });
    }
};
