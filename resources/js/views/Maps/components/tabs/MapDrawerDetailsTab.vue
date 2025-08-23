<template>
    <div class="details-tab">
        <!-- Section D: Top Litter Objects -->
        <section class="stats-section">
            <div class="section-header" @click="toggleSection('objects')">
                <h3>
                    <i :class="['fas', expandedSections.objects ? 'fa-chevron-down' : 'fa-chevron-right']"></i>
                    🗑️ Top Litter Objects
                </h3>
            </div>
            <div v-show="expandedSections.objects" class="section-content">
                <div class="objects-list">
                    <div
                        v-for="(obj, idx) in processedObjects"
                        :key="obj.key"
                        class="object-item"
                        :class="{ highlighted: highlightedObject === obj.key }"
                        @mouseenter="handleObjectHover(obj)"
                        @mouseleave="handleObjectHover(null)"
                    >
                        <span class="object-rank" :style="`color: ${obj.color}`">#{{ idx + 1 }}</span>
                        <div class="object-info">
                            <span
                                class="object-category"
                                :style="`background-color: ${obj.color}20; color: ${obj.color}`"
                            >
                                {{ obj.category }}
                            </span>
                            <span class="object-arrow">→</span>
                            <span class="object-name">{{ obj.objectKey }}</span>
                        </div>
                        <div class="object-stats">
                            <span class="object-count">{{ helper.formatNumber(obj.count) }}</span>
                            <span class="object-percentage">{{
                                helper.formatPercentage(obj.count, processedData.totalObjects)
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section E: Top Brands -->
        <section class="stats-section">
            <div class="section-header" @click="toggleSection('brands')">
                <h3>
                    <i :class="['fas', expandedSections.brands ? 'fa-chevron-down' : 'fa-chevron-right']"></i>
                    ©️ Top Brands
                </h3>
            </div>
            <div v-show="expandedSections.brands" class="section-content">
                <div class="brands-grid">
                    <div v-for="(brand, idx) in processedData.topBrands" :key="brand.key" class="brand-card">
                        <div class="brand-rank">#{{ idx + 1 }}</div>
                        <div class="brand-name">{{ brand.key }}</div>
                        <div class="brand-count">{{ helper.formatNumber(brand.count) }}</div>
                        <div class="brand-percentage">
                            {{ helper.formatPercentage(brand.count, processedData.totalBrands) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section F: Top Materials -->
        <section class="stats-section">
            <div class="section-header" @click="toggleSection('materials')">
                <h3>
                    <i :class="['fas', expandedSections.materials ? 'fa-chevron-down' : 'fa-chevron-right']"></i>
                    ♻️ Top Materials
                </h3>
            </div>
            <div v-show="expandedSections.materials" class="section-content">
                <div class="materials-list">
                    <div
                        v-for="material in processedData.materialsWithPercentages"
                        :key="material.key"
                        class="material-item"
                    >
                        <span class="material-icon">{{ material.icon || '🗑️' }}</span>
                        <span class="material-name">{{ material.key }}</span>
                        <div class="material-bar-wrapper">
                            <div class="material-bar" :style="`width: ${material.percentage}%`" />
                            <span class="material-count">{{ helper.formatNumber(material.count) }}</span>
                            <span class="material-percentage">{{ material.formattedPercentage }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section G: Top Contributors -->
        <section class="stats-section">
            <div class="section-header" @click="toggleSection('contributors')">
                <h3>
                    <i :class="['fas', expandedSections.contributors ? 'fa-chevron-down' : 'fa-chevron-right']"></i>
                    🏆 Top Contributors
                </h3>
            </div>
            <div v-show="expandedSections.contributors" class="section-content">
                <div class="contributors-list">
                    <div
                        v-for="(user, idx) in statsData.top_contributors"
                        :key="user.username"
                        class="contributor-item"
                    >
                        <div class="contributor-rank">
                            <template v-if="idx === 0">🥇</template>
                            <template v-else-if="idx === 1">🥈</template>
                            <template v-else-if="idx === 2">🥉</template>
                            <template v-else>#{{ idx + 1 }}</template>
                        </div>
                        <div class="contributor-info">
                            <div class="contributor-name">
                                {{ user.name || user.username }}
                                <span v-if="user.username && user.name" class="username">@{{ user.username }}</span>
                            </div>
                            <div class="contributor-stats">
                                <span>{{ helper.formatNumber(user.photo_count) }} photos</span>
                                <span class="separator">•</span>
                                <span>{{ helper.formatNumber(user.total_litter) }} items</span>
                                <span class="separator">•</span>
                                <span class="contributor-period">
                                    {{ helper.getContributorPeriod(user.first_contribution, user.last_contribution) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import MapDrawerHelper from '../../helpers/mapDrawerHelper.js';
import { Category } from '../../helpers/Category.js';

// Initialize helper
const helper = MapDrawerHelper;

// Props
const props = defineProps({
    statsData: {
        type: Object,
        required: true,
    },
    processedData: {
        type: Object,
        required: true,
    },
});

// Emits
const emit = defineEmits(['highlight-object', 'highlight-category']);

// State
const highlightedObject = ref(null);
const expandedSections = ref({
    objects: true, // First section open by default
    brands: false,
    materials: false,
    contributors: false,
});

// Computed
const processedObjects = computed(() => {
    if (!props.processedData.topObjects) return [];

    return props.processedData.topObjects.map((obj) => {
        // Check if we have a category field directly on the object
        let categoryKey = obj.category;
        let objectKey = obj.key;

        // If no category field, try to parse from the key
        if (!categoryKey && obj.key.includes('.')) {
            const parsed = helper.parseObjectKey(obj.key);
            categoryKey = parsed.category;
            objectKey = parsed.name;
        }

        // If still no category, we need to determine it from the object type
        // This mapping should match your backend data structure
        if (!categoryKey) {
            // Common object to category mappings
            const objectToCategoryMap = {
                butts: 'smoking',
                cigarette_butts: 'smoking',
                cigarette_box: 'smoking',
                lighters: 'smoking',
                vape: 'smoking',
                wrapper: 'food',
                paper: 'food',
                packaging: 'food',
                bottle: 'alcohol',
                can: 'alcohol',
                coffee_cup: 'coffee',
                // Add more mappings as needed
            };

            categoryKey = objectToCategoryMap[objectKey] || 'other';
            console.log(`Mapped object "${objectKey}" to category "${categoryKey}"`);
        }

        console.log(`Processing object: key="${obj.key}", category="${categoryKey}", object="${objectKey}"`);

        return {
            ...obj,
            category: categoryKey,
            objectKey: objectKey,
            color: Category.getColor(categoryKey),
            fullKey: obj.key,
        };
    });
});

// Methods
const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section];
};

const handleObjectHover = (object) => {
    if (object) {
        highlightedObject.value = object.key;
        console.log('Emitting highlight-object:', {
            category: object.category,
            objectKey: object.objectKey,
            fullKey: object.fullKey,
        });
        // Emit both the category and the full object key
        emit('highlight-object', {
            category: object.category,
            objectKey: object.objectKey,
            fullKey: object.fullKey,
        });
    } else {
        highlightedObject.value = null;
        emit('highlight-object', null);
    }
};
</script>

<style scoped>
.details-tab {
    width: 100%;
    color: white; /* Default text color */
}

/* Section Headers - Collapsible */
.section-header {
    cursor: pointer;
    user-select: none;
    padding: 8px;
    margin: -8px -8px 8px -8px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.section-header:hover {
    background: rgba(255, 255, 255, 0.05);
}

.section-header h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.section-header i {
    font-size: 12px;
    transition: transform 0.2s ease;
}

.section-content {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Objects List */
.objects-list {
    display: grid;
    gap: 8px;
}

.object-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.object-item:hover,
.object-item.highlighted {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
    border-color: rgba(255, 255, 255, 0.3);
}

.object-rank {
    font-weight: 700;
    min-width: 32px;
}

.object-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.object-category {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: lowercase;
}

.object-arrow {
    color: rgba(255, 255, 255, 0.3);
}

.object-name {
    font-weight: 600;
    color: white;
}

.object-stats {
    display: flex;
    gap: 12px;
    align-items: center;
}

.object-count {
    font-weight: 700;
    font-size: 16px;
    color: white;
}

.object-percentage {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

/* Brands Grid */
.brands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
}

.brand-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    transition: all 0.2s ease;
}

.brand-card:hover {
    transform: translateY(-4px);
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.brand-rank {
    font-size: 12px;
    color: #4ade80;
    font-weight: 700;
    margin-bottom: 8px;
}

.brand-name {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 8px;
    min-height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.brand-count {
    font-size: 20px;
    font-weight: 700;
    color: white;
    margin-bottom: 4px;
}

.brand-percentage {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

/* Materials List */
.materials-list {
    display: grid;
    gap: 12px;
}

.material-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.material-item:hover {
    background: rgba(255, 255, 255, 0.15);
}

.material-icon {
    font-size: 20px;
}

.material-name {
    font-weight: 600;
    font-size: 14px;
    min-width: 100px;
    color: white;
}

.material-bar-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 12px;
}

.material-bar {
    height: 8px;
    background: linear-gradient(90deg, #14d145, #12b83d);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.material-count {
    font-weight: 700;
    font-size: 14px;
    color: white;
}

.material-percentage {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

/* Contributors List */
.contributors-list {
    display: grid;
    gap: 12px;
}

.contributor-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    transition: all 0.2s ease;
}

.contributor-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
}

.contributor-rank {
    font-size: 24px;
    min-width: 40px;
    text-align: center;
    color: white;
}

.contributor-info {
    flex: 1;
}

.contributor-name {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
    color: white;
}

.username {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
    font-weight: 400;
}

.contributor-stats {
    display: flex;
    gap: 8px;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.7);
}

.separator {
    color: rgba(255, 255, 255, 0.3);
}

.contributor-period {
    color: rgba(255, 255, 255, 0.6);
}
</style>
