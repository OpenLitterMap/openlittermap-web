<template>
    <div class="flex flex-col gap-5">
        <!-- Summary Stats -->
        <div
            class="grid grid-cols-2 md:grid-cols-[repeat(auto-fit,minmax(120px,1fr))] gap-3 p-4 bg-gray-100 rounded-lg"
        >
            <div class="text-center">
                <span class="block text-xs text-gray-600 mb-1">Total Objects</span>
                <span class="block text-2xl font-bold text-gray-800">{{ stats.totalObjects }}</span>
            </div>
            <div class="text-center">
                <span class="block text-xs text-gray-600 mb-1">Total Tags</span>
                <span class="block text-2xl font-bold text-gray-800">{{ stats.totalTags }}</span>
            </div>
            <div v-if="stats.totalMaterials > 0" class="text-center">
                <span class="block text-xs text-gray-600 mb-1">Total Materials</span>
                <span class="block text-2xl font-bold text-gray-800">{{ stats.totalMaterials }}</span>
            </div>
            <div v-if="stats.totalBrands > 0" class="text-center">
                <span class="block text-xs text-gray-600 mb-1">Total Brands</span>
                <span class="block text-2xl font-bold text-gray-800">{{ stats.totalBrands }}</span>
            </div>
            <div v-if="photo.xp" class="text-center">
                <span class="block text-xs text-gray-600 mb-1">XP Earned</span>
                <span class="block text-2xl font-bold text-gray-800">{{ photo.xp }}</span>
            </div>
        </div>

        <!-- Categories -->
        <div class="flex flex-col gap-4">
            <div v-for="category in categorizedData" :key="category.name" class="bg-gray-100 rounded-lg p-4">
                <h3 class="m-0 mb-3 text-base text-gray-800 font-semibold">{{ formatCategoryName(category.name) }}</h3>

                <div v-for="item in category.items" :key="item.id" class="bg-white rounded-md p-3 mb-2 last:mb-0">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="font-semibold text-gray-800 capitalize">{{ item.object?.key || 'unknown' }}</span>
                        <span class="text-sm text-gray-600">×{{ item.quantity }}</span>
                        <span v-if="item.picked_up" class="bg-green-500 text-white px-1.5 py-0.5 rounded text-xs"
                            >✓ Picked up</span
                        >
                    </div>

                    <!-- Materials -->
                    <div v-if="item.materials.length > 0" class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                        <span class="text-xs text-gray-600 mr-1">Materials:</span>
                        <span
                            v-for="mat in item.materials"
                            :key="mat.tag?.id"
                            class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-600"
                        >
                            {{ mat.tag?.key || 'unknown' }} ({{ mat.quantity }})
                        </span>
                    </div>

                    <!-- Brands -->
                    <div v-if="item.brands.length > 0" class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                        <span class="text-xs text-gray-600 mr-1">Brands:</span>
                        <span
                            v-for="brand in item.brands"
                            :key="brand.tag?.id"
                            class="px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-600"
                        >
                            {{ brand.tag?.key || 'unknown' }} ({{ brand.quantity }})
                        </span>
                    </div>

                    <!-- Custom Tags -->
                    <div v-if="item.customTags.length > 0" class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                        <span class="text-xs text-gray-600 mr-1">Tags:</span>
                        <span
                            v-for="custom in item.customTags"
                            :key="custom.tag?.id"
                            class="px-2 py-0.5 rounded-full text-xs font-medium bg-orange-50 text-orange-600"
                        >
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
        const t = props.photo.summary.totals;
        result.totalObjects = t.litter || t.total_objects || 0;
        result.totalMaterials = t.materials || 0;
        result.totalBrands = t.brands || 0;
        result.totalTags = result.totalObjects + result.totalMaterials + result.totalBrands + (t.custom_tags || 0);
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
