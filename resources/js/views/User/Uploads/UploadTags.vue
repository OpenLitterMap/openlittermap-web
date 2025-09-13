<template>
    <div class="modal-overlay" @click="emit('close')">
        <div class="modal-content" @click.stop>
            <div class="modal-header">
                <h2>Tag Details - Photo #{{ photo.id }}</h2>
                <button class="close-btn" @click="emit('close')">×</button>
            </div>

            <div class="modal-tabs">
                <button class="tab-btn" :class="{ active: activeTab === 'tagged' }" @click="activeTab = 'tagged'">
                    Tagged View
                </button>
                <button class="tab-btn" :class="{ active: activeTab === 'raw' }" @click="activeTab = 'raw'">
                    Raw Data
                </button>
            </div>

            <div class="modal-body">
                <!-- Tagged View -->
                <div v-if="activeTab === 'tagged'" class="tagged-view">
                    <!-- Summary Stats -->
                    <div class="stats-summary">
                        <div class="stat">
                            <span class="stat-label">Total Objects</span>
                            <span class="stat-value">{{ totalObjects }}</span>
                        </div>
                        <div class="stat">
                            <span class="stat-label">Total Tags</span>
                            <span class="stat-value">{{ totalTags }}</span>
                        </div>
                        <div class="stat" v-if="totalMaterials > 0">
                            <span class="stat-label">Total Materials</span>
                            <span class="stat-value">{{ totalMaterials }}</span>
                        </div>
                        <div class="stat" v-if="totalBrands > 0">
                            <span class="stat-label">Total Brands</span>
                            <span class="stat-value">{{ totalBrands }}</span>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="categories-grid">
                        <div v-for="category in categorizedData" :key="category.name" class="category-card">
                            <h3 class="category-title">{{ formatCategoryName(category.name) }}</h3>

                            <div v-for="item in category.items" :key="item.id" class="object-item">
                                <div class="object-header">
                                    <span class="object-name">{{ item.object.key }}</span>
                                    <span class="object-qty">×{{ item.quantity }}</span>
                                    <span v-if="item.picked_up" class="picked-up-badge">✓ Picked up</span>
                                </div>

                                <!-- Materials -->
                                <div v-if="item.materials.length > 0" class="sub-items">
                                    <span class="sub-label">Materials:</span>
                                    <span v-for="mat in item.materials" :key="mat.tag.id" class="tag-chip material">
                                        {{ mat.tag.key }} ({{ mat.quantity }})
                                    </span>
                                </div>

                                <!-- Brands -->
                                <div v-if="item.brands.length > 0" class="sub-items">
                                    <span class="sub-label">Brands:</span>
                                    <span v-for="brand in item.brands" :key="brand.tag.id" class="tag-chip brand">
                                        {{ brand.tag.key }} ({{ brand.quantity }})
                                    </span>
                                </div>

                                <!-- Custom Tags -->
                                <div v-if="item.customTags.length > 0" class="sub-items">
                                    <span class="sub-label">Tags:</span>
                                    <span
                                        v-for="custom in item.customTags"
                                        :key="custom.tag.id"
                                        class="tag-chip custom"
                                    >
                                        {{ custom.tag.key }} ({{ custom.quantity }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raw View -->
                <div v-if="activeTab === 'raw'" class="raw-view">
                    <div class="raw-section">
                        <div class="section-header">
                            <h3>Old Tags (v4)</h3>
                            <button
                                class="copy-btn"
                                @click="copyToClipboard(photo.old_tags, 'old')"
                                :title="copyStatus.old || 'Copy to clipboard'"
                            >
                                {{ copyStatus.old === 'Copied!' ? '✓' : '📋' }}
                            </button>
                        </div>
                        <pre class="raw-content">{{ JSON.stringify(photo.old_tags || {}, null, 2) }}</pre>
                    </div>

                    <div class="raw-section">
                        <div class="section-header">
                            <h3>New Tags (v5)</h3>
                            <button
                                class="copy-btn"
                                @click="copyToClipboard(photo.new_tags, 'new')"
                                :title="copyStatus.new || 'Copy to clipboard'"
                            >
                                {{ copyStatus.new === 'Copied!' ? '✓' : '📋' }}
                            </button>
                        </div>
                        <pre class="raw-content">{{ JSON.stringify(photo.new_tags || [], null, 2) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    photo: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close']);

const activeTab = ref('tagged');
const copyStatus = ref({ old: '', new: '' });

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

// Calculate totals
const totalObjects = computed(() => {
    if (!props.photo.new_tags) return 0;
    return props.photo.new_tags.reduce((sum, tag) => sum + (tag.quantity || 0), 0);
});

const totalTags = computed(() => {
    if (!props.photo.new_tags) return 0;
    let count = props.photo.new_tags.length;
    props.photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) count += tag.extra_tags.length;
    });
    return count;
});

const totalMaterials = computed(() => {
    if (!props.photo.new_tags) return 0;
    let count = 0;
    props.photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'material') count += extra.quantity || 0;
            });
        }
    });
    return count;
});

const totalBrands = computed(() => {
    if (!props.photo.new_tags) return 0;
    let count = 0;
    props.photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'brand') count += extra.quantity || 0;
            });
        }
    });
    return count;
});

// Format category name
const formatCategoryName = (name) => {
    return name.charAt(0).toUpperCase() + name.slice(1).replace(/_/g, ' ');
};

// Copy to clipboard
const copyToClipboard = async (data, type) => {
    try {
        await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
        copyStatus.value[type] = 'Copied!';
        setTimeout(() => {
            copyStatus.value[type] = '';
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
        copyStatus.value[type] = 'Failed';
        setTimeout(() => {
            copyStatus.value[type] = '';
        }, 2000);
    }
};

// Handle ESC key
const handleKeydown = (e) => {
    if (e.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
    document.body.style.overflow = 'hidden';
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
    document.body.style.overflow = '';
});
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 1000px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
    color: #333;
}

.close-btn {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    color: #666;
}

.close-btn:hover {
    background: #f0f0f0;
    color: #333;
}

.modal-tabs {
    display: flex;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f8f8;
}

.tab-btn {
    flex: 1;
    padding: 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    color: #666;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: #f0f0f0;
}

.tab-btn.active {
    background: white;
    color: #333;
    font-weight: 600;
    border-bottom: 2px solid #2196f3;
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

/* Tagged View Styles */
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

/* Raw View Styles */
.raw-view {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    height: 100%;
}

.raw-section {
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.section-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.copy-btn {
    background: #f0f0f0;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.copy-btn:hover {
    background: #e0e0e0;
}

.raw-content {
    background: #f5f5f5;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    margin: 0;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    line-height: 1.4;
    overflow: auto;
    flex: 1;
}

@media (max-width: 768px) {
    .raw-view {
        grid-template-columns: 1fr;
    }

    .stats-summary {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
