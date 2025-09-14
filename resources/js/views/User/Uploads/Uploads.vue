<template>
    <div class="uploads-container">
        <!-- Header with stats in same row -->
        <div class="header-section">
            <div class="header-content">
                <h1>My Uploads</h1>
                <div class="header-stats">
                    <div class="stat-item">
                        <span class="stat-label">Total Photos</span>
                        <span class="stat-value">{{ totalPhotos }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Migrated</span>
                        <span class="stat-value">{{ migratedCount }}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Progress</span>
                        <div class="progress-wrapper">
                            <div class="progress-bar">
                                <div class="progress-fill" :style="{ width: migrationProgress + '%' }"></div>
                            </div>
                            <span class="progress-text">{{ migrationProgress }}%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters">
            <div class="filter-item">
                <label for="filterTag">Tag</label>
                <input id="filterTag" name="filterTag" class="input" v-model="filters.tag" placeholder="Enter a tag" />
            </div>

            <div class="filter-item">
                <label for="filterCustomTag">Custom Tag</label>
                <input
                    id="filterCustomTag"
                    name="filterCustomTag"
                    class="input"
                    v-model="filters.customTag"
                    placeholder="Enter a custom tag"
                />
            </div>

            <div class="filter-item">
                <label for="uploadedFrom">Uploaded From</label>
                <input id="uploadedFrom" name="uploadedFrom" class="input" type="date" v-model="filters.dateFrom" />
            </div>

            <div class="filter-item">
                <label for="uploadedTo">Uploaded To</label>
                <input id="uploadedTo" name="uploadedTo" class="input" type="date" v-model="filters.dateTo" />
            </div>

            <div class="filter-item">
                <label for="amount">Amount</label>
                <select id="amount" class="input" v-model="filters.perPage">
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <button class="button is-primary" @click="applyFilters">Apply Filters</button>
        </div>

        <!-- Photos Grid -->
        <div class="photos-grid">
            <div
                v-for="photo in photos"
                :key="photo.id"
                class="photo-card"
                :class="{ migrated: photo.is_migrated }"
                @click="openTags(photo)"
            >
                <!-- Photo Thumbnail -->
                <div class="photo-thumbnail">
                    <img :src="photo.filename" :alt="`Photo ${photo.id}`" />
                    <div v-if="photo.is_migrated" class="migration-badge">✓ Migrated</div>
                </div>

                <!-- Photo Info -->
                <div class="photo-info">
                    <div class="photo-header">
                        <span class="photo-id">#{{ photo.id }}</span>
                        <span class="photo-date">{{ formatDate(photo.datetime) }}</span>
                    </div>

                    <!-- Tags Summary -->
                    <div class="tags-summary">
                        <div class="tags-stats">
                            <div class="stat-row">
                                <span class="label">Total tags:</span>
                                <span class="value">{{ photo.total_tags || 0 }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Objects:</span>
                                <span class="value">{{ getObjectCount(photo) }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Materials:</span>
                                <span class="value">{{ getMaterialCount(photo) }}</span>
                            </div>
                            <div class="stat-row">
                                <span class="label">Brands:</span>
                                <span class="value">{{ getBrandCount(photo) }}</span>
                            </div>
                        </div>
                        <div class="tags-list">
                            <div v-for="obj in getObjectsList(photo)" :key="obj" class="tag-item">
                                {{ obj }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination">
            <button @click="changePage(1)" :disabled="pagination.current_page === 1" class="page-btn">First</button>

            <button
                @click="changePage(pagination.current_page - 1)"
                :disabled="pagination.current_page === 1"
                class="page-btn"
            >
                Prev
            </button>

            <template v-for="page in paginationRange" :key="page">
                <span v-if="page === '...'" class="page-ellipsis">...</span>
                <button
                    v-else
                    @click="changePage(page)"
                    :class="['page-btn', { active: page === pagination.current_page }]"
                >
                    {{ page }}
                </button>
            </template>

            <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
                class="page-btn"
            >
                Next
            </button>

            <button
                @click="changePage(pagination.last_page)"
                :disabled="pagination.current_page === pagination.last_page"
                class="page-btn"
            >
                Last
            </button>
        </div>

        <!-- UploadTags Modal -->
        <UploadTags v-if="selectedPhoto" :photo="selectedPhoto" @close="selectedPhoto = null" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePhotosStore } from '@/stores/photos';
import UploadTags from './UploadTags.vue';

const store = usePhotosStore();

// State
const selectedPhoto = ref(null);
const filters = ref({
    tag: '',
    customTag: '',
    dateFrom: '',
    dateTo: '',
    perPage: '25',
});

// Computed
const photos = computed(() => store.migrationData.photos || []);
const pagination = computed(() => store.migrationData.pagination || { current_page: 1, last_page: 1 });
const totalPhotos = computed(() => pagination.value.total || 0);
const migratedCount = computed(() => {
    return photos.value.filter((p) => p.is_migrated).length;
});
const migrationProgress = computed(() => {
    if (!totalPhotos.value) return 0;
    return Math.round((migratedCount.value / totalPhotos.value) * 100);
});

const paginationRange = computed(() => {
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const range = [];

    if (last <= 7) {
        for (let i = 1; i <= last; i++) {
            range.push(i);
        }
    } else {
        // Always show first 3 pages
        range.push(1, 2, 3);

        // Add ellipsis if needed
        if (current > 5) {
            range.push('...');
        }

        // Add current page and neighbors if in middle
        if (current > 4 && current < last - 3) {
            range.push(current - 1, current, current + 1);
        }

        // Add ellipsis if needed
        if (current < last - 4) {
            range.push('...');
        }

        // Always show last 3 pages
        range.push(last - 2, last - 1, last);
    }

    // Remove duplicates and sort
    return [...new Set(range.filter((p) => p === '...' || (p >= 1 && p <= last)))];
});

// Methods
const getObjectCount = (photo) => {
    if (!photo.new_tags) return 0;
    return photo.new_tags.filter((tag) => tag.object).length;
};

const getMaterialCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            count += tag.extra_tags.filter((e) => e.type === 'material').length;
        }
    });
    return count;
};

const getBrandCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            count += tag.extra_tags.filter((e) => e.type === 'brand').length;
        }
    });
    return count;
};

const getObjectsList = (photo) => {
    if (!photo.new_tags) return [];
    const objects = [];
    photo.new_tags.forEach((tag) => {
        if (tag.object && tag.object.key) {
            objects.push(tag.object.key);
        }
    });
    return objects.slice(0, 5); // Show max 5 objects
};

const applyFilters = () => {
    // Apply filters to store
    store.setMigrationFilter('tag', filters.value.tag);
    store.setMigrationFilter('customTag', filters.value.customTag);
    store.setMigrationFilter('dateFrom', filters.value.dateFrom);
    store.setMigrationFilter('dateTo', filters.value.dateTo);
    store.migrationData.pagination.per_page = parseInt(filters.value.perPage);
    loadPhotos(1);
};

const loadPhotos = async (page = 1) => {
    await store.fetchMigrationPhotos(page);
};

const changePage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        loadPhotos(page);
    }
};

const openTags = (photo) => {
    selectedPhoto.value = photo;
};

const formatDate = (datetime) => {
    if (!datetime) return 'N/A';
    return new Date(datetime).toLocaleDateString();
};

onMounted(() => {
    loadPhotos(1);
});
</script>

<style scoped>
.uploads-container {
    padding: 3em;
    width: 100%;
    background-color: #3b3b3b;
}

.header-section {
    margin-bottom: 24px;
}

.header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header-content h1 {
    font-size: 28px;
    margin: 0;
}

.header-stats {
    display: flex;
    gap: 32px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-label {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 20px;
    font-weight: bold;
}

.progress-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-bar {
    width: 100px;
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
}

.progress-fill {
    height: 100%;
    background: #4caf50;
    border-radius: 4px;
    transition: width 0.3s;
}

.progress-text {
    font-size: 14px;
    font-weight: bold;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
    padding: 16px;
    background: white;
    border-radius: 8px;
    align-items: flex-end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.filter-item label {
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.button {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.button.is-primary {
    background: #3498db;
    color: white;
}

.button.is-primary:hover {
    background: #2980b9;
}

.photos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.photo-card {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.2s;
}

.photo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.photo-card.migrated {
    border-left: 4px solid #4caf50;
}

.photo-thumbnail {
    position: relative;
    height: 180px;
    background: #f8f8f8;
}

.photo-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.migration-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #4caf50;
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

.photo-info {
    padding: 12px;
}

.photo-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
}

.photo-id {
    font-weight: bold;
    color: #333;
}

.photo-date {
    color: #666;
    font-size: 14px;
}

.tags-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 8px;
    background: #f8f8f8;
    border-radius: 4px;
}

.tags-stats {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stat-row {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.stat-row .label {
    color: #666;
}

.stat-row .value {
    font-weight: bold;
    color: #333;
}

.tags-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 12px;
    color: #555;
}

.tag-item {
    padding: 2px 4px;
    background: white;
    border-radius: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 24px 0;
}

.page-btn {
    padding: 8px 12px;
    background: #1e2128;
    border: 1px solid #2a2f3a;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    min-width: 40px;
    color: #eaecef;
    transition:
        background-color 0.2s,
        border-color 0.2s;
}

.page-btn:hover:not(:disabled) {
    background: #252a33;
    border-color: #3a4152;
}

.page-btn.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.page-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    color: #5a6472;
}

.page-ellipsis {
    padding: 0 8px;
    color: #5a6472;
}
</style>
