import { defineStore } from 'pinia';
import { useToast } from 'vue-toastification';

const toast = useToast();

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
        memberStats: [],
        filter: 'all', // 'all' | 'pending' | 'approved'
        loading: false,
        submitting: false,
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
         * Update tags on a photo (teacher edit, CLO format).
         */
        async updateTags(photoId, tags) {
            this.errors = {};
            this.submitting = true;

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
                toast.error('Failed to update tags');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Update tags then approve in sequence (save edits flow).
         */
        async updateTagsAndApprove(teamId, photoId, tags) {
            this.submitting = true;

            try {
                // Step 1: Update tags
                const { data: tagData } = await axios.patch(`/api/teams/photos/${photoId}/tags`, {
                    tags,
                });

                if (!tagData.success) {
                    toast.error('Failed to update tags');
                    return false;
                }

                // Step 2: Approve
                const { data: approveData } = await axios.post('/api/teams/photos/approve', {
                    team_id: teamId,
                    photo_ids: [photoId],
                });

                if (approveData.success) {
                    await this.fetchPhotos(teamId, this.photos.current_page);
                    return true;
                }

                return false;
            } catch (e) {
                console.error('updateTagsAndApprove', e);
                toast.error('Failed to save edits');
                return false;
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Approve specific photos or all pending.
         */
        async approvePhotos(teamId, photoIds = null) {
            this.approving = true;
            this.submitting = true;

            try {
                const payload = { team_id: teamId };

                if (photoIds) {
                    payload.photo_ids = photoIds;
                } else {
                    payload.approve_all = true;
                }

                const { data } = await axios.post('/api/teams/photos/approve', payload);

                if (data.success) {
                    this.memberStats = [];
                    await this.fetchPhotos(teamId, this.photos.current_page);
                    return data.approved_count;
                }

                return 0;
            } catch (e) {
                console.error('approvePhotos', e);
                toast.error('Failed to approve photos');
                return 0;
            } finally {
                this.approving = false;
                this.submitting = false;
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

        /**
         * Delete a team photo (teacher only).
         */
        async deletePhoto(teamId, photoId) {
            this.submitting = true;

            try {
                const { data } = await axios.delete(`/api/teams/photos/${photoId}`, {
                    params: { team_id: teamId },
                });

                if (data.success) {
                    this.stats = data.stats;
                    this.memberStats = [];
                    await this.fetchPhotos(teamId, this.photos.current_page);
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

        /**
         * Revoke approval on photos (teacher only).
         */
        async revokePhotos(teamId, photoIds = null) {
            this.submitting = true;

            try {
                const payload = { team_id: teamId };

                if (photoIds) {
                    payload.photo_ids = photoIds;
                } else {
                    payload.revoke_all = true;
                }

                const { data } = await axios.post('/api/teams/photos/revoke', payload);

                if (data.success) {
                    this.memberStats = [];
                    await this.fetchPhotos(teamId, this.photos.current_page);
                    return data.revoked_count;
                }

                return 0;
            } catch (e) {
                console.error('revokePhotos', e);
                toast.error('Failed to revoke photos');
                return 0;
            } finally {
                this.submitting = false;
            }
        },

        /**
         * Fetch per-member stats for the team.
         */
        async fetchMemberStats(teamId) {
            try {
                const { data } = await axios.get('/api/teams/photos/member-stats', {
                    params: { team_id: teamId },
                });

                if (data.success) {
                    this.memberStats = data.members;
                }
            } catch (e) {
                console.error('fetchMemberStats', e);
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
