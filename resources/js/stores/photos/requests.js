import { useToast } from 'vue-toastification';
import i18n from '../../i18n.js';
const toast = useToast();
const t = i18n.global.t;

export const requests = {
    /**
     * Fetch photos with filters
     */
    async GET_USERS_PHOTOS(page = 1, filters = {}) {
        try {
            const params = {
                page,
                per_page: filters.perPage || 25,
            };

            // Add tagged filter
            if (filters.tagged !== null && filters.tagged !== undefined) {
                params.tagged = filters.tagged ? 1 : 0;
            }

            // Add ID filter
            if (filters.id) {
                params.id = filters.id;
                params.id_operator = filters.idOperator || '=';
            }

            // Add tag filter
            if (filters.tag) {
                params.tag = filters.tag;
            }

            // Add custom tag filter
            if (filters.customTag) {
                params.custom_tag = filters.customTag;
            }

            // Add date filters
            if (filters.dateFrom) {
                params.date_from = filters.dateFrom;
            }
            if (filters.dateTo) {
                params.date_to = filters.dateTo;
            }

            const response = await axios.get('/api/v3/user/photos', { params });

            // Update store with new structure
            this.paginated = {
                data: response.data.photos || [],
                ...response.data.pagination,
            };
            this.photos = response.data.photos || [];
            this.pagination = response.data.pagination || {
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 0,
            };
            this.user = response.data.user || null;

            // Extract custom tags if they exist in the old tags
            if (this.photos.length > 0) {
                const customTags = new Set();
                this.photos.forEach((photo) => {
                    // Check old tags for custom tags
                    if (photo.old_tags?.customTags) {
                        Object.keys(photo.old_tags.customTags).forEach((tag) => {
                            customTags.add(tag);
                        });
                    }
                    // Check new tags for custom tags
                    photo.new_tags?.forEach((tag) => {
                        if (tag.primary_custom_tag?.key) {
                            customTags.add(tag.primary_custom_tag.key);
                        }
                    });
                });
                this.previousCustomTags = Array.from(customTags);
            }

            return response.data;
        } catch (error) {
            console.error('get_users_photos', error);
            throw error;
        }
    },

    /**
     * Fetch stats separately (can be cached)
     */
    async GET_UNTAGGED_STATS() {
        try {
            const response = await axios.get('/api/v3/user/photos/stats');

            this.untaggedStats = {
                totalPhotos: response.data.totalPhotos || 0,
                totalTags: response.data.totalTags || 0,
                leftToTag: response.data.leftToTag || 0,
                taggedPercentage: response.data.taggedPercentage || 0,
            };

            return response.data;
        } catch (error) {
            console.error('get_untagged_stats', error);
            throw error;
        }
    },

    /**
     * Delete a single photo
     */
    async DELETE_PHOTO(photoId) {
        try {
            await axios.post('/api/profile/photos/delete', { photoid: photoId });

            const title = t('Photo deleted');
            toast.success(title);

            // Refresh photos and stats
            await Promise.all([
                this.GET_USERS_PHOTOS(this.pagination.current_page, this.currentFilters),
                this.GET_UNTAGGED_STATS(),
            ]);

            return true;
        } catch (error) {
            const title = t('Failed to delete photo');
            toast.error(title);
            throw error;
        }
    },

    /**
     * Fetch a single photo by ID for editing
     */
    async GET_SINGLE_PHOTO(photoId) {
        try {
            const response = await axios.get('/api/v3/user/photos', {
                params: { id: photoId, id_operator: '=', per_page: 1 },
            });

            return response.data.photos?.[0] || null;
        } catch (error) {
            console.error('get_single_photo', error);
            throw error;
        }
    },

    /**
     * Replace all tags on a photo (for editing already-tagged photos)
     */
    async REPLACE_TAGS({ photoId, tags }) {
        try {
            const response = await axios.put('/api/v3/tags', {
                photo_id: photoId,
                tags: tags,
            });

            if (response.data.success) {
                const title = t('Tags Updated');
                toast.success(title);
                await this.GET_UNTAGGED_STATS();
            }

            return response.data;
        } catch (error) {
            throw error;
        }
    },

    /**
     * Upload tags for a photo
     */
    async UPLOAD_TAGS({ photoId, tags }) {
        try {
            const response = await axios.post('/api/v3/tags', {
                photo_id: photoId,
                tags: tags,
            });

            if (response.data.success) {
                const title = t('Tags Added');
                toast.success(title);

                // Refresh stats and photos in parallel to avoid stale intermediate state
                await Promise.all([
                    this.GET_UNTAGGED_STATS(),
                    this.GET_USERS_PHOTOS(this.pagination.current_page, this.currentFilters),
                ]);
            }

            return response.data;
        } catch (error) {
            throw error;
        }
    },
};
