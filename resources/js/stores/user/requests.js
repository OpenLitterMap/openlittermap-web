import { useModalStore } from '../modal/index.js';

export const requests = {
    async CHECK_AUTH() {
        await axios
            .get('/check-auth')
            .then((response) => {
                console.log('check_auth', response);

                if (response.data.success) {
                    this.auth = true;
                } else {
                    this.$reset();
                }
            })
            .catch((error) => {
                console.log('error.check_auth', error);
            });
    },

    // /**
    //  * When we log in, we need to dispatch a request to get the current user
    //  *
    //  * Also checks for auth on app load.
    //  */
    // async GET_CURRENT_USER() {
    //     await axios
    //         .get('/api/current-user')
    //         .then((response) => {
    //             console.log('get_current_user', response);
    //         })
    //         .catch((error) => {
    //             console.log('error.get_current_user', error);
    //         });
    // },

    /**
     * Log in via email or username
     */
    async LOGIN(payload) {
        try {
            const response = await axios.post('/api/auth/login', {
                identifier: payload.identifier,
                password: payload.password,
            });

            const modalStore = useModalStore();
            modalStore.hideModal();

            this.auth = true;
            this.user = response.data.user;

            // Session cookie is set automatically — redirect to upload
            window.location.href = '/upload';
        } catch (error) {
            if (error?.response?.status === 422) {
                this.errorLogin =
                    error.response.data.errors?.identifier?.[0] || error.response.data.message || 'Invalid credentials';
            } else if (error?.response?.status === 429) {
                this.errorLogin = 'Too many requests. Please try again later.';
            } else {
                this.errorLogin = 'Something went wrong. Please try again.';
            }
        }
    },

    /**
     * Log out and invalidate session
     */
    async LOGOUT_REQUEST() {
        try {
            await axios.post('/api/auth/logout');
        } catch (error) {
            console.log('error.logout', error);
        } finally {
            this.$reset();
            window.location.href = '/';
        }
    },

    /**
     * Register a new account via the API
     */
    async REGISTER(payload) {
        this.errors = {};

        try {
            const { data } = await axios.post('/api/auth/register', payload);

            this.auth = true;
            this.user = { id: data.user_id, email: data.email };

            return data;
        } catch (error) {
            if (error?.response?.status === 422) {
                this.errors = error.response.data.errors || {};
            } else {
                this.errors = { general: ['Something went wrong. Please try again.'] };
            }
            return false;
        }
    },
};
