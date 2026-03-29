import { useModalStore } from '../modal/index.js';
import router from '../../router/index.js';

export const requests = {
    async CHECK_AUTH() {
        await axios
            .get('/check-auth')
            .then((response) => {
                console.log('check_auth', response);

                if (response.data.success) {
                    this.auth = true;
                    // Sync user data (picked_up, xp, level, etc.) from server
                    this.REFRESH_USER();
                } else {
                    this.$reset();
                }
            })
            .catch((error) => {
                console.log('error.check_auth', error);
                this.$reset();
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

            // Login regenerates the session, invalidating the old CSRF token.
            // Refresh the XSRF-TOKEN cookie so subsequent requests use the new token.
            await axios.get('/sanctum/csrf-cookie');

            const modalStore = useModalStore();
            modalStore.hideModal();

            this.auth = true;
            this.user = {
                ...response.data.user,
                xp: response.data.stats?.xp,
                next_level: response.data.level,
            };

            // New users go to onboarding; returning users go to intended route
            if (!this.onboardingCompleted) {
                router.push('/onboarding');
            } else {
                const intended = sessionStorage.getItem('intended_route');
                sessionStorage.removeItem('intended_route');
                router.push(intended || '/upload');
            }
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
     * Refresh user data (XP, level, position)
     */
    async REFRESH_USER() {
        try {
            const { data } = await axios.get('/api/user/profile/refresh');
            this.user = {
                ...this.user,
                ...data.user,
                xp: data.stats.xp,
                next_level: data.level,
            };
        } catch (error) {
            // Silent — non-critical refresh
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
            this.user = data.user ?? { id: data.user_id, email: data.email };

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
