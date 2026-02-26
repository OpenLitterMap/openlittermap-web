import { defineStore } from 'pinia';
import { useUserStore } from './user/index.js';

export const useSettingsStore = defineStore('settings', {
    state: () => ({
        saving: false,
        error: null,
        successMessage: null,

        // Delete account
        deleting: false,
        deleteError: null,
    }),

    actions: {
        /**
         * Update a single setting via key/value.
         */
        async UPDATE_SETTING(key, value) {
            this.saving = true;
            this.error = null;
            this.successMessage = null;

            try {
                await axios.post('/api/settings/update', { key, value });

                // Sync the user store if it's a user-level field
                const userStore = useUserStore();
                if (userStore.user && key in userStore.user) {
                    userStore.user[key] = value;
                }

                this.successMessage = 'Setting updated.';
                return true;
            } catch (e) {
                this.error = e.response?.data?.msg || 'Failed to update setting.';
                return false;
            } finally {
                this.saving = false;
            }
        },

        /**
         * Toggle a privacy setting (maps name/username, leaderboard name/username, etc.)
         */
        async TOGGLE_PRIVACY(endpoint) {
            this.saving = true;
            this.error = null;

            try {
                const { data } = await axios.post(endpoint);
                return data;
            } catch (e) {
                this.error = e.response?.data?.msg || 'Failed to toggle privacy setting.';
                return null;
            } finally {
                this.saving = false;
            }
        },

        /**
         * Delete account with password confirmation.
         */
        async DELETE_ACCOUNT(password) {
            this.deleting = true;
            this.deleteError = null;

            try {
                const { data } = await axios.post('/api/settings/delete-account', { password });

                if (data.success) {
                    const userStore = useUserStore();
                    userStore.$reset();
                    window.location.href = '/';
                    return true;
                }

                this.deleteError = data.msg || 'Failed to delete account.';
                return false;
            } catch (e) {
                this.deleteError = e.response?.data?.msg || 'Failed to delete account.';
                return false;
            } finally {
                this.deleting = false;
            }
        },

        clearMessages() {
            this.error = null;
            this.successMessage = null;
            this.deleteError = null;
        },
    },
});
