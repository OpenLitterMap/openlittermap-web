import { defineStore } from 'pinia';
import { useUserStore } from './user/index.js';

export const useProfileStore = defineStore('profile', {
    state: () => ({
        loading: false,
        error: null,

        user: {},
        stats: { uploads: 0, litter: 0, xp: 0, streak: 0 },
        level: {},
        rank: { global_position: 0, global_total: 0, percentile: 0 },
        global_stats: { total_photos: 0, total_litter: 0 },
        achievements: { unlocked: 0, total: 0 },
        locations: { countries: 0, states: 0, cities: 0 },
        team: null,
    }),

    getters: {
        levelProgress: (state) => state.level.progress_percent ?? 0,
        levelTitle: (state) => state.level.title ?? 'Beginner',
    },

    actions: {
        async FETCH_PROFILE() {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await axios.get('/api/user/profile/index');

                this.user = data.user;
                this.stats = data.stats;
                this.level = data.level;
                this.rank = data.rank;
                this.global_stats = data.global_stats;
                this.achievements = data.achievements;
                this.locations = data.locations;
                this.team = data.team;

                // Sync settings-related fields to user store
                const userStore = useUserStore();
                if (userStore.user) {
                    const settingsFields = [
                        'name', 'username', 'email', 'public_profile',
                        'show_name', 'show_username', 'show_name_maps',
                        'show_username_maps', 'picked_up', 'previous_tags', 'emailsub',
                    ];
                    settingsFields.forEach((key) => {
                        if (key in data.user) {
                            userStore.user[key] = data.user[key];
                        }
                    });
                }
            } catch (e) {
                this.error = e.response?.status === 401 ? 'unauthenticated' : 'Failed to load profile';
            } finally {
                this.loading = false;
            }
        },
    },
});
