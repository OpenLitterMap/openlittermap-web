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
                <h2>Data Analysis</h2>
                <div class="tabs">
                    <button :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">
                        Overview
                    </button>
                    <button :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">Details</button>
                    <!--                    <button :class="{ active: activeTab === 'export' }" @click="activeTab = 'export'">Export</button>-->
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="drawer-body">
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>Analyzing data...</p>
                </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="drawer-body">
                <div class="error-container">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>{{ error }}</p>
                    <button @click="$emit('retry')" class="retry-btn">Try Again</button>
                </div>
            </div>

            <!-- No Data State -->
            <div v-else-if="!processedData" class="drawer-body">
                <div class="no-data">
                    <i class="fas fa-chart-line"></i>
                    <p>No data available for the current selection</p>
                </div>
            </div>

            <!-- Content with Tab Components -->
            <div v-else class="drawer-body">
                <!-- Overview Tab -->
                <MapDrawerOverviewTab
                    v-if="activeTab === 'overview'"
                    :stats-data="statsData"
                    :processed-data="processedData"
                    @highlight-category="handleCategoryHighlight"
                />

                <!-- Details Tab -->
                <MapDrawerDetailsTab
                    v-if="activeTab === 'details'"
                    :stats-data="statsData"
                    :processed-data="processedData"
                    @highlight-object="handleObjectHighlight"
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
});

// Emits
const emit = defineEmits(['toggle', 'highlight-category', 'highlight-object', 'retry']);

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
