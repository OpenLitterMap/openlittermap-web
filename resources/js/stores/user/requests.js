import { useModalStore } from "../modal/index.js";

export const requests = {

    async CHECK_AUTH ()
    {
        await axios.get('/check-auth')
            .then(response => {
                console.log('check_auth', response);

                if (!response.data.success) {
                    this.$reset();
                }
            })
            .catch(error => {
                console.log('error.check_auth', error);
            });
    },

    /**
     * When we log in, we need to dispatch a request to get the current user
     *
     * Also checks for auth on app load.
     */
    async GET_CURRENT_USER ()
    {
        await axios.get('/current-user')
            .then(response => {
                console.log('get_current_user', response);

                // context.commit('initUser', response.data);
                // context.commit('set_default_litter_picked_up', response.data.picked_up);
            })
            .catch(error => {
                console.log('error.get_current_user', error);
            });
    },

    /**
     * Try to log the user in
     * Todo - return the user object
     */
    async LOGIN (payload)
    {
        await axios.post('/login', {
            email: payload.email,
            password: payload.password
        })
            .then(response => {
                const modalStore = useModalStore();
                modalStore.hideModal();
                this.auth = true;

                // we need to force page refresh to put CSRF token in the session
                window.location.href = '/upload';
            })
            .catch(error => {
                console.log('error.login', error.response.data);

                this.errorLogin = error.response.data.email;
            });
    },

    /**
     * Try to log the user out
     */
    async LOGOUT ()
    {
        await axios.get('/logout')
            .then(response => {
                console.log('logout', response);

                this.logout();

                // this will reset state for all objects
                this.$reset();

                window.location.href = '/';
            })
            .catch(error => {
                console.log('error.logout', error);
            });
    },
}
