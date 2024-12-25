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

    persist: true,

    actions: {
        clearErrorLogin () {
            this.errorLogin = '';
        },

        logout ()
        {
            this.auth = false;
            this.admin = false;
            this.helper = false;
            window.location.href = "/";
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
    },
});
