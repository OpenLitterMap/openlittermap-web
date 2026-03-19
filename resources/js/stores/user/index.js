import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const useUserStore = defineStore('user', {
    state: () => ({
        admin: false,
        auth: false,
        countries: {},
        emailConfirmed: false,
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
        totalUsers: 0,
        unsubscribed: false,
        user: {},
    }),

    persist: true,

    actions: {
        clearErrorLogin() {
            this.errorLogin = '';
        },

        clearError(key) {
            if (this.errors?.[key]) {
                const next = { ...this.errors };
                delete next[key];
                this.errors = next;
            }
        },

        clearErrors() {
            this.errors = {};
        },

        // deprecated. just do $reset
        // logout() {
        //     this.auth = false;
        //     this.admin = false;
        //     this.helper = false;
        //     this.user = null;
        //     window.location.href = '/';
        // },

        initUser(user) {
            this.user = user;
            this.auth = true;
        },

        ...requests,
    },
});
