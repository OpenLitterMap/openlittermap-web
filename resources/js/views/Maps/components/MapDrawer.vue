<template>
    <div
        :class="['map-drawer', { open: isOpen, mobile: isMobile }]"
        :style="isMobile ? mobileStyle : {}"
        v-show="shouldShowDrawer"
    >
        <!-- Desktop: Drawer Toggle Button -->
        <button v-if="!isMobile" class="drawer-toggle" @click="$emit('toggle')">
            <i :class="['fas', isOpen ? 'fa-chevron-left' : 'fa-chart-bar']"></i>
        </button>

        <!-- Mobile: Drag Handle -->
        <div
            v-if="isMobile"
            class="mobile-drag-handle"
            @touchstart.passive="onTouchStart"
            @touchmove.passive="onTouchMove"
            @touchend="onTouchEnd"
            @click="onHandleTap"
        >
            <div class="drag-pill"></div>
            <span class="drag-label">{{ $t('Data Analysis') }}</span>
        </div>

        <!-- Drawer Content -->
        <div
            class="drawer-content"
            :class="{ 'mobile-content': isMobile }"
        >
            <!-- Header -->
            <div class="drawer-header">
                <h2 v-if="!isMobile">{{ $t('Data Analysis') }}</h2>
                <div class="tabs">
                    <button :class="{ active: activeTab === 'overview' }" @click="activeTab = 'overview'">
                        {{ $t('Overview') }}
                    </button>
                    <button :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">{{ $t('Details') }}</button>
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
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
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

// ─── Mobile Detection ────────────────────────────────────────────────
const isMobile = ref(false);
let mediaQuery = null;

const updateMobile = (e) => {
    isMobile.value = e.matches;
};

onMounted(() => {
    mediaQuery = window.matchMedia('(max-width: 768px)');
    isMobile.value = mediaQuery.matches;
    mediaQuery.addEventListener('change', updateMobile);
    window.addEventListener('resize', updateSnapPoints);
});

onBeforeUnmount(() => {
    if (mediaQuery) {
        mediaQuery.removeEventListener('change', updateMobile);
    }
    window.removeEventListener('resize', updateSnapPoints);
});

// ─── Mobile Bottom Drawer ────────────────────────────────────────────
const SNAP_COLLAPSED = 60;
const snapHalf = ref(Math.round(window.innerHeight * 0.5));
const snapFull = ref(Math.round(window.innerHeight * 0.9));

const drawerState = ref('half');
const drawerHeight = ref(snapHalf.value);

const updateSnapPoints = () => {
    snapHalf.value = Math.round(window.innerHeight * 0.5);
    snapFull.value = Math.round(window.innerHeight * 0.9);
    // Re-snap to current state with new values
    if (drawerState.value === 'half') drawerHeight.value = snapHalf.value;
    else if (drawerState.value === 'full') drawerHeight.value = snapFull.value;
};
const isDragging = ref(false);

let touchStartY = 0;
let touchStartHeight = 0;
let touchStartTime = 0;

const mobileStyle = computed(() => {
    if (!isMobile.value) return {};
    return {
        height: `${drawerHeight.value}px`,
        transition: isDragging.value ? 'none' : 'height 0.3s ease',
    };
});

const snapToState = (state) => {
    drawerState.value = state;
    if (state === 'collapsed') {
        drawerHeight.value = SNAP_COLLAPSED;
        emit('toggle');
    } else if (state === 'half') {
        drawerHeight.value = snapHalf.value;
    } else {
        drawerHeight.value = snapFull.value;
    }
};

const onTouchStart = (e) => {
    const touch = e.touches[0];
    touchStartY = touch.clientY;
    touchStartHeight = drawerHeight.value;
    touchStartTime = Date.now();
    isDragging.value = true;
};

const onTouchMove = (e) => {
    if (!isDragging.value) return;
    const touch = e.touches[0];
    const deltaY = touchStartY - touch.clientY; // positive = dragging up
    const newHeight = Math.min(snapFull.value, Math.max(SNAP_COLLAPSED, touchStartHeight + deltaY));
    drawerHeight.value = newHeight;
};

const onTouchEnd = (e) => {
    if (!isDragging.value) return;
    isDragging.value = false;

    const elapsed = Date.now() - touchStartTime;
    const velocity = (touchStartHeight - drawerHeight.value) / elapsed; // negative = dragging up

    // Fast swipe detection
    if (Math.abs(velocity) > 0.5) {
        if (velocity < 0) {
            // Swiped up
            snapToState(drawerState.value === 'collapsed' ? 'half' : 'full');
        } else {
            // Swiped down
            snapToState(drawerState.value === 'full' ? 'half' : 'collapsed');
        }
        return;
    }

    // Position-based snap
    const midCollapsedHalf = (SNAP_COLLAPSED + snapHalf.value) / 2;
    const midHalfFull = (snapHalf.value + snapFull.value) / 2;

    if (drawerHeight.value < midCollapsedHalf) {
        snapToState('collapsed');
    } else if (drawerHeight.value < midHalfFull) {
        snapToState('half');
    } else {
        snapToState('full');
    }
};

const onHandleTap = () => {
    if (isDragging.value) return;
    // Cycle: collapsed → half → full → collapsed
    if (drawerState.value === 'collapsed') {
        snapToState('half');
    } else if (drawerState.value === 'half') {
        snapToState('full');
    } else {
        snapToState('collapsed');
    }
};

// When drawer becomes visible on mobile, default to half
watch(
    () => props.currentZoom >= props.clusterZoomThreshold,
    (visible) => {
        if (visible && isMobile.value && drawerState.value === 'collapsed') {
            snapToState('half');
        }
    }
);

// Computed properties
const shouldShowDrawer = computed(() => {
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
