import { defineStore } from 'pinia';
import { requests } from './requests.js';

export const usePhotosStore = defineStore('photos', {
    state: () => {
        return {
            // Existing state
            filters: {
                id: '',
                dateRange: {
                    start: null,
                    end: null,
                },
                period: 'created_at',
                verified: null,
            },
            paginated: null,
            bulkPaginate: {
                prev_page_url: null,
                next_page_url: null,
                data: [],
            },
            myUploadsPaginate: null,
            remaining: 0,
            selectedCount: 0,
            selectAll: false,
            inclIds: [], // when selectAll is false
            exclIds: [], // when selectAll is true
            total: 0, // number of photos available
            verified: 0, // level of verification
            previousCustomTags: [],
            showDetailsPhotoId: null,

            // New migration verification state
            migrationData: {
                photos: [],
                currentPhoto: null,
                pagination: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 20,
                    total: 0,
                },
                loading: false,
                error: null,
                filters: {
                    showMigrated: true,
                    showUnmigrated: true,
                    searchTerm: '',
                },
            },
        };
    },

    getters: {
        // New getters for migration verification
        filteredMigrationPhotos: (state) => {
            return state.migrationData.photos.filter((photo) => {
                // Filter by migration status
                if (!state.migrationData.filters.showMigrated && photo.is_migrated) return false;
                if (!state.migrationData.filters.showUnmigrated && !photo.is_migrated) return false;

                // Filter by search term
                if (state.migrationData.filters.searchTerm) {
                    const term = state.migrationData.filters.searchTerm.toLowerCase();

                    // Search in old tags
                    const oldTagsStr = JSON.stringify(photo.old_tags).toLowerCase();
                    if (oldTagsStr.includes(term)) return true;

                    // Search in new tags
                    const newTagsStr = JSON.stringify(photo.new_tags).toLowerCase();
                    if (newTagsStr.includes(term)) return true;

                    // Search in filename
                    if (photo.filename && photo.filename.toLowerCase().includes(term)) return true;

                    return false;
                }

                return true;
            });
        },

        totalOldTags: (state) => {
            return state.migrationData.photos.reduce((total, photo) => {
                if (!photo.old_tags) return total;

                let count = 0;
                Object.values(photo.old_tags).forEach((category) => {
                    if (Array.isArray(category)) {
                        count += category.length;
                    } else if (typeof category === 'object') {
                        Object.values(category).forEach((val) => {
                            count += parseInt(val) || 0;
                        });
                    }
                });
                return total + count;
            }, 0);
        },

        totalNewTags: (state) => {
            return state.migrationData.photos.reduce((total, photo) => {
                return total + (photo.total_tags || 0);
            }, 0);
        },

        migrationProgress: (state) => {
            const total = state.migrationData.photos.length;
            if (total === 0) return 0;

            const migrated = state.migrationData.photos.filter((p) => p.is_migrated).length;
            return Math.round((migrated / total) * 100);
        },
    },

    actions: {
        ...requests,

        // New actions for migration verification
        async fetchMigrationPhotos(page = 1) {
            this.migrationData.loading = true;
            this.migrationData.error = null;

            try {
                const response = await axios.get('/api/user/photos', {
                    params: {
                        page,
                        per_page: this.migrationData.pagination.per_page,
                    },
                });

                this.migrationData.photos = response.data.photos;
                this.migrationData.pagination = response.data.pagination;
            } catch (error) {
                this.migrationData.error = error.response?.data?.message || 'Failed to fetch photos';
                console.error('Error fetching migration photos:', error);
            } finally {
                this.migrationData.loading = false;
            }
        },

        async fetchMigrationPhoto(photoId) {
            this.migrationData.loading = true;
            this.migrationData.error = null;

            try {
                const response = await axios.get(`/api/user/photos/${photoId}`);
                this.migrationData.currentPhoto = response.data.photo;
                return response.data.photo;
            } catch (error) {
                this.migrationData.error = error.response?.data?.message || 'Failed to fetch photo';
                console.error('Error fetching migration photo:', error);
            } finally {
                this.migrationData.loading = false;
            }
        },

        setMigrationFilter(filterName, value) {
            this.migrationData.filters[filterName] = value;
        },

        clearMigrationFilters() {
            this.migrationData.filters = {
                showMigrated: true,
                showUnmigrated: true,
                searchTerm: '',
            };
        },

        compareOldAndNewTags(photo) {
            const comparison = {
                matches: [],
                mismatches: [],
                missing: [],
                added: [],
            };

            // Count old tags
            const oldTagCounts = {};
            if (photo.old_tags) {
                Object.entries(photo.old_tags).forEach(([category, items]) => {
                    if (category === 'custom_tags') {
                        oldTagCounts['custom'] = items.length;
                    } else if (typeof items === 'object') {
                        Object.entries(items).forEach(([tag, count]) => {
                            oldTagCounts[tag] = (oldTagCounts[tag] || 0) + parseInt(count);
                        });
                    }
                });
            }

            // Count new tags
            const newTagCounts = {};
            if (photo.new_tags) {
                photo.new_tags.forEach((tag) => {
                    if (tag.object) {
                        newTagCounts[tag.object.key] = (newTagCounts[tag.object.key] || 0) + tag.quantity;
                    }
                    if (tag.primary_custom_tag) {
                        newTagCounts['custom'] = (newTagCounts['custom'] || 0) + tag.quantity;
                    }
                    if (tag.extra_tags) {
                        tag.extra_tags.forEach((extra) => {
                            if (extra.tag) {
                                const key = extra.type === 'brand' ? `brand:${extra.tag.key}` : extra.tag.key;
                                newTagCounts[key] = (newTagCounts[key] || 0) + extra.quantity;
                            }
                        });
                    }
                });
            }

            // Compare
            Object.entries(oldTagCounts).forEach(([tag, count]) => {
                if (newTagCounts[tag]) {
                    if (newTagCounts[tag] === count) {
                        comparison.matches.push({ tag, count });
                    } else {
                        comparison.mismatches.push({
                            tag,
                            oldCount: count,
                            newCount: newTagCounts[tag],
                        });
                    }
                    delete newTagCounts[tag];
                } else {
                    comparison.missing.push({ tag, count });
                }
            });

            // Remaining new tags are additions
            Object.entries(newTagCounts).forEach(([tag, count]) => {
                comparison.added.push({ tag, count });
            });

            return comparison;
        },
    },
});
