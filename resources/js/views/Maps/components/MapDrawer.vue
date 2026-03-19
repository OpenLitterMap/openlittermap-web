<template>
    <div :class="['map-drawer', { open: isOpen }]" v-show="shouldShowDrawer">
        <!-- Drawer Toggle Button -->
        <button class="drawer-toggle" @click="$emit('toggle')">
            <i :class="['fas', isOpen ? 'fa-chevron-left' : 'fa-chart-bar']"></i>
        </button>

        <!-- Drawer Content -->
        <div class="drawer-content">
            <!-- Header -->
            <div class="drawer-header">
                <h2>{{ $t('Data Analysis') }}</h2>
                <div class="tabs">
                    <button :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">
                        {{ $t('Overview') }}
                    </button>
                    <button :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">{{ $t('Details') }}</button>
                    <!--                    <button :class="{ active: activeTab === 'export' }" @click="activeTab = 'export'">Export</button>-->
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="drawer-body">
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>{{ $t('Analyzing data...') }}</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="drawer-body">
                <div class="error-container">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>{{ error }}</p>
                    <button @click="$emit('retry')" class="retry-btn">{{ $t('Try Again') }}</button>
                </div>
            </div>

            <!-- No Data State -->
            <div v-else-if="!processedData" class="drawer-body">
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>{{ $t('No data available for the current selection') }}</p>
                </div>
            </div>

            <!-- Content with Tab Components -->
            <div v-else class="drawer-body">
                <!-- Active Filter Chip -->
                <div v-if="activeFilter" class="flex items-center gap-2 mb-4 px-1">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-green-500/20 border border-green-500/40 rounded-full text-sm text-green-300">
                        <span class="text-xs text-green-400/70 uppercase">{{ activeFilter.type }}</span>
                        <span class="font-medium text-white">{{ formatFilterLabel(activeFilter.id) }}</span>
                        <button
                            class="ml-1 hover:text-white text-green-300/70 transition-colors"
                            @click="$emit('filter-clear')"
                        >
                            <i class="fas fa-times text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Overview Tab -->
                <MapDrawerOverviewTab
                    v-if="activeTab === 'overview'"
                    :stats-data="statsData"
                    :processed-data="processedData"
                    :active-filter="activeFilter"
                    @highlight-category="handleCategoryHighlight"
                    @filter-apply="handleFilterApply"
                />

                <!-- Details Tab -->
                <MapDrawerDetailsTab
                    v-if="activeTab === 'details'"
                    :stats-data="statsData"
                    :processed-data="processedData"
                    :active-filter="activeFilter"
                    @highlight-object="handleObjectHighlight"
                    @filter-apply="handleFilterApply"
                />

                <!-- Export Tab -->
                <MapDrawerExportTab
                    v-if="activeTab === 'export'"
                    :stats-data="statsData"
                    :processed-data="processedData"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import MapDrawerHelper from '../helpers/mapDrawerHelper.js';
import MapDrawerOverviewTab from './tabs/MapDrawerOverviewTab.vue';
import MapDrawerDetailsTab from './tabs/MapDrawerDetailsTab.vue';
import MapDrawerExportTab from './tabs/MapDrawerExportTab.vue';

// Initialize helper
const helper = MapDrawerHelper;

// Props
const props = defineProps({
    statsData: {
        type: Object,
        default: null,
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: null,
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    currentZoom: {
        type: Number,
        default: 2,
    },
    clusterZoomThreshold: {
        type: Number,
        default: 17,
    },
    activeFilter: {
        type: Object,
        default: null,
    },
});

// Emits
const emit = defineEmits(['toggle', 'highlight-category', 'highlight-object', 'filter-apply', 'filter-clear', 'retry']);

// State
const activeTab = ref('overview');

// Computed properties
const shouldShowDrawer = computed(() => {
    // Always show drawer when we're at points zoom level (>= threshold)
    // This ensures the drawer button is always visible in points view
    return props.currentZoom >= props.clusterZoomThreshold;
});

const processedData = computed(() => {
    return helper.processStatsDataForComponent(props.statsData);
});

// Methods
const handleCategoryHighlight = (category) => {
    emit('highlight-category', category);
};

const handleObjectHighlight = (objectData) => {
    emit('highlight-object', objectData);
};

const handleFilterApply = (filter) => {
    emit('filter-apply', filter);
};

const formatFilterLabel = (id) => {
    // Format snake_case to Title Case
    if (typeof id === 'string' && id.includes('_')) {
        return id.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }
    return id;
};

// Watch for stats data changes to validate
watch(
    () => props.statsData,
    (newData) => {
        if (newData && !helper.validateStatsData(newData)) {
            console.warn('Invalid stats data structure received');
        }
    },
    { immediate: true }
);
</script>

<style>
@import '../styles/MapDrawer.css';
</style>
