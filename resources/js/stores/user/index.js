import { defineStore } from "pinia";
import { useModalStore } from "../modal";

export const useUserStore = defineStore("user", {
    state: () => ({
        admin: false,
        auth: false,
        countries: {}, // options for flags => { ie: "Ireland" }
        errorLogin: '',
        errors: {},
        geojson: {
            features: []
        },
        helper: false,
        position: 0,
        photoPercent: 0,
        requiredXp: 0,
        tagPercent: 0,
        totalPhotos: 0,
        totalTags: 0,
        totalUsers: 0, // Should be on users.old_js
        user: {}
    }),
    actions: {
        clearErrorLogin () {
            this.errorLogin = '';
        },

        /**
         * The user has been authenticated
         */
        doLogin ()
        {
            this.auth = true;
        },

        /**
         * Log the user out
         */
        logout ()
        {
            this.auth = false;
            this.admin = false;
            this.helper = false;
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
                console.log('login_success', response);

                const modalStore = useModalStore();
                modalStore.hideModal();
                this.doLogin();

                window.location.href = '/upload'; // we need to force page refresh to put CSRF token in the session
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
    },
});
