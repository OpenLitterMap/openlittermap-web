<template>
    <div class="uploads-container">
        <!-- Header with stats -->
        <div class="header-section">
            <h1>My Uploads</h1>
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-label">Total Photos</span>
                    <span class="stat-value">{{ totalPhotos }}</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Migrated</span>
                    <span class="stat-value">{{ migratedCount }}</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Migration Progress</span>
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: migrationProgress + '%' }"></div>
                    </div>
                    <span class="stat-value">{{ migrationProgress }}%</span>
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <select v-model="filters.migrationStatus" @change="applyFilters">
                <option value="all">All Photos</option>
                <option value="migrated">Migrated Only</option>
                <option value="unmigrated">Not Migrated</option>
            </select>

            <select v-model="filters.complexity" @change="applyFilters">
                <option value="all">Any Complexity</option>
                <option value="simple">Simple (1-5 tags)</option>
                <option value="moderate">Moderate (6-15 tags)</option>
                <option value="complex">Complex (15+ tags)</option>
            </select>

            <input
                type="text"
                v-model="filters.search"
                @input="debounceSearch"
                placeholder="Search tags..."
                class="search-input"
            />

            <button @click="clearFilters" class="btn-secondary">Clear Filters</button>
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

                    <!-- Complexity Meter -->
                    <div class="complexity-meter">
                        <div class="complexity-score">
                            <span class="score-label">Complexity:</span>
                            <span class="score-value">{{ getComplexityScore(photo) }}</span>
                        </div>
                        <div class="complexity-badges">
                            <span v-if="getCounts(photo, 'objects') > 0" class="badge objects">
                                Objects({{ getCounts(photo, 'objects') }})
                            </span>
                            <span v-if="getCounts(photo, 'materials') > 0" class="badge materials">
                                Materials({{ getCounts(photo, 'materials') }})
                            </span>
                            <span v-if="getCounts(photo, 'brands') > 0" class="badge brands">
                                Brands({{ getCounts(photo, 'brands') }})
                            </span>
                            <span v-if="getCounts(photo, 'custom') > 0" class="badge custom">
                                Custom({{ getCounts(photo, 'custom') }})
                            </span>
                        </div>
                    </div>

                    <!-- Tag Counts Comparison -->
                    <div class="tag-comparison">
                        <div class="old-counts">
                            <span class="count-label">Old (v4):</span>
                            <span class="count-value">{{ getOldTagCount(photo) }} items</span>
                        </div>
                        <div class="new-counts">
                            <span class="count-label">New (v5):</span>
                            <span class="count-value">{{ photo.total_tags || 0 }} items</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="pagination">
            <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1">
                Previous
            </button>
            <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button
                @click="changePage(pagination.current_page + 1)"
                :disabled="pagination.current_page === pagination.last_page"
            >
                Next
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
    migrationStatus: 'all',
    complexity: 'all',
    search: '',
});
const searchTimeout = ref(null);

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

// Methods
const getCounts = (photo, type) => {
    if (!photo.new_tags) return 0;

    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (type === 'objects' && tag.object) count++;

        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'material' && type === 'materials') {
                    count += extra.quantity || 1;
                }
                if (extra.type === 'brand' && type === 'brands') {
                    count += extra.quantity || 1;
                }
                if (extra.type === 'custom_tag' && type === 'custom') {
                    count += extra.quantity || 1;
                }
            });
        }

        if (type === 'custom' && tag.primary_custom_tag) count++;
    });

    return count;
};

const getComplexityScore = (photo) => {
    if (!photo.new_tags) return 0;

    let score = 0;
    score += getCounts(photo, 'objects');
    score += getCounts(photo, 'brands');
    score += getCounts(photo, 'materials');
    score += getCounts(photo, 'custom') * 0.5;
    score += photo.summary?.totals?.picked_up > 0 ? 0.5 : 0;

    return Math.round(score * 10) / 10;
};

const getOldTagCount = (photo) => {
    if (!photo.old_tags) return 0;

    let count = 0;
    Object.values(photo.old_tags).forEach((category) => {
        if (Array.isArray(category)) {
            count += category.length;
        } else if (typeof category === 'object') {
            Object.values(category).forEach((qty) => {
                count += parseInt(qty) || 0;
            });
        }
    });

    return count;
};

// Actions
const applyFilters = () => {
    store.setMigrationFilter('status', filters.value.migrationStatus);
    store.setMigrationFilter('complexity', filters.value.complexity);
    loadPhotos(1);
};

const debounceSearch = () => {
    clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(() => {
        store.setMigrationFilter('search', filters.value.search);
        loadPhotos(1);
    }, 500);
};

const clearFilters = () => {
    filters.value = { migrationStatus: 'all', complexity: 'all', search: '' };
    store.clearMigrationFilters();
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
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.header-section h1 {
    font-size: 28px;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 16px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 8px;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
}

.progress-bar {
    height: 8px;
    background: #f0f0f0;
    border-radius: 4px;
    margin: 8px 0;
}

.progress-fill {
    height: 100%;
    background: #4caf50;
    border-radius: 4px;
    transition: width 0.3s;
}

.filters-bar {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    padding: 16px;
    background: white;
    border-radius: 8px;
}

.filters-bar select,
.search-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-input {
    flex: 1;
    max-width: 300px;
}

.btn-secondary {
    padding: 8px 16px;
    background: #f0f0f0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #e0e0e0;
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

.complexity-meter {
    margin-bottom: 12px;
    padding: 8px;
    background: #f8f8f8;
    border-radius: 4px;
}

.complexity-score {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.score-label {
    font-size: 12px;
    color: #666;
}

.score-value {
    font-weight: bold;
    color: #333;
}

.complexity-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.badge {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.badge.objects {
    background: #e3f2fd;
    color: #1976d2;
}

.badge.materials {
    background: #e8f5e9;
    color: #4caf50;
}

.badge.brands {
    background: #f3e5f5;
    color: #9c27b0;
}

.badge.custom {
    background: #fff3e0;
    color: #ff9800;
}

.tag-comparison {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    padding: 8px;
    background: #fafafa;
    border-radius: 4px;
}

.old-counts,
.new-counts {
    display: flex;
    align-items: center;
    gap: 4px;
}

.count-label {
    font-size: 12px;
    color: #666;
}

.count-value {
    font-weight: bold;
    color: #333;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin: 24px 0;
}

.pagination button {
    padding: 8px 16px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
}

.pagination button:hover:not(:disabled) {
    background: #f8f8f8;
}

.pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
