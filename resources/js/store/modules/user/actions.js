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
     *
     */
    async GET_CURRENT_USER (context)
    {
        await axios.get('/current-user')
        .then(response => {
            console.log('get_current_user', response);

            context.commit('initUser', response.data);
        })
        .catch(error => {
            console.log('error.get_current_user', error);
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

            context.commit('login');
            context.commit('hideModal');

            routes.push({ path: '/submit' });
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
                window.location.href = '/';
            })
            .catch(error => {
                console.log('error.logout', error);
            });
    },

    /**
     * The user wants to update name, email, username
     */
    async UPDATE_DETAILS (context)
    {
        let title = i18n.t('notifications.success');
        // todo - translate this
        let body  = 'Your infomration have been updated'

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
    }
};
