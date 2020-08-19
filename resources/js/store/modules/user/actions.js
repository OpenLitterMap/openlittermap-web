import routes from '../../../routes'

export const actions = {

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
            console.log('error.login', error);
        });
    },

    /**
     * Try to log the user out
     */
    async LOGOUT (context)
    {
        await axios.post('logout')
        .then(response => {
            console.log('logout', response);

            context.commit('logout');
        })
        .catch(error => {
            console.log('error.logout', error);
        });
    }
};