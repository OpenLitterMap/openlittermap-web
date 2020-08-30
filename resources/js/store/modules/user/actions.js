import routes from '../../../routes'

export const actions = {

    /**
     *
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

            // update errors

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
     * Try to log the user in
     */
    async LOGIN (context, payload)
    {
        await axios.post('login', {
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
        await axios.get('logout')
        .then(response => {
            console.log('logout', response);

            context.commit('logout');
            window.location.href = '/';
        })
        .catch(error => {
            console.log('error.logout', error);
        });
    }
};
