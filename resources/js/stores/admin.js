import { defineStore } from 'pinia';
import { useToast } from 'vue-toastification';

const toast = useToast();

export const useAdminStore = defineStore('admin', {
    state: () => ({
        photos: {
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        stats: {
            total_pending: 0,
            queue_total: 0,
            queue_today: 0,
            by_verification: {},
            by_country: {},
            total_users: 0,
            users_today: 0,
            flagged_usernames: 0,
        },
        statsLoading: false,
        countries: {},
        filters: {
            country_id: null,
            user_id: null,
            photo_id: null,
            date_from: null,
            date_to: null,
        },
        loading: false,
        submitting: false,

        // User management
        users: {
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        usersLoading: false,
    }),

    getters: {
        pendingCount: (state) => state.stats.total_pending,
        activeFilters: (state) => {
            return Object.fromEntries(
                Object.entries(state.filters).filter(([, v]) => v !== null && v !== '')
            );
        },
    },

    actions: {
        async fetchPhotos(page = 1) {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/admin/photos', {
                    params: {
                        ...this.activeFilters,
                        page,
                        per_page: 20,
                    },
                });

                if (data.success) {
                    this.photos = data.photos;
                    Object.assign(this.stats, data.stats);
                }
            } catch (e) {
                console.error('fetchPhotos', e);
                toast.error('Failed to load photos');
            } finally {
                this.loading = false;
            }
        },

        async fetchCountries() {
            try {
                const { data } = await axios.get('/api/admin/get-countries-with-photos');
                this.countries = data;
            } catch (e) {
                console.error('fetchCountries', e);
            }
        },

        async approvePhoto(photoId) {
            this.submitting = true;

            try {
                const { data } = await axios.post('/api/admin/verify', { photoId });

                if (data.success) {
                    toast.success('Photo approved');
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('approvePhoto', e);
                toast.error('Failed to approve photo');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async deletePhoto(photoId) {
            this.submitting = true;

            try {
                const { data } = await axios.post('/api/admin/destroy', { photoId });

                if (data.success) {
                    toast.success('Photo deleted');
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('deletePhoto', e);
                toast.error('Failed to delete photo');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async updateTagsAndApprove(photoId, tags) {
            this.submitting = true;

            try {
                const { data } = await axios.post('/api/admin/contentsupdatedelete', {
                    photoId,
                    tags,
                });

                if (data.success) {
                    toast.success('Tags updated and photo approved');
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('updateTagsAndApprove', e);
                toast.error('Failed to update tags');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async resetTags(photoId) {
            this.submitting = true;

            try {
                const { data } = await axios.post('/api/admin/reset-tags', { photoId });

                if (data.success) {
                    toast.success('Tags reset — photo returned to untagged');
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('resetTags', e);
                toast.error('Failed to reset tags');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        // ─── User management ─────────────────────────────

        async fetchUsers(params = {}) {
            this.usersLoading = true;

            try {
                const { data } = await axios.get('/api/admin/users', { params });

                if (data.success) {
                    this.users = data.users;
                }
            } catch (e) {
                console.error('fetchUsers', e);
                toast.error('Failed to load users');
            } finally {
                this.usersLoading = false;
            }
        },

        async toggleTrust(userId, trusted) {
            this.submitting = true;

            try {
                const { data } = await axios.post(`/api/admin/users/${userId}/trust`, { trusted });

                if (data.success !== false) {
                    toast.success(trusted ? 'User trusted' : 'User untrusted');
                    return true;
                }

                return false;
            } catch (e) {
                console.error('toggleTrust', e);
                toast.error(e.response?.data?.message || 'Failed to update trust');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async approveAllForUser(userId) {
            this.submitting = true;

            try {
                const { data } = await axios.post(`/api/admin/users/${userId}/approve-all`);

                if (data.success !== false) {
                    toast.success(`${data.approved_count || 0} photos approved`);
                    return data;
                }

                return false;
            } catch (e) {
                console.error('approveAllForUser', e);
                toast.error(e.response?.data?.message || 'Failed to approve photos');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async toggleSchoolManager(userId, enabled) {
            this.submitting = true;

            try {
                const { data } = await axios.post(`/api/admin/users/${userId}/school-manager`, {
                    enabled,
                });

                toast.success(enabled ? 'School manager role granted' : 'School manager role removed');
                return true;
            } catch (e) {
                console.error('toggleSchoolManager', e);
                toast.error(e.response?.data?.message || 'Failed to update school manager role');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async impersonateUser(userId) {
            this.submitting = true;

            try {
                await axios.post(`/api/admin/users/${userId}/impersonate`);
                window.location.href = '/';
                return true;
            } catch (e) {
                console.error('impersonateUser', e);
                toast.error(e.response?.data?.message || 'Failed to impersonate user');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async updateUsername(userId, username) {
            this.submitting = true;

            try {
                const { data } = await axios.patch(`/api/admin/users/${userId}/username`, {
                    username,
                });

                toast.success(`Username updated to ${data.username}`);
                return true;
            } catch (e) {
                console.error('updateUsername', e);
                const msg =
                    e.response?.data?.errors?.username?.[0] ||
                    e.response?.data?.message ||
                    'Failed to update username';
                toast.error(msg);
                return false;
            } finally {
                this.submitting = false;
            }
        },

        async fetchStats() {
            this.statsLoading = true;

            try {
                const { data } = await axios.get('/api/admin/stats');

                if (data.success) {
                    Object.assign(this.stats, data.stats);
                }
            } catch (e) {
                console.error('fetchStats', e);
            } finally {
                this.statsLoading = false;
            }
        },

        setFilter(key, value) {
            this.filters[key] = value || null;
        },

        resetFilters() {
            this.filters = {
                country_id: null,
                user_id: null,
                photo_id: null,
                date_from: null,
                date_to: null,
            };
        },
    },
});
