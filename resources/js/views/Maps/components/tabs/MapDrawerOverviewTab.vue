<template>
    <div class="w-full text-white">
        <!-- Section A: Overview Stats -->
        <section class="mb-6">
            <h3 class="text-lg font-semibold mb-4">📊 Overview</h3>

            <!-- 2x2 Grid -->
            <div class="grid grid-cols-2 gap-3 mb-5">
                <!-- Total Photos -->
                <div
                    class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg p-3 text-center shadow-lg hover:-translate-y-0.5 transition-transform"
                >
                    <div class="text-2xl font-bold">
                        {{ formatNumber(counts.photos) }}
                    </div>
                    <div class="text-xs opacity-90">Total Photos</div>
                </div>

                <!-- Total Objects -->
                <div
                    class="bg-white/10 backdrop-blur-sm rounded-lg p-3 text-center border border-white/20 hover:bg-white/15 hover:-translate-y-0.5 transition-all"
                >
                    <div class="text-2xl font-bold">
                        {{ formatNumber(counts.total_objects) }}
                    </div>
                    <div class="text-xs opacity-90">Total Objects</div>
                </div>

                <!-- Total Brands -->
                <div
                    class="bg-white/10 backdrop-blur-sm rounded-lg p-3 text-center border border-white/20 hover:bg-white/15 hover:-translate-y-0.5 transition-all"
                >
                    <div class="text-2xl font-bold">
                        {{ formatNumber(brandsCount) }}
                    </div>
                    <div class="text-xs opacity-90">Total Brands</div>
                </div>

                <!-- Total Users -->
                <div
                    class="bg-white/10 backdrop-blur-sm rounded-lg p-3 text-center border border-white/20 hover:bg-white/15 hover:-translate-y-0.5 transition-all"
                >
                    <div class="text-2xl font-bold">
                        {{ formatNumber(counts.users) }}
                    </div>
                    <div class="text-xs opacity-90">Total Users</div>
                </div>
            </div>

            <!-- Pickup Stats -->
            <div class="mt-4">
                <div class="flex justify-between items-center mb-2 text-sm">
                    <span>Pickup Status</span>
                    <span class="font-semibold text-green-400">
                        {{ formatPercentage(counts.picked_up, totalPhotos) }} collected
                    </span>
                </div>
                <div class="flex h-8 rounded-full overflow-hidden bg-white/10">
                    <div
                        class="bg-gradient-to-r from-green-500 to-green-600 flex items-center justify-center text-xs font-medium transition-all duration-500"
                        :style="`width: ${pickedUpPercentage}%`"
                    >
                        <span v-if="pickedUpPercentage > 15" class="px-2">
                            {{ formatNumber(counts.picked_up) }} picked up
                        </span>
                    </div>
                    <div class="flex-1 flex items-center justify-center text-xs text-white/70">
                        <span v-if="notPickedUpPercentage > 15" class="px-2">
                            {{ formatNumber(counts.not_picked_up) }} remaining
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section B: Time Series -->
        <section class="mb-6">
            <h3 class="text-lg font-semibold mb-4">📈 Time Series</h3>

            <div
                class="flex justify-between items-center mb-4 p-2 px-3 bg-white/10 backdrop-blur-sm rounded-lg text-sm"
            >
                <span>📅 {{ dateRangeText }}</span>
            </div>

            <!-- Time Series Chart -->
            <div v-if="hasTimeData" class="my-5">
                <div class="flex items-end h-32 gap-0.5 mb-2">
                    <div
                        v-for="(item, idx) in normalizedHistogram"
                        :key="idx"
                        class="flex-1 bg-gradient-to-t from-green-500 to-green-400 rounded-t hover:opacity-80 transition-opacity cursor-pointer min-w-[2px]"
                        :style="`height: ${item.height}%`"
                        :title="`${formatDate(item.bucket)}: ${formatNumber(item.photos)} photos, ${formatNumber(item.objects)} objects`"
                    ></div>
                </div>
                <div class="flex justify-between text-xs text-white/70">
                    <span>{{ firstDate }}</span>
                    <span>{{ lastDate }}</span>
                </div>
            </div>

            <!-- Time Metrics -->
            <div class="space-y-2 mt-4">
                <div class="flex justify-between items-center p-2 px-3 bg-white/10 backdrop-blur-sm rounded-md text-sm">
                    <span class="text-white/70">Daily Average:</span>
                    <span class="font-semibold">{{ dailyAverage }}</span>
                </div>
                <div
                    v-if="peakValue > 0"
                    class="flex justify-between items-center p-2 px-3 bg-white/10 backdrop-blur-sm rounded-md text-sm"
                >
                    <span class="text-white/70">Peak:</span>
                    <span class="font-semibold">{{ formatNumber(peakValue) }} photos</span>
                </div>
            </div>
        </section>

        <!-- Section C: Categories -->
        <section>
            <h3 class="text-lg font-semibold mb-4">🏷️ Categories</h3>

            <div class="space-y-3">
                <div
                    v-for="cat in categories"
                    :key="cat.key"
                    class="p-3 bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg transition-all cursor-pointer hover:bg-white/15 hover:translate-x-1"
                    :class="{
                        'bg-white/15 translate-x-1': highlightedCategory === cat.key,
                        '!bg-green-500/20 !border-green-500/40': isActiveFilter('category', cat.key),
                    }"
                    @mouseenter="handleCategoryHover(cat.key)"
                    @mouseleave="handleCategoryHover(null)"
                    @click="handleCategoryClick(cat)"
                >
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-sm">{{ cat.name }}</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-sm">{{ formatNumber(cat.count) }}</span>
                            <span class="text-xs text-white/70">{{ cat.percentage }}</span>
                        </div>
                    </div>
                    <div class="h-1 bg-white/10 rounded-full overflow-hidden">
                        <div
                            class="h-full transition-all duration-500 ease-out"
                            :style="{
                                width: `${cat.width}%`,
                                backgroundColor: cat.color,
                            }"
                        />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import MapDrawerHelper from '../../helpers/mapDrawerHelper.js';

const props = defineProps({
    statsData: {
        type: Object,
        required: true,
    },
    processedData: {
        type: Object,
        required: true,
    },
    activeFilter: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['highlight-category', 'filter-apply']);

const highlightedCategory = ref(null);

// Helper functions
const formatNumber = (num) => MapDrawerHelper.formatNumber(num);
const formatPercentage = (value, total) => MapDrawerHelper.formatPercentage(value, total);
const formatDate = (date) => MapDrawerHelper.formatDate(date);

// FIXED: Data is directly on statsData, not statsData.data
const counts = computed(() => props.statsData?.counts || {});

const totalPhotos = computed(() => counts.value.photos || 0);

const brandsCount = computed(() => {
    const brands = props.statsData?.brands || [];
    return brands.reduce((sum, brand) => sum + (brand.qty || 0), 0);
});

const pickedUpPercentage = computed(() => {
    const total = totalPhotos.value;
    if (!total || total === 0) return 0;
    return ((counts.value.picked_up || 0) / total) * 100;
});

const notPickedUpPercentage = computed(() => {
    return 100 - pickedUpPercentage.value;
});

const dateRangeText = computed(() => {
    // Meta might be on statsData.meta or on a separate meta prop
    const meta = props.statsData?.meta || {};
    if (meta.from && meta.to) {
        return MapDrawerHelper.formatDateRange(meta.from, meta.to);
    }
    if (meta.year) {
        return `Year ${meta.year}`;
    }
    return 'All time';
});

// Time series data
const timeHistogram = computed(() => props.statsData?.time_histogram || []);

const hasTimeData = computed(() => timeHistogram.value.length > 0);

const normalizedHistogram = computed(() => {
    const data = timeHistogram.value;
    if (!data || data.length === 0) return [];

    const maxValue = Math.max(...data.map((h) => h.photos || 0));
    if (maxValue === 0) return data.map((item) => ({ ...item, height: 0 }));

    return data.map((item) => ({
        ...item,
        height: ((item.photos || 0) / maxValue) * 100,
    }));
});

const firstDate = computed(() => {
    if (!hasTimeData.value) return '';
    return formatDate(timeHistogram.value[0].bucket);
});

const lastDate = computed(() => {
    if (!hasTimeData.value) return '';
    return formatDate(timeHistogram.value[timeHistogram.value.length - 1].bucket);
});

const dailyAverage = computed(() => {
    if (!hasTimeData.value) return '0';
    const total = timeHistogram.value.reduce((sum, item) => sum + (item.photos || 0), 0);
    const average = total / timeHistogram.value.length;
    return formatNumber(Math.round(average * 10) / 10);
});

const peakValue = computed(() => {
    if (!hasTimeData.value) return 0;
    return Math.max(...timeHistogram.value.map((h) => h.photos || 0));
});

// Categories
const categories = computed(() => {
    const cats = props.statsData?.by_category || [];
    const totalCount = cats.reduce((sum, cat) => sum + (cat.qty || 0), 0);

    return cats.map((cat) => {
        const count = cat.qty || 0;
        const width = totalCount > 0 ? (count / totalCount) * 100 : 0;
        const percentage = width < 0.1 ? '<0.1%' : `${width.toFixed(1)}%`;

        return {
            key: cat.key,
            name: MapDrawerHelper.formatFilterKey(cat.key),
            count,
            width,
            percentage,
            color: MapDrawerHelper.getCategoryColor(cat.key),
        };
    });
});

const handleCategoryHover = (category) => {
    highlightedCategory.value = category;
    emit('highlight-category', category);
};

const handleCategoryClick = (cat) => {
    emit('filter-apply', { type: 'category', id: cat.key, label: cat.name });
};

const isActiveFilter = (type, id) => {
    return props.activeFilter?.type === type && props.activeFilter?.id === id;
};
</script>
