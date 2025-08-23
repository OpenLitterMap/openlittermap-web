<template>
    <div class="overview-tab">
        <!-- Section A: Metadata -->
        <section class="stats-section">
            <h3>📊 Overview</h3>
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-value">
                        {{ helper.formatNumber(statsData.metadata?.total_photos) }}
                    </div>
                    <div class="stat-label">Total Photos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ helper.formatNumber(processedData.totalObjects) }}</div>
                    <div class="stat-label">Total Objects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ helper.formatNumber(processedData.totalBrands) }}</div>
                    <div class="stat-label">Total Brands</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ helper.formatNumber(statsData.metadata?.total_users) }}</div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <!-- Pickup Stats -->
            <div class="pickup-stats">
                <div class="pickup-header">
                    <span>Pickup Status</span>
                    <span class="pickup-percentage">
                        {{ helper.formatPercentage(statsData.metadata?.picked_up, processedData.totalItems) }}
                        collected
                    </span>
                </div>
                <div class="pickup-bar">
                    <div class="picked-up" :style="`width: ${processedData.pickedUpPercentage}%`">
                        <span v-if="processedData.pickedUpPercentage > 15">
                            {{ helper.formatNumber(statsData.metadata?.picked_up) }} picked up
                        </span>
                    </div>
                    <div class="not-picked-up">
                        <span v-if="processedData.notPickedUpPercentage > 15">
                            {{ helper.formatNumber(statsData.metadata?.not_picked_up) }} remaining
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section B: Time Series -->
        <section class="stats-section">
            <h3>📈 Time Series</h3>
            <div class="date-range">
                <span class="date-text">
                    📅
                    {{
                        helper.formatDateRange(
                            statsData.time_series?.date_range?.from,
                            statsData.time_series?.date_range?.to
                        )
                    }}
                </span>
                <span class="metric-badge" v-if="statsData.time_series?.metrics?.items_per_minute">
                    {{ statsData.time_series.metrics.items_per_minute }} items/min
                </span>
            </div>

            <!-- Simple time series visualization -->
            <div v-if="processedData.timeSeriesData?.length > 0" class="chart-container">
                <div class="simple-chart">
                    <div
                        v-for="(item, idx) in processedData.normalizedHistogram"
                        :key="idx"
                        class="chart-bar"
                        :style="`height: ${item.height}%`"
                        :title="`${helper.formatDate(item.bucket)}: ${helper.formatNumber(item.photos)} photos, ${helper.formatNumber(item.objects)} objects`"
                    ></div>
                </div>
                <div class="chart-labels">
                    <span>{{ processedData.firstDate }}</span>
                    <span>{{ processedData.lastDate }}</span>
                </div>
            </div>

            <div class="time-metrics">
                <div class="metric-item">
                    <span class="metric-label">Daily Average:</span>
                    <span class="metric-value">{{
                        helper.formatNumber(statsData.time_series?.metrics?.avg_per_day)
                    }}</span>
                </div>
                <div class="metric-item" v-if="statsData.time_series?.metrics?.peak_day">
                    <span class="metric-label">Peak Day:</span>
                    <span class="metric-value">
                        {{ helper.formatNumber(statsData.time_series.metrics.peak_day.count) }} on
                        {{ helper.formatDate(statsData.time_series.metrics.peak_day.date) }}
                    </span>
                </div>
            </div>
        </section>

        <!-- Section C: Categories -->
        <section class="stats-section">
            <h3>🏷️ Categories</h3>
            <div class="category-list">
                <div
                    v-for="cat in processedData.categoriesWithPercentages"
                    :key="cat.key"
                    :class="['category-item', { highlighted: highlightedCategory === cat.key }]"
                    @mouseenter="handleCategoryHover(cat.key)"
                    @mouseleave="handleCategoryHover(null)"
                >
                    <div class="category-header">
                        <span class="category-name">{{ cat.name }}</span>
                        <div class="category-stats">
                            <span class="category-count">{{ helper.formatNumber(cat.count) }}</span>
                            <span class="category-percentage">{{ cat.formattedPercentage }}</span>
                        </div>
                    </div>
                    <div class="category-bar-container">
                        <div
                            class="category-bar"
                            :style="{
                                width: `${cat.percentage}%`,
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
import { ref } from 'vue';
import MapDrawerHelper from '../../helpers/mapDrawerHelper.js';

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
const emit = defineEmits(['highlight-category']);

// State
const highlightedCategory = ref(null);

// Methods
const handleCategoryHover = (category) => {
    highlightedCategory.value = category;
    // Emit the category key for highlighting, or null to reset
    emit('highlight-category', category);
};
</script>

<style scoped>
.overview-tab {
    width: 100%;
    color: white; /* Default text color */
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 12px;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: all 0.2s ease;
    color: white;
}

.stat-card:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.15);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.stat-card.primary {
    background: linear-gradient(135deg, #14d145 0%, #12b83d 100%);
    color: white;
    border: none;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    opacity: 0.9;
}

/* Pickup Stats */
.pickup-stats {
    margin-top: 16px;
}

.pickup-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
    color: white;
}

.pickup-percentage {
    font-weight: 600;
    color: #4ade80;
}

.pickup-bar {
    display: flex;
    height: 32px;
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.1);
}

.picked-up {
    background: linear-gradient(90deg, #14d145, #12b83d);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    font-weight: 500;
    transition: width 0.5s ease;
}

.not-picked-up {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.7);
    font-size: 12px;
}

/* Time Series */
.date-range {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
}

.date-text {
    font-size: 14px;
    color: white;
}

.metric-badge {
    background: #14d145;
    color: white;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.chart-container {
    margin: 20px 0;
}

.simple-chart {
    display: flex;
    align-items: flex-end;
    height: 120px;
    gap: 2px;
    margin-bottom: 8px;
}

.chart-bar {
    flex: 1;
    background: linear-gradient(to top, #14d145, #1ed150);
    border-radius: 2px 2px 0 0;
    transition: all 0.3s ease;
    cursor: pointer;
}

.chart-bar:hover {
    opacity: 0.8;
}

.chart-labels {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

.trend-indicator {
    font-weight: 600;
}

.trend-indicator.increasing {
    color: #4ade80;
}

.trend-indicator.decreasing {
    color: #f87171;
}

.trend-indicator.stable {
    color: rgba(255, 255, 255, 0.7);
}

.time-metrics {
    display: grid;
    gap: 8px;
    margin-top: 16px;
}

.metric-item {
    display: flex;
    justify-content: space-between;
    padding: 8px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 6px;
    font-size: 14px;
}

.metric-label {
    color: rgba(255, 255, 255, 0.7);
}

.metric-value {
    font-weight: 600;
    color: white;
}

/* Categories */
.category-list {
    display: grid;
    gap: 12px;
}

.category-item {
    padding: 12px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.category-item:hover,
.category-item.highlighted {
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(4px);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.category-name {
    font-weight: 600;
    font-size: 14px;
    color: white;
}

.category-stats {
    display: flex;
    gap: 8px;
    align-items: center;
}

.category-count {
    font-weight: 600;
    font-size: 14px;
    color: white;
}

.category-percentage {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

.category-bar-container {
    height: 4px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.category-bar {
    height: 100%;
    transition: width 0.5s ease;
}
</style>
