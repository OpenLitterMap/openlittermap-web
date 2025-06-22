<template>
    <div class="achievements-container">
        <!-- Header -->
        <div class="achievements-header">
            <h1 class="title">
                <svg class="title-icon" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M12 2L14.09 8.26L21 9.27L16.45 13.97L17.82 21L12 17.77L6.18 21L7.55 13.97L3 9.27L9.91 8.26L12 2Z"
                        fill="currentColor"
                        stroke="currentColor"
                        stroke-width="2"
                    />
                </svg>
                Achievements
            </h1>
            <div class="summary-stats" v-if="summary">
                <div class="stat-badge">
                    <span class="stat-value">{{ summary.unlocked }}</span>
                    <span class="stat-label">Unlocked</span>
                </div>
                <div class="stat-badge">
                    <span class="stat-value">{{ summary.percentage }}%</span>
                    <span class="stat-label">Complete</span>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="loading-container">
            <div class="loading-spinner"></div>
            <p>Loading achievements...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-container">
            <p>{{ error }}</p>
            <button @click="fetchAchievements" class="retry-btn">Retry</button>
        </div>

        <!-- Content -->
        <div v-else-if="data" class="achievements-content">
            <!-- Overview Rows -->
            <section class="overview-section">
                <!-- Uploads Row -->
                <div class="achievement-row" v-if="overview.uploads">
                    <div class="row-header">
                        <div class="row-title">
                            <div class="row-icon icon-uploads">
                                <svg viewBox="0 0 24 24">
                                    <path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z" fill="currentColor" />
                                </svg>
                            </div>
                            <h3>Uploads</h3>
                            <span class="row-progress">{{ overview.uploads?.progress?.toLocaleString() || 0 }}</span>
                        </div>
                    </div>
                    <div class="achievements-scroll">
                        <div class="next-achievement" v-if="overview.uploads?.next">
                            <svg class="lock-icon-mini" viewBox="0 0 24 24">
                                <path
                                    d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                    fill="currentColor"
                                />
                            </svg>
                            <div class="next-value">{{ overview.uploads.next.threshold.toLocaleString() }}</div>
                            <div class="next-label">
                                {{ (overview.uploads.next.threshold - overview.uploads.progress).toLocaleString() }}
                                remaining
                            </div>
                            <div class="next-progress">
                                <div class="progress-bar-mini">
                                    <div
                                        class="progress-fill"
                                        :style="{ width: (overview.uploads?.percentage || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="achievement-card" v-for="achievement in sortedUploads" :key="achievement.id">
                            <div class="achievement-value">{{ achievement.threshold.toLocaleString() }}</div>
                            <div class="achievement-label">Unlocked on</div>
                            <div class="achievement-date">{{ formatDate(achievement.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Daily Streak Row -->
                <div class="achievement-row" v-if="overview.streak">
                    <div class="row-header">
                        <div class="row-title">
                            <div class="row-icon icon-streak">
                                <svg viewBox="0 0 24 24">
                                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" fill="currentColor" />
                                </svg>
                            </div>
                            <h3>Daily Streak</h3>
                            <span class="row-progress"
                                >{{ overview.streak?.progress?.toLocaleString() || 0 }} days</span
                            >
                        </div>
                    </div>
                    <div class="achievements-scroll">
                        <div class="next-achievement streak-next" v-if="overview.streak?.next">
                            <svg class="lock-icon-mini" viewBox="0 0 24 24">
                                <path
                                    d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                    fill="currentColor"
                                />
                            </svg>
                            <div class="next-value">{{ overview.streak.next.threshold }}</div>
                            <div class="next-label">
                                {{ overview.streak.next.threshold - overview.streak.progress }} remaining
                            </div>
                            <div class="next-progress">
                                <div class="progress-bar-mini">
                                    <div
                                        class="progress-fill streak-fill"
                                        :style="{ width: (overview.streak?.percentage || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="achievement-card streak-card"
                            v-for="achievement in sortedStreak"
                            :key="achievement.id"
                        >
                            <div class="achievement-value">{{ achievement.threshold }}</div>
                            <div class="achievement-label">Unlocked on</div>
                            <div class="achievement-date">{{ formatDate(achievement.created_at) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Total Tags Row -->
                <div class="achievement-row" v-if="overview.total_objects">
                    <div class="row-header">
                        <div class="row-title">
                            <div class="row-icon icon-tags">
                                <svg viewBox="0 0 24 24">
                                    <path
                                        d="M5.5 7A1.5 1.5 0 1 0 4 5.5 1.5 1.5 0 0 0 5.5 7zm15.91 4.58-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 .59 1.42l9 9a2 2 0 0 0 2.82 0l7-7a2 2 0 0 0 0-2.84zM13 20.41 4 11.41V4h7v.41l9 9z"
                                        fill="currentColor"
                                    />
                                </svg>
                            </div>
                            <h3>Total Tags</h3>
                            <span class="row-progress">{{
                                overview.total_objects?.progress?.toLocaleString() || 0
                            }}</span>
                        </div>
                    </div>
                    <div class="achievements-scroll">
                        <div class="next-achievement tags-next" v-if="overview.total_objects?.next">
                            <svg class="lock-icon-mini" viewBox="0 0 24 24">
                                <path
                                    d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                    fill="currentColor"
                                />
                            </svg>
                            <div class="next-value">{{ overview.total_objects.next.threshold.toLocaleString() }}</div>
                            <div class="next-label">
                                {{
                                    (
                                        overview.total_objects.next.threshold - overview.total_objects.progress
                                    ).toLocaleString()
                                }}
                                remaining
                            </div>
                            <div class="next-progress">
                                <div class="progress-bar-mini">
                                    <div
                                        class="progress-fill tags-fill"
                                        :style="{ width: (overview.total_objects?.percentage || 0) + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="achievement-card tags-card"
                            v-for="achievement in sortedTotalObjects"
                            :key="achievement.id"
                        >
                            <div class="achievement-value">{{ achievement.threshold.toLocaleString() }}</div>
                            <div class="achievement-label">Unlocked on</div>
                            <div class="achievement-date">{{ formatDate(achievement.created_at) }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Categories Section -->
            <section class="categories-section">
                <h2 class="section-title">Categories</h2>

                <!-- Search -->
                <div class="controls">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search categories or objects..."
                        class="search-input"
                    />
                </div>

                <!-- Categories Grid -->
                <div
                    class="categories-grid"
                    ref="categoriesGrid"
                    @mousedown="startDrag"
                    @mousemove="drag"
                    @mouseup="endDrag"
                    @mouseleave="endDrag"
                    @touchstart="startDrag"
                    @touchmove="drag"
                    @touchend="endDrag"
                >
                    <div
                        v-for="category in filteredCategories"
                        :key="category.id"
                        class="category-card"
                        @click="
                            !isDragging &&
                            !(category.achievement.progress === 0 && category.achievement.next_threshold === 1) &&
                            (selectedCategory = selectedCategory?.id === category.id ? null : category)
                        "
                        :class="{
                            selected: selectedCategory?.id === category.id,
                            locked: category.achievement.progress === 0 && category.achievement.next_threshold === 1,
                        }"
                    >
                        <div class="category-icon" :class="`icon-${category.key}`">
                            <component :is="getCategoryIcon(category.key)" />
                        </div>
                        <h3 class="category-title">{{ formatCategoryName(category.key) }}</h3>

                        <div class="category-stats">
                            <div class="stat-item">
                                <span class="stat-number">{{ category.achievement.progress.toLocaleString() }}</span>
                                <span class="stat-label">items</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">{{ category.objects.length }}</span>
                                <span class="stat-label">types</span>
                            </div>
                        </div>

                        <div class="category-progress-bar">
                            <div
                                class="category-progress-fill"
                                :style="{ width: Math.min(100, (category.achievement.progress / 100) * 100) + '%' }"
                            ></div>
                        </div>

                        <!-- Locked Overlay -->
                        <div
                            v-if="category.achievement.progress === 0 && category.achievement.next_threshold === 1"
                            class="locked-overlay"
                        >
                            <svg class="lock-icon" viewBox="0 0 24 24">
                                <path
                                    d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                    fill="currentColor"
                                />
                            </svg>
                            <span class="lock-text">Locked</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Selected Category Detail -->
            <section v-if="selectedCategory" class="category-detail">
                <div class="detail-header">
                    <h2>{{ formatCategoryName(selectedCategory.key) }} Objects</h2>
                    <div class="detail-controls">
                        <select v-model="sortBy" class="sort-select">
                            <option value="alphabetical">Alphabetical</option>
                            <option value="total">Total Score</option>
                            <option value="next">Next to Unlock</option>
                            <option value="achievements">Total Achievements</option>
                        </select>
                        <button @click="selectedCategory = null" class="close-btn">
                            <svg viewBox="0 0 24 24" width="24" height="24">
                                <path
                                    d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"
                                    fill="currentColor"
                                />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="objects-list">
                    <div
                        v-for="object in sortedObjects"
                        :key="object.id"
                        class="object-row"
                        :class="{
                            locked: object.achievement.progress === 0 && object.achievement.next_threshold === 1,
                        }"
                    >
                        <div class="object-info">
                            <h4 class="object-name">{{ object.name }}</h4>
                            <span class="object-total">{{ object.achievement.progress.toLocaleString() }} total</span>
                        </div>

                        <div class="object-achievements">
                            <div class="next-achievement mini" v-if="object.achievement.next">
                                <svg class="lock-icon-mini" viewBox="0 0 24 24">
                                    <path
                                        d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                        fill="currentColor"
                                    />
                                </svg>
                                <div class="next-value">{{ object.achievement.next_threshold }}</div>
                                <div class="next-label">
                                    {{ object.achievement.next_threshold - object.achievement.progress }} remaining
                                </div>
                                <div class="next-progress">
                                    <div class="progress-bar-mini">
                                        <div
                                            class="progress-fill"
                                            :style="{ width: object.achievement.percentage + '%' }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="achievement-card mini"
                                v-for="ach in sortedObjectAchievements(object)"
                                :key="ach.id"
                            >
                                <div class="achievement-value">{{ ach.threshold }}</div>
                                <div class="achievement-label">Unlocked on</div>
                                <div class="achievement-date">{{ formatDate(ach.created_at) }}</div>
                            </div>
                        </div>

                        <!-- Locked Overlay -->
                        <div
                            v-if="object.achievement.progress === 0 && object.achievement.next_threshold === 1"
                            class="locked-overlay-row"
                        >
                            <svg class="lock-icon" viewBox="0 0 24 24">
                                <path
                                    d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z"
                                    fill="currentColor"
                                />
                            </svg>
                            <span class="lock-text">Locked</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

// State
const loading = ref(true);
const error = ref(null);
const data = ref(null);
const searchQuery = ref('');
const selectedCategory = ref(null);
const sortBy = ref('alphabetical');

// Drag state
const categoriesGrid = ref(null);
const isDragging = ref(false);
const startX = ref(0);
const scrollLeft = ref(0);

// Computed
const overview = computed(() => data.value?.overview || {});
const categories = computed(() => data.value?.categories || []);
const summary = computed(() => data.value?.summary || null);

const sortedUploads = computed(() => {
    if (!overview.value.uploads?.unlocked) return [];
    return [...overview.value.uploads.unlocked].sort((a, b) => b.threshold - a.threshold);
});

const sortedStreak = computed(() => {
    if (!overview.value.streak?.unlocked) return [];
    return [...overview.value.streak.unlocked].sort((a, b) => b.threshold - a.threshold);
});

const sortedTotalObjects = computed(() => {
    if (!overview.value.total_objects?.unlocked) return [];
    return [...overview.value.total_objects.unlocked].sort((a, b) => b.threshold - a.threshold);
});

const filteredCategories = computed(() => {
    if (!searchQuery.value) return categories.value;

    const query = searchQuery.value.toLowerCase();
    return categories.value.filter((category) => {
        // Check category name
        if (formatCategoryName(category.key).toLowerCase().includes(query)) return true;

        // Check object names
        return category.objects.some((obj) => obj.name.toLowerCase().includes(query));
    });
});

const sortedObjects = computed(() => {
    if (!selectedCategory.value) return [];

    const objects = [...selectedCategory.value.objects];

    switch (sortBy.value) {
        case 'alphabetical':
            return objects.sort((a, b) => a.name.localeCompare(b.name));
        case 'total':
            return objects.sort((a, b) => b.achievement.progress - a.achievement.progress);
        case 'next':
            return objects.sort((a, b) => {
                const aNext = a.achievement.next_threshold || Infinity;
                const bNext = b.achievement.next_threshold || Infinity;
                return aNext - bNext;
            });
        case 'achievements':
            return objects.sort((a, b) => b.achievement.unlocked.length - a.achievement.unlocked.length);
        default:
            return objects;
    }
});

// Methods
const fetchAchievements = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get('/api/achievements');
        data.value = response.data;
    } catch (err) {
        error.value = 'Failed to load achievements. Please try again.';
        console.error('Error fetching achievements:', err);
    } finally {
        loading.value = false;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

const sortedObjectAchievements = (object) => {
    return [...object.achievement.unlocked].sort((a, b) => b.threshold - a.threshold);
};

// Drag methods
const startDrag = (e) => {
    isDragging.value = true;
    const pageX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
    startX.value = pageX - categoriesGrid.value.offsetLeft;
    scrollLeft.value = categoriesGrid.value.scrollLeft;
    categoriesGrid.value.style.cursor = 'grabbing';
};

const drag = (e) => {
    if (!isDragging.value) return;
    e.preventDefault();
    const pageX = e.type.includes('mouse') ? e.pageX : e.touches[0].pageX;
    const x = pageX - categoriesGrid.value.offsetLeft;
    const walk = (x - startX.value) * 2;
    categoriesGrid.value.scrollLeft = scrollLeft.value - walk;
};

const endDrag = () => {
    isDragging.value = false;
    if (categoriesGrid.value) {
        categoriesGrid.value.style.cursor = 'grab';
    }
};

const formatCategoryName = (key) => {
    const names = {
        smoking: 'Smoking',
        food: 'Food',
        coffee: 'Coffee',
        alcohol: 'Alcohol',
        softdrinks: 'Soft Drinks',
        sanitary: 'Sanitary',
        coastal: 'Coastal',
        dumping: 'Dumping',
        industrial: 'Industrial',
        brands: 'Brands',
        dogshit: 'Dog Waste',
        art: 'Art',
        material: 'Materials',
        other: 'Other',
        automobile: 'Automobile',
        electronics: 'Electronics',
        pets: 'Pets',
        stationery: 'Stationery',
    };
    return names[key] || key;
};

const getCategoryIcon = (key) => {
    const icons = {
        smoking: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M2 16h15v3H2v-3zm18.5 0H22v3h-1.5v-3zM18 16h1.5v3H18v-3zm.85-8.27c.62-.61 1-1.45 1-2.38C19.85 3.5 18.35 2 16.5 2v1.5c1.02 0 1.85.83 1.85 1.85S17.52 7.2 16.5 7.2v1.5c2.24 0 4 1.83 4 4.07V15H22v-2.24c0-2.22-1.28-4.14-3.15-5.03zm-2.82 2.47H14.5c-1.02 0-1.85-.98-1.85-2s.83-1.75 1.85-1.75v-1.5a3.35 3.35 0 0 0-3.35 3.35 3.35 3.35 0 0 0 3.35 3.35h1.53c1.05 0 1.97.74 1.97 2.05V15h1.5v-2.64c0-1.81-1.6-3.16-3.47-3.16z" fill="currentColor"/></svg>',
        },
        food: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7zm5-3v8h2.5v8H21V2c-2.76 0-5 2.24-5 4z" fill="currentColor"/></svg>',
        },
        coffee: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4v-2z" fill="currentColor"/></svg>',
        },
        alcohol: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M3 14c0 1.3.84 2.4 2 2.82V20H3v2h6v-2H7v-3.18C8.16 16.4 9 15.3 9 14V6H3v8zm2-6h2v3H5V8zm15.64-4.54L19.21 2l-1.43 1.41L15.72 5.5c-.32.32-.5.75-.5 1.21V14c0 1.3.84 2.4 2 2.82V20h-2v2h6v-2h-2v-3.18c1.16-.41 2-1.51 2-2.82V6.71c0-.46-.18-.89-.5-1.21l-1.08-1.04zM19 14c0 .55-.45 1-1 1s-1-.45-1-1v-4h2v4z" fill="currentColor"/></svg>',
        },
        softdrinks: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M20 2h-2l-1.2 1.8L15.5 2h-2l2.5 3.7V12c0 1.1-.9 2-2 2h-4c-1.1 0-2-.9-2-2V5.7L10.5 2h-2L7.2 3.8 6 2H4l2.5 3.7V12c0 2.2 1.8 4 4 4v4H8v2h8v-2h-2.5v-4c2.2 0 4-1.8 4-4V5.7L20 2z" fill="currentColor"/></svg>',
        },
        sanitary: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M15.5 6.5C15.5 5.66 17 4 17 4s1.5 1.66 1.5 2.5c0 .83-.67 1.5-1.5 1.5s-1.5-.67-1.5-1.5zM12 12v8c0 .5-.2 1-.6 1.4-.4.4-.9.6-1.4.6H8c-.5 0-1-.2-1.4-.6-.4-.4-.6-.9-.6-1.4v-8c0-3.5 2.5-6.4 5.8-7l.6 1.9c-2.4.5-4.4 2.5-4.4 5.1h4z" fill="currentColor"/></svg>',
        },
        coastal: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M17 16.99c-1.35 0-2.2.42-2.95.8-.65.33-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.33-1.17.6-2.05.6v1.95c1.35 0 2.2-.42 2.95-.8.65-.33 1.17-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.42 2.95-.8c.65-.33 1.18-.6 2.05-.6.9 0 1.4.25 2.05.6.75.38 1.58.8 2.95.8v-1.95c-.9 0-1.4-.25-2.05-.6-.75-.38-1.6-.8-2.95-.8zm0-4.45c-1.35 0-2.2.42-2.95.8-.65.32-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.32-1.17.6-2.05.6v1.95c1.35 0 2.2-.42 2.95-.8.65-.35 1.15-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.42 2.95-.8c.65-.35 1.15-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.58.8 2.95.8v-1.95c-.9 0-1.4-.25-2.05-.6-.75-.38-1.6-.8-2.95-.8zm2.95-8.08c-.75-.38-1.58-.8-2.95-.8s-2.2.42-2.95.8c-.65.32-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.37-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.33-1.17.6-2.05.6v1.93c1.35 0 2.2-.43 2.95-.8.65-.33 1.17-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.43 2.95-.8c.65-.32 1.18-.6 2.05-.6.9 0 1.4.25 2.05.6.75.38 1.58.8 2.95.8V5.04c-.9 0-1.4-.25-2.05-.58z" fill="currentColor"/></svg>',
        },
        dumping: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12z" fill="currentColor"/></svg>',
        },
        industrial: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6zm9 14H6V10h12v10zm-6-3c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2z" fill="currentColor"/></svg>',
        },
        brands: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58.55 0 1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41 0-.55-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z" fill="currentColor"/></svg>',
        },
        dogshit: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/></svg>',
        },
        art: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10c.19 0 .34-.16.34-.34 0-.09-.04-.17-.09-.25-.27-.44-.44-1.03-.44-1.67 0-.79.38-1.79 1.22-1.79h1.44c3.04 0 5.5-2.46 5.5-5.5C20 7.81 16.19 2 12 2zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 8 6.5 8 8 8.67 8 9.5 7.33 11 6.5 11zm3-4C8.67 7 8 6.33 8 5.5S8.67 4 9.5 4s1.5.67 1.5 1.5S10.33 7 9.5 7zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 4 14.5 4s1.5.67 1.5 1.5S15.33 7 14.5 7zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 8 17.5 8s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z" fill="currentColor"/></svg>',
        },
        material: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M19.23 12.04l-1.52-.11-.3-1.5C16.89 7.86 14.62 6 12 6s-4.89 1.86-5.4 4.43l-.3 1.5-1.53.11C2.57 12.24 1 14.05 1 16.22 1 18.51 2.77 20 5.06 20h13.17c2.11 0 3.77-1.49 3.77-3.78 0-2.06-1.4-3.98-3.77-4.18zM19.23 18H5.06c-1.18 0-2.06-.68-2.06-1.78 0-1.09.77-2.01 1.99-2.11l2.36-.18.57-2.3C8.17 10.31 9.93 8 12 8s3.83 2.31 4.08 3.64l.57 2.29 2.36.17c1.11.1 1.99.93 1.99 2.12 0 1.1-.88 1.78-2.77 1.78z" fill="currentColor"/></svg>',
        },
        other: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM10 17l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/></svg>',
        },
        automobile: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z" fill="currentColor"/></svg>',
        },
        electronics: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M16 1H8C6.34 1 5 2.34 5 4v16c0 1.66 1.34 3 3 3h8c1.66 0 3-1.34 3-3V4c0-1.66-1.34-3-3-3zm-2 20h-4v-1h4v1zm3.25-3H6.75V4h10.5v14z" fill="currentColor"/></svg>',
        },
        pets: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M4.5 10.5C4.5 12.39 5.61 14 7.5 14c1.19 0 2.34-.71 3.12-1.8L12 10.8l1.38 1.4c.78 1.09 1.93 1.8 3.12 1.8 1.89 0 3-1.61 3-3.5 0-1.89-1.11-3.5-3-3.5-1.19 0-2.34.71-3.12 1.8L12 10.2l-1.38-1.4C9.84 7.71 8.69 7 7.5 7c-1.89 0-3 1.61-3 3.5zm7.5 4.5a5 5 0 0 0-5 5c0 .5.4 1 1 1h8c.6 0 1-.5 1-1a5 5 0 0 0-5-5z" fill="currentColor"/></svg>',
        },
        stationery: {
            template:
                '<svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>',
        },
    };
    return icons[key] || icons.other;
};

// Lifecycle
onMounted(() => {
    fetchAchievements();
});
</script>

<style scoped>
/* Dark Theme Container */
.achievements-container {
    margin: 0 auto;
    padding: 5em;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #0a0a0a;
    color: #e5e5e5;
    min-height: 100vh;
}

/* Header */
.achievements-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding-bottom: 24px;
    border-bottom: 2px solid #262626;
}

.title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 36px;
    font-weight: 700;
    color: #fff;
    margin: 0;
}

.title-icon {
    width: 40px;
    height: 40px;
    color: #fbbf24;
}

.summary-stats {
    display: flex;
    gap: 24px;
}

.stat-badge {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #fff;
}

.stat-label {
    display: block;
    font-size: 14px;
    color: #a3a3a3;
    margin-top: 4px;
}

/* Loading & Error States */
.loading-container,
.error-container {
    text-align: center;
    padding: 80px 20px;
    color: #e5e5e5;
}

.loading-spinner {
    width: 48px;
    height: 48px;
    margin: 0 auto 20px;
    border: 4px solid #262626;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.retry-btn {
    padding: 12px 24px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s;
}

.retry-btn:hover {
    background: #2563eb;
}

/* Achievement Rows */
.overview-section {
    margin-bottom: 48px;
}

.achievement-row {
    margin-bottom: 32px;
    background: #171717;
    border-radius: 16px;
    padding: 24px;
    border: 1px solid #262626;
}

.row-header {
    margin-bottom: 20px;
}

.row-title {
    display: flex;
    align-items: center;
    gap: 16px;
}

.row-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
}

.row-icon svg {
    width: 28px;
    height: 28px;
}

.row-title h3 {
    margin: 0;
    font-size: 20px;
    color: #fff;
    flex: 1;
}

.row-progress {
    font-size: 24px;
    font-weight: 600;
    color: #a3a3a3;
}

.achievements-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
}

.achievements-scroll::-webkit-scrollbar {
    height: 6px;
}

.achievements-scroll::-webkit-scrollbar-track {
    background: #262626;
    border-radius: 3px;
}

.achievements-scroll::-webkit-scrollbar-thumb {
    background: #404040;
    border-radius: 3px;
}

.achievement-card {
    flex: 0 0 auto;
    background: #262626;
    border: 1px solid #404040;
    border-radius: 12px;
    padding: 16px 20px;
    text-align: center;
    min-width: 120px;
}

.achievement-card.streak-card {
    background: #1a1811;
    border-color: #4a3f26;
}

.achievement-card.tags-card {
    background: #1a1620;
    border-color: #3d2f5c;
}

.achievement-value {
    font-size: 20px;
    font-weight: 700;
    color: #fbbf24;
    margin-bottom: 4px;
}

.achievement-label {
    font-size: 10px;
    color: #525252;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.achievement-date {
    font-size: 12px;
    color: #737373;
}

.lock-icon-mini {
    width: 20px;
    height: 20px;
    color: #737373;
    margin-bottom: 4px;
}

.next-achievement {
    flex: 0 0 auto;
    background: rgba(59, 130, 246, 0.1);
    border: 2px dashed #3b82f6;
    border-radius: 12px;
    padding: 16px 20px;
    text-align: center;
    min-width: 120px;
}

.next-achievement.streak-next {
    background: rgba(245, 158, 11, 0.1);
    border-color: #f59e0b;
}

.next-achievement.tags-next {
    background: rgba(139, 92, 246, 0.1);
    border-color: #8b5cf6;
}

.next-value {
    font-size: 20px;
    font-weight: 700;
    color: #e5e5e5;
    margin-bottom: 4px;
}

.next-label {
    font-size: 12px;
    color: #a3a3a3;
    margin-bottom: 8px;
}

.next-progress {
    margin-top: 8px;
}

.progress-bar-mini {
    height: 4px;
    background: #262626;
    border-radius: 2px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #3b82f6;
    border-radius: 2px;
    transition: width 0.3s ease;
}

.streak-fill {
    background: #f59e0b;
}

.tags-fill {
    background: #8b5cf6;
}

/* Icon Colors */
.icon-uploads {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.icon-streak {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.icon-tags {
    background: rgba(139, 92, 246, 0.2);
    color: #8b5cf6;
}

/* Categories Section */
.categories-section {
    margin-top: 48px;
}

.section-title {
    font-size: 28px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 24px;
}

.controls {
    margin-bottom: 24px;
}

.search-input {
    width: 100%;
    max-width: 400px;
    padding: 12px 20px;
    background: #171717;
    border: 2px solid #262626;
    border-radius: 10px;
    font-size: 16px;
    color: #e5e5e5;
    transition: border-color 0.2s;
}

.search-input::placeholder {
    color: #737373;
}

.search-input:focus {
    outline: none;
    border-color: #3b82f6;
    background: #1a1a1a;
}

/* Categories Grid */
.categories-grid {
    display: flex;
    gap: 16px;
    overflow-x: auto;
    padding: 4px 0 20px;
    margin: 0 -20px;
    padding-left: 20px;
    padding-right: 20px;
    scroll-behavior: smooth;
    cursor: grab;
    user-select: none;
}

.categories-grid::-webkit-scrollbar {
    height: 8px;
}

.categories-grid::-webkit-scrollbar-track {
    background: #171717;
    border-radius: 4px;
}

.categories-grid::-webkit-scrollbar-thumb {
    background: #404040;
    border-radius: 4px;
}

.category-card {
    flex: 0 0 200px;
    background: #171717;
    border-radius: 16px;
    padding: 24px;
    border: 2px solid #262626;
    transition: all 0.2s;
    cursor: pointer;
    position: relative;
}

.category-card:hover {
    transform: translateY(-2px);
    border-color: #404040;
}

.category-card.selected {
    border-color: #3b82f6;
    background: #1a1f2e;
}

.category-card.locked {
    opacity: 0.5;
    cursor: not-allowed;
}

.locked-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(10, 10, 10, 0.8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
}

.lock-icon {
    width: 32px;
    height: 32px;
    color: #737373;
    margin-bottom: 8px;
}

.lock-text {
    font-size: 14px;
    font-weight: 600;
    color: #737373;
}

.category-icon {
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    margin-bottom: 16px;
}

.category-icon svg {
    width: 32px;
    height: 32px;
}

/* Category Icon Colors - Dark Theme */
.icon-smoking {
    background: rgba(220, 38, 38, 0.2);
    color: #ef4444;
}
.icon-food {
    background: rgba(234, 88, 12, 0.2);
    color: #f97316;
}
.icon-coffee {
    background: rgba(120, 113, 108, 0.2);
    color: #a8a29e;
}
.icon-alcohol {
    background: rgba(219, 39, 119, 0.2);
    color: #ec4899;
}
.icon-softdrinks {
    background: rgba(8, 145, 178, 0.2);
    color: #06b6d4;
}
.icon-sanitary {
    background: rgba(99, 102, 241, 0.2);
    color: #818cf8;
}
.icon-coastal {
    background: rgba(13, 148, 136, 0.2);
    color: #14b8a6;
}
.icon-dumping {
    background: rgba(185, 28, 28, 0.2);
    color: #ef4444;
}
.icon-industrial {
    background: rgba(82, 82, 91, 0.2);
    color: #a1a1aa;
}
.icon-brands {
    background: rgba(147, 51, 234, 0.2);
    color: #a855f7;
}
.icon-dogshit {
    background: rgba(161, 98, 7, 0.2);
    color: #facc15;
}
.icon-art {
    background: rgba(236, 72, 153, 0.2);
    color: #f472b6;
}
.icon-material {
    background: rgba(75, 85, 99, 0.2);
    color: #9ca3af;
}
.icon-other {
    background: rgba(107, 114, 128, 0.2);
    color: #9ca3af;
}
.icon-automobile {
    background: rgba(37, 99, 235, 0.2);
    color: #60a5fa;
}
.icon-electronics {
    background: rgba(5, 150, 105, 0.2);
    color: #10b981;
}
.icon-pets {
    background: rgba(217, 119, 6, 0.2);
    color: #fbbf24;
}
.icon-stationery {
    background: rgba(124, 58, 237, 0.2);
    color: #a78bfa;
}

.category-title {
    font-size: 18px;
    font-weight: 600;
    color: #fff;
    margin: 0 0 16px 0;
}

.category-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 16px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #e5e5e5;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #737373;
    margin-top: 2px;
}

.category-progress-bar {
    height: 6px;
    background: #262626;
    border-radius: 3px;
    overflow: hidden;
}

.category-progress-fill {
    height: 100%;
    background: #10b981;
    border-radius: 3px;
    transition: width 0.3s ease;
}

/* Category Detail Section */
.category-detail {
    margin-top: 48px;
    background: #171717;
    border-radius: 16px;
    padding: 28px;
    border: 1px solid #262626;
}

.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}

.detail-header h2 {
    margin: 0;
    font-size: 24px;
    color: #fff;
}

.detail-controls {
    display: flex;
    align-items: center;
    gap: 16px;
}

.sort-select {
    padding: 8px 16px;
    background: #262626;
    border: 1px solid #404040;
    border-radius: 8px;
    color: #e5e5e5;
    font-size: 14px;
    cursor: pointer;
}

.sort-select:focus {
    outline: none;
    border-color: #3b82f6;
}

.close-btn {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    color: #737373;
    transition: color 0.2s;
    border-radius: 8px;
}

.close-btn:hover {
    color: #e5e5e5;
    background: #262626;
}

/* Objects List */
.objects-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.object-row {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #0f0f0f;
    border: 1px solid #262626;
    border-radius: 12px;
    padding: 16px 20px;
    position: relative;
}

.object-row.locked {
    opacity: 0.5;
}

.object-info {
    flex: 0 0 200px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.object-name {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    color: #fff;
}

.object-total {
    font-size: 14px;
    color: #737373;
}

.object-achievements {
    flex: 1;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
}

.object-achievements::-webkit-scrollbar {
    height: 4px;
}

.object-achievements::-webkit-scrollbar-track {
    background: #262626;
    border-radius: 2px;
}

.object-achievements::-webkit-scrollbar-thumb {
    background: #404040;
    border-radius: 2px;
}

.achievement-card.mini {
    flex: 0 0 auto;
    min-width: 90px;
    padding: 12px;
}

.achievement-card.mini .achievement-value {
    font-size: 16px;
}

.achievement-card.mini .achievement-label {
    font-size: 9px;
}

.achievement-card.mini .achievement-date {
    font-size: 11px;
}

.next-achievement.mini {
    flex: 0 0 auto;
    min-width: 90px;
    padding: 12px;
}

.next-achievement.mini .lock-icon-mini {
    width: 16px;
    height: 16px;
}

.next-achievement.mini .next-value {
    font-size: 16px;
}

.next-achievement.mini .next-label {
    font-size: 11px;
}

.locked-overlay-row {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(10, 10, 10, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    border-radius: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .achievements-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }

    .object-row {
        flex-direction: column;
        align-items: flex-start;
    }

    .object-info {
        flex: 1;
        width: 100%;
    }

    .object-achievements {
        width: 100%;
    }

    .detail-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }

    .detail-controls {
        width: 100%;
        justify-content: space-between;
    }
}
</style>
