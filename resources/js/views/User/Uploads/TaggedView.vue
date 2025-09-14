<template>
    <div class="tagged-view">
        <!-- Summary Stats -->
        <div class="stats-summary">
            <div class="stat">
                <span class="stat-label">Total Objects</span>
                <span class="stat-value">{{ stats.totalObjects }}</span>
            </div>
            <div class="stat">
                <span class="stat-label">Total Tags</span>
                <span class="stat-value">{{ stats.totalTags }}</span>
            </div>
            <div class="stat" v-if="stats.totalMaterials > 0">
                <span class="stat-label">Total Materials</span>
                <span class="stat-value">{{ stats.totalMaterials }}</span>
            </div>
            <div class="stat" v-if="stats.totalBrands > 0">
                <span class="stat-label">Total Brands</span>
                <span class="stat-value">{{ stats.totalBrands }}</span>
            </div>
            <div class="stat" v-if="photo.xp">
                <span class="stat-label">XP Earned</span>
                <span class="stat-value">{{ photo.xp }}</span>
            </div>
        </div>

        <!-- Categories -->
        <div class="categories-grid">
            <div v-for="category in categorizedData" :key="category.name" class="category-card">
                <h3 class="category-title">{{ formatCategoryName(category.name) }}</h3>

                <div v-for="item in category.items" :key="item.id" class="object-item">
                    <div class="object-header">
                        <span class="object-name">{{ item.object?.key || 'unknown' }}</span>
                        <span class="object-qty">×{{ item.quantity }}</span>
                        <span v-if="item.picked_up" class="picked-up-badge">✓ Picked up</span>
                    </div>

                    <!-- Materials -->
                    <div v-if="item.materials.length > 0" class="sub-items">
                        <span class="sub-label">Materials:</span>
                        <span v-for="mat in item.materials" :key="mat.tag?.id" class="tag-chip material">
                            {{ mat.tag?.key || 'unknown' }} ({{ mat.quantity }})
                        </span>
                    </div>

                    <!-- Brands -->
                    <div v-if="item.brands.length > 0" class="sub-items">
                        <span class="sub-label">Brands:</span>
                        <span v-for="brand in item.brands" :key="brand.tag?.id" class="tag-chip brand">
                            {{ brand.tag?.key || 'unknown' }} ({{ brand.quantity }})
                        </span>
                    </div>

                    <!-- Custom Tags -->
                    <div v-if="item.customTags.length > 0" class="sub-items">
                        <span class="sub-label">Tags:</span>
                        <span v-for="custom in item.customTags" :key="custom.tag?.id" class="tag-chip custom">
                            {{ custom.tag?.key || 'unknown' }} ({{ custom.quantity }})
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    photo: {
        type: Object,
        required: true,
    },
});

// Process the new tags into categorized data
const categorizedData = computed(() => {
    if (!props.photo.new_tags || !Array.isArray(props.photo.new_tags)) return [];

    const categories = {};

    props.photo.new_tags.forEach((tag) => {
        const categoryKey = tag.category?.key || 'uncategorized';

        if (!categories[categoryKey]) {
            categories[categoryKey] = {
                name: categoryKey,
                items: [],
                totalQuantity: 0,
            };
        }

        const materials = [];
        const brands = [];
        const customTags = [];

        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'material') materials.push(extra);
                else if (extra.type === 'brand') brands.push(extra);
                else if (extra.type === 'custom_tag') customTags.push(extra);
            });
        }

        categories[categoryKey].items.push({
            ...tag,
            materials,
            brands,
            customTags,
        });

        categories[categoryKey].totalQuantity += tag.quantity || 0;
    });

    // Sort categories by total quantity, then sort items within each category
    return Object.values(categories)
        .sort((a, b) => b.totalQuantity - a.totalQuantity)
        .map((cat) => ({
            ...cat,
            items: cat.items.sort((a, b) => (b.quantity || 0) - (a.quantity || 0)),
        }));
});

// Calculate stats
const stats = computed(() => {
    const result = {
        totalObjects: 0,
        totalTags: 0,
        totalMaterials: 0,
        totalBrands: 0,
    };

    // Use summary totals if available
    if (props.photo.summary?.totals) {
        result.totalTags = props.photo.summary.totals.total_tags || 0;
        result.totalObjects = props.photo.summary.totals.total_objects || 0;
        result.totalMaterials = props.photo.summary.totals.materials || 0;
        result.totalBrands = props.photo.summary.totals.brands || 0;
    } else if (props.photo.new_tags) {
        // Calculate from new_tags
        props.photo.new_tags.forEach((tag) => {
            result.totalObjects += tag.quantity || 0;
            result.totalTags += tag.quantity || 0;

            if (tag.extra_tags) {
                tag.extra_tags.forEach((extra) => {
                    const qty = extra.quantity || 0;
                    result.totalTags += qty;

                    if (extra.type === 'material') result.totalMaterials += qty;
                    else if (extra.type === 'brand') result.totalBrands += qty;
                });
            }
        });
    }

    return result;
});

// Format category name
const formatCategoryName = (name) => {
    return name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ');
};
</script>

<style scoped>
.tagged-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    padding: 16px;
    background: #f8f8f8;
    border-radius: 8px;
}

.stat {
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-bottom: 4px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.categories-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.category-card {
    background: #f8f8f8;
    border-radius: 8px;
    padding: 16px;
}

.category-title {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #333;
    font-weight: 600;
}

.object-item {
    background: white;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
}

.object-item:last-child {
    margin-bottom: 0;
}

.object-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.object-name {
    font-weight: 600;
    color: #333;
    text-transform: capitalize;
}

.object-qty {
    color: #666;
    font-size: 14px;
}

.picked-up-badge {
    background: #4caf50;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
}

.sub-items {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.sub-label {
    font-size: 12px;
    color: #666;
    margin-right: 4px;
}

.tag-chip {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.tag-chip.material {
    background: #e8f5e9;
    color: #4caf50;
}

.tag-chip.brand {
    background: #f3e5f5;
    color: #9c27b0;
}

.tag-chip.custom {
    background: #fff3e0;
    color: #ff9800;
}

@media (max-width: 768px) {
    .stats-summary {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
