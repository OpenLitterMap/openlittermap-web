import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useUserStore = defineStore('user', {
    state: () => ({
        admin: false,
        auth: false,
        countries: {}, // options for flags => { ie: "Ireland" }
        errorLogin: '',
        errors: {},
        geojson: {
            features: [],
        },
        helper: false,
        position: 0,
        photoPercent: 0,
        requiredXp: 0,
        tagPercent: 0,
        totalPhotos: 0,
        totalTags: 0,
        totalUsers: 0, // Should be on users.old_js
        user: {},
    }),

    persist: true,

    actions: {
        clearErrorLogin() {
            this.errorLogin = '';
        },

        logout() {
            this.auth = false;
            this.admin = false;
            this.helper = false;
            window.location.href = '/';
        },

        initUser(user) {
            this.user = user;
        },

        ...requests,
    },
});
