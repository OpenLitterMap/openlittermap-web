import { defineStore } from 'pinia';

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
        },
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
                    this.stats = data.stats;
                }
            } catch (e) {
                console.error('fetchPhotos', e);
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
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('approvePhoto', e);
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
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('deletePhoto', e);
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
                    await this.fetchPhotos(this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('updateTagsAndApprove', e);
                return false;
            } finally {
                this.submitting = false;
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
