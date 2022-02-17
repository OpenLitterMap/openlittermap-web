import routes from '../../../routes'
import Vue from "vue";
import i18n from "../../../i18n";
import router from '../../../routes';

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
     * The user is requesting a password reset link
     */
    async SEND_PASSWORD_RESET_LINK (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = "An email will be sent with a link to reset your password if the email exists.";

        Vue.$vToastify.success({
            title,
            body
        });

        await axios.post('/password/email', {
            email: payload,
        })
        .then(response => {
            // console.log('send_password_reset_link', response);
        })
        .catch(error => {
            // console.log('error.send_password_reset_link', error.response.data);
        });
    },

    /**
     * The user is resetting their password
     */
    async RESET_PASSWORD (context, payload)
    {
        const title = i18n.t('notifications.success');

        await axios.post('/password/reset', payload)
            .then(response => {
                console.log('reset_password', response);

                if (!response.data.success) return;

                Vue.$vToastify.success({
                    title,
                    body: response.data.message
                });

                // Go home and log in
                setTimeout(function() {
                    router.replace('/');
                    router.go(0);
                }, 4000);
            })
            .catch(error => {
                console.log('error.reset_password', error.response.data);

                context.commit('errors', error.response.data.errors);
            });
    },

    /**
     * A user is contacting OLM
     */
    async SEND_EMAIL_TO_US (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body = 'We got your email. You\'ll hear from us soon!'

        await axios.post('/contact-us', payload)
            .then(response => {
                console.log('send_email_to_us', response);

                Vue.$vToastify.success({title, body});
            })
            .catch(error => {
                console.log('error.send_email_to_us', error.response.data);

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
        const username = context.state.public_profile.publicProfile.username;

        await axios.get('/user/profile/index', {
            params: {
                username
            }
        })
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
     *
     * This can be for the currently authenticated user, or a public profile
     */
    async GET_USERS_PROFILE_MAP_DATA (context, payload)
    {
        const url = (context.state.public_profile.publicProfile)
            ? '/user/public-profile/map'
            : '/user/profile/map';

        const username = context.state.public_profile.publicProfile.hasOwnProperty('username')
            ? context.state.public_profile.publicProfile.username
            : null;

        const title = i18n.t('notifications.success');
        const body = "Map data updated";

        await axios.get(url, {
            params: {
                period: payload.period,
                start: payload.start + ' 00:00:00',
                end: payload.end + ' 23:59:59',
                username
            }
        })
        .then(response => {
            console.log('get_users_profile_map_data', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });

                context.commit('usersGeojson', response.data.geojson);
            }
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
        const title = i18n.t('notifications.success');
        const body  = i18n.t('notifications.privacy-updated');

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
        const title = i18n.t('notifications.success');
        const body  = i18n.t('notifications.litter-toggled');

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
     * Toggle the privacy status of the users dashboard
     */
    async TOGGLE_PUBLIC_PROFILE (context)
    {
        const title = i18n.t('notifications.success');

        const nowPublic = "Your Profile is now public";
        const nowPrivate = "Your Profile is now private";

        await axios.post('/settings/public-profile/toggle')
            .then(response => {
                console.log('toggle_public_profile', response);

                if (response.data.success)
                {
                    context.commit('updateUserSettings', response.data.settings);

                    Vue.$vToastify.success({
                        title,
                        body: (response.data.settings.show_public_profile)
                            ? nowPublic
                            : nowPrivate,
                        position: 'top-right'
                    });
                }
            })
            .catch(error => {
                console.error('toggle_public_profile', error);
            });
    },

    /**
     * The user wants to update name, email, username
     */
    async UPDATE_DETAILS (context)
    {
        const title = i18n.t('notifications.success');
        const body  = 'Your information has been updated'

        await axios.post('/settings/details', {
            name: context.state.user.name,
            email: context.state.user.email,
            username: context.state.user.username
        })
        .then(response => {
            console.log('update_details', response);

            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });
        })
        .catch(error => {
            console.error('update_details', error);

            // update errors. user.js
            context.commit('errors', error.response.data.errors);
        });
    },

    /**
     * Update the flag the user can show on the Global leaderboard
     */
    async UPDATE_GLOBAL_FLAG (context, payload)
    {
        const title = i18n.t('notifications.success');
        const body  = i18n.t('notifications.settings.flag-updated');

        await axios.post('/settings/save-flag', {
            country: payload
        })
        .then(response => {
            console.log('update_global_flag', response);

            Vue.$vToastify.success({
                title,
                body,
                position: 'top-right'
            });
        })
        .catch(error => {
            console.log('update_global_flag', error);
        });
    },

    /**
     * Update the settings of the users public profile
     */
    async UPDATE_PUBLIC_PROFILE_SETTINGS (context)
    {
        const title = i18n.t('notifications.success');
        const body = "Your settings have been updated";

        await axios.post('/settings/public-profile/update', {
            map: context.state.user.settings.map,
            download: context.state.user.settings.download,
        })
        .then(response => {
            console.log('update_public_profile_settings', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            }
        })
        .catch(error => {
            console.error('update_public_profile_settings', error);
        });
    },

    /**
     * Update the users links to twitter and instagram
     */
    async UPDATE_SOCIAL_MEDIA_LINKS (context)
    {
        const title = i18n.t('notifications.success');
        const body = "Your settings have been updated";

        await axios.post('/settings/social-media/update', {
            twitter: context.state.user.settings.twitter,
            instagram: context.state.user.settings.instagram,
        })
        .then(response => {
            console.log('update_public_profile_settings', response);

            if (response.data.success)
            {
                Vue.$vToastify.success({
                    title,
                    body,
                    position: 'top-right'
                });
            }
        })
        .catch(error => {
            console.error('update_public_profile_settings', error);
        });
    }
};
