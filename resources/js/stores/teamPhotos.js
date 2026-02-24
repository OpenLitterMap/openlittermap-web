import { defineStore } from 'pinia';

export const useTeamPhotosStore = defineStore('teamPhotos', {
    state: () => ({
        photos: {
            data: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        },
        stats: {
            total: 0,
            pending: 0,
            approved: 0,
        },
        currentPhoto: null,
        mapPoints: [],
        filter: 'all', // 'all' | 'pending' | 'approved'
        loading: false,
        approving: false,
        errors: {},
    }),

    getters: {
        pendingCount: (state) => state.stats.pending,
        hasPending: (state) => state.stats.pending > 0,
    },

    actions: {
        /**
         * Fetch paginated photos for a team.
         */
        async fetchPhotos(teamId, page = 1) {
            this.loading = true;

            try {
                const { data } = await axios.get('/api/teams/photos', {
                    params: {
                        team_id: teamId,
                        status: this.filter,
                        page,
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

        /**
         * Fetch a single photo with tags for editing.
         */
        async fetchPhoto(photoId) {
            try {
                const { data } = await axios.get(`/api/teams/photos/${photoId}`);

                if (data.success) {
                    this.currentPhoto = data.photo;
                }
            } catch (e) {
                console.error('fetchPhoto', e);
            }
        },

        /**
         * Update tags on a photo (teacher edit).
         */
        async updateTags(photoId, tags) {
            this.errors = {};

            try {
                const { data } = await axios.patch(`/api/teams/photos/${photoId}/tags`, {
                    tags,
                });

                if (data.success) {
                    this.currentPhoto = data.photo;

                    // Update in list
                    const idx = this.photos.data.findIndex((p) => p.id === photoId);
                    if (idx !== -1) {
                        this.photos.data[idx] = data.photo;
                    }

                    return true;
                }

                return false;
            } catch (e) {
                if (e?.response?.status === 422) {
                    this.errors = e.response.data.errors || {};
                }
                return false;
            }
        },

        /**
         * Approve specific photos or all pending.
         */
        async approvePhotos(teamId, photoIds = null) {
            this.approving = true;

            try {
                const payload = { team_id: teamId };

                if (photoIds) {
                    payload.photo_ids = photoIds;
                } else {
                    payload.approve_all = true;
                }

                const { data } = await axios.post('/api/teams/photos/approve', payload);

                if (data.success) {
                    // Refresh the list
                    await this.fetchPhotos(teamId, this.photos.current_page);
                    return data.approved_count;
                }

                return 0;
            } catch (e) {
                console.error('approvePhotos', e);
                return 0;
            } finally {
                this.approving = false;
            }
        },

        /**
         * Fetch map points for team.
         */
        async fetchMapPoints(teamId) {
            try {
                const { data } = await axios.get('/api/teams/photos/map', {
                    params: { team_id: teamId },
                });

                if (data.success) {
                    this.mapPoints = data.points;
                }
            } catch (e) {
                console.error('fetchMapPoints', e);
            }
        },

        setFilter(filter) {
            this.filter = filter;
        },

        clearCurrentPhoto() {
            this.currentPhoto = null;
        },
    },
});
