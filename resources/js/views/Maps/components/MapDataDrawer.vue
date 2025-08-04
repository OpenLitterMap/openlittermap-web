<template>
    <div class="map-drawer-container" :class="{ open: isOpen }">
        <!-- Drawer Toggle Button -->
        <button class="drawer-toggle" @click="toggleDrawer" :title="isOpen ? 'Close data panel' : 'Open data panel'">
            <i class="fas" :class="isOpen ? 'fa-chevron-left' : 'fa-chart-bar'"></i>
        </button>

        <!-- Drawer Content -->
        <div class="drawer-content">
            <!-- Header -->
            <div class="drawer-header">
                <h3>Data Analysis</h3>
                <button class="close-btn" @click="isOpen = false">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="drawer-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Analyzing data...</p>
            </div>

            <!-- Content Sections -->
            <div v-else class="drawer-sections">
                <!-- Summary Statistics -->
                <section class="drawer-section">
                    <h4>
                        <i class="fas fa-chart-pie"></i>
                        Summary Statistics
                    </h4>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value">{{ totalPoints }}</div>
                            <div class="stat-label">Total Photos</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">{{ totalLitterItems }}</div>
                            <div class="stat-label">Total Litter Items</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">{{ pickedUpCount }}</div>
                            <div class="stat-label">Picked Up</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">{{ verifiedCount }}</div>
                            <div class="stat-label">Verified</div>
                        </div>
                    </div>
                </section>

                <!-- Date Range -->
                <section class="drawer-section">
                    <h4>
                        <i class="fas fa-calendar-alt"></i>
                        Date Range
                    </h4>
                    <div class="date-range-display">
                        <div class="date-item">
                            <span class="date-label">From:</span>
                            <span class="date-value">{{ dateRange.start }}</span>
                        </div>
                        <div class="date-item">
                            <span class="date-label">To:</span>
                            <span class="date-value">{{ dateRange.end }}</span>
                        </div>
                    </div>
                </section>

                <!-- Category Distribution -->
                <section class="drawer-section" v-if="categoryData.length > 0">
                    <h4>
                        <i class="fas fa-tags"></i>
                        Litter Categories
                    </h4>
                    <div class="category-list">
                        <div v-for="(cat, index) in topCategories" :key="cat.name" class="category-item">
                            <span class="category-name">{{ cat.name }}</span>
                            <div class="category-bar-wrapper">
                                <div
                                    class="category-bar"
                                    :style="`width: ${(cat.count / maxCategoryCount) * 100}%; background: ${getCategoryColor(index)}`"
                                ></div>
                                <span class="category-count">{{ cat.count }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Top Litter Items -->
                <section class="drawer-section" v-if="topLitterItems.length > 0">
                    <h4>
                        <i class="fas fa-trash"></i>
                        Top Litter Items
                    </h4>
                    <div class="items-list">
                        <div v-for="(item, index) in topLitterItems" :key="index" class="item-row">
                            <span class="item-rank">#{{ index + 1 }}</span>
                            <span class="item-name">{{ formatItemName(item.name) }}</span>
                            <span class="item-count">{{ item.count }}</span>
                        </div>
                    </div>
                </section>

                <!-- Top Brands -->
                <section class="drawer-section" v-if="topBrands.length > 0">
                    <h4>
                        <i class="fas fa-copyright"></i>
                        Top Brands Found
                    </h4>
                    <div class="brands-list">
                        <div v-for="(brand, index) in topBrands" :key="index" class="brand-item">
                            <span class="brand-rank">#{{ index + 1 }}</span>
                            <span class="brand-name">{{ brand.name }}</span>
                            <span class="brand-count">{{ brand.count }}</span>
                        </div>
                    </div>
                </section>

                <!-- Top Contributors -->
                <section class="drawer-section" v-if="topContributors.length > 0">
                    <h4>
                        <i class="fas fa-users"></i>
                        Top Contributors
                    </h4>
                    <div class="contributors-list">
                        <div v-for="(contributor, index) in topContributors" :key="index" class="contributor-item">
                            <span class="contributor-rank">#{{ index + 1 }}</span>
                            <span class="contributor-name">{{ contributor.name }}</span>
                            <div class="contributor-stats">
                                <span class="contributor-count">{{ contributor.count }} photos</span>
                                <span class="contributor-litter">{{ contributor.totalLitter }} items</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Teams Distribution -->
                <section class="drawer-section" v-if="teamsData.length > 0">
                    <h4>
                        <i class="fas fa-people-group"></i>
                        Teams Distribution
                    </h4>
                    <div class="teams-list">
                        <div v-for="(team, index) in teamsData" :key="index" class="team-item">
                            <span class="team-name">{{ team.name }}</span>
                            <span class="team-count">{{ team.count }} photos</span>
                        </div>
                    </div>
                </section>

                <!-- Export Options -->
                <section class="drawer-section">
                    <h4>
                        <i class="fas fa-download"></i>
                        Export Data
                    </h4>
                    <div class="export-buttons">
                        <button @click="exportCSV" class="export-btn">
                            <i class="fas fa-file-csv"></i>
                            Export as CSV
                        </button>
                        <button @click="exportJSON" class="export-btn">
                            <i class="fas fa-file-code"></i>
                            Export as JSON
                        </button>
                        <button @click="exportSummaryReport" class="export-btn full-width">
                            <i class="fas fa-file-alt"></i>
                            Generate Summary Report
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import moment from 'moment';

const props = defineProps({
    pointsData: {
        type: Object,
        default: () => ({ features: [] }),
    },
    isLoading: {
        type: Boolean,
        default: false,
    },
});

const isOpen = ref(false);

// Category colors
const categoryColors = [
    '#14d145',
    '#0ea5e9',
    '#f59e0b',
    '#ef4444',
    '#8b5cf6',
    '#ec4899',
    '#10b981',
    '#f97316',
    '#6366f1',
    '#84cc16',
];

// Computed statistics
const totalPoints = computed(() => props.pointsData.features?.length || 0);

const totalLitterItems = computed(() => {
    return (
        props.pointsData.features?.reduce((sum, f) => {
            if (f.properties.summary?.totals?.total_tags) {
                return sum + f.properties.summary.totals.total_tags;
            }
            return sum + (f.properties.total_litter || 0);
        }, 0) || 0
    );
});

const pickedUpCount = computed(() => {
    return props.pointsData.features?.filter((f) => f.properties.picked_up).length || 0;
});

const verifiedCount = computed(() => {
    return props.pointsData.features?.filter((f) => f.properties.verified >= 2).length || 0;
});

const dateRange = computed(() => {
    if (!props.pointsData.features?.length) {
        return { start: 'N/A', end: 'N/A' };
    }

    const dates = props.pointsData.features.map((f) => moment(f.properties.datetime)).sort((a, b) => a - b);

    return {
        start: dates[0].format('MMM D, YYYY'),
        end: dates[dates.length - 1].format('MMM D, YYYY'),
    };
});

// Analyze categories from summary data
const categoryData = computed(() => {
    const categories = {};

    props.pointsData.features?.forEach((f) => {
        if (f.properties.summary?.totals?.by_category) {
            Object.entries(f.properties.summary.totals.by_category).forEach(([category, count]) => {
                categories[category] = (categories[category] || 0) + count;
            });
        }
    });

    return Object.entries(categories)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count);
});

const topCategories = computed(() => categoryData.value.slice(0, 10));
const maxCategoryCount = computed(() => Math.max(...categoryData.value.map((c) => c.count), 1));

// Analyze top litter items from summary data
const topLitterItems = computed(() => {
    const items = {};

    props.pointsData.features?.forEach((f) => {
        if (f.properties.summary?.tags) {
            Object.entries(f.properties.summary.tags).forEach(([category, objects]) => {
                Object.entries(objects).forEach(([objectKey, data]) => {
                    const itemName = `${category}.${objectKey}`;
                    items[itemName] = (items[itemName] || 0) + data.quantity;
                });
            });
        }
    });

    return Object.entries(items)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 15);
});

// Analyze brands from summary data
const topBrands = computed(() => {
    const brands = {};

    props.pointsData.features?.forEach((f) => {
        if (f.properties.summary?.tags) {
            Object.values(f.properties.summary.tags).forEach((objects) => {
                Object.values(objects).forEach((data) => {
                    if (data.brands) {
                        Object.entries(data.brands).forEach(([brand, count]) => {
                            brands[brand] = (brands[brand] || 0) + count;
                        });
                    }
                });
            });
        }
    });

    return Object.entries(brands)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => b.count - a.count)
        .slice(0, 10);
});

const topContributors = computed(() => {
    const contributors = {};

    props.pointsData.features?.forEach((f) => {
        const name = f.properties.username || f.properties.name || 'Anonymous';
        if (!contributors[name]) {
            contributors[name] = {
                name,
                count: 0,
                totalLitter: 0,
            };
        }
        contributors[name].count++;
        contributors[name].totalLitter += f.properties.summary?.totals?.total_tags || f.properties.total_litter || 0;
    });

    return Object.values(contributors)
        .sort((a, b) => b.count - a.count)
        .slice(0, 10);
});

const teamsData = computed(() => {
    const teams = {};

    props.pointsData.features?.forEach((f) => {
        if (f.properties.team) {
            teams[f.properties.team] = (teams[f.properties.team] || 0) + 1;
        }
    });

    return Object.entries(teams)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .map(([name, count]) => ({ name, count }));
});

// Helper functions
const getCategoryColor = (index) => categoryColors[index % categoryColors.length];

const formatItemName = (name) => {
    // Convert category.item to more readable format
    return name.replace('.', ' → ').replace(/_/g, ' ');
};

// Toggle drawer
const toggleDrawer = () => {
    isOpen.value = !isOpen.value;
};

// Export functions
const exportCSV = () => {
    if (!props.pointsData.features?.length) return;

    const headers = [
        'ID',
        'Datetime',
        'Latitude',
        'Longitude',
        'Username',
        'Team',
        'Total Litter',
        'Total Brands',
        'Materials',
        'Custom Tags',
        'Picked Up',
        'Verified',
    ];
    const rows = props.pointsData.features.map((f) => {
        const summary = f.properties.summary?.totals || {};
        return [
            f.properties.id,
            f.properties.datetime,
            f.geometry.coordinates[1],
            f.geometry.coordinates[0],
            f.properties.username || '',
            f.properties.team || '',
            summary.total_tags || f.properties.total_litter || 0,
            summary.brands || 0,
            summary.materials || 0,
            summary.custom_tags || 0,
            f.properties.picked_up ? 'Yes' : 'No',
            f.properties.verified,
        ];
    });

    const csv = [headers, ...rows].map((row) => row.join(',')).join('\n');
    downloadFile(csv, 'litter-data.csv', 'text/csv');
};

const exportJSON = () => {
    if (!props.pointsData.features?.length) return;

    // Create a clean export without filename
    const exportData = {
        type: props.pointsData.type,
        features: props.pointsData.features.map((f) => ({
            type: f.type,
            geometry: f.geometry,
            properties: {
                id: f.properties.id,
                datetime: f.properties.datetime,
                verified: f.properties.verified,
                picked_up: f.properties.picked_up,
                total_litter: f.properties.total_litter,
                username: f.properties.username,
                name: f.properties.name,
                team: f.properties.team,
                summary: f.properties.summary,
            },
        })),
    };

    const json = JSON.stringify(exportData, null, 2);
    downloadFile(json, 'litter-data.json', 'application/json');
};

const exportSummaryReport = () => {
    if (!props.pointsData.features?.length) return;

    // Detailed category breakdown with items
    const categoryBreakdown = {};
    props.pointsData.features.forEach((f) => {
        if (f.properties.summary?.tags) {
            Object.entries(f.properties.summary.tags).forEach(([category, objects]) => {
                if (!categoryBreakdown[category]) {
                    categoryBreakdown[category] = {
                        total: 0,
                        items: {},
                        brands: {},
                        materials: {},
                    };
                }

                Object.entries(objects).forEach(([objectKey, data]) => {
                    categoryBreakdown[category].items[objectKey] =
                        (categoryBreakdown[category].items[objectKey] || 0) + data.quantity;
                    categoryBreakdown[category].total += data.quantity;

                    // Aggregate brands
                    if (data.brands) {
                        Object.entries(data.brands).forEach(([brand, count]) => {
                            categoryBreakdown[category].brands[brand] =
                                (categoryBreakdown[category].brands[brand] || 0) + count;
                        });
                    }

                    // Aggregate materials
                    if (data.materials) {
                        Object.entries(data.materials).forEach(([material, count]) => {
                            categoryBreakdown[category].materials[material] =
                                (categoryBreakdown[category].materials[material] || 0) + count;
                        });
                    }
                });
            });
        }
    });

    const report = {
        metadata: {
            generated_at: new Date().toISOString(),
            total_photos: totalPoints.value,
            total_litter_items: totalLitterItems.value,
            date_range: dateRange.value,
            verified_photos: verifiedCount.value,
            picked_up_photos: pickedUpCount.value,
            photos_with_brands: props.pointsData.features.filter((f) => f.properties.summary?.totals?.brands > 0)
                .length,
            photos_with_materials: props.pointsData.features.filter((f) => f.properties.summary?.totals?.materials > 0)
                .length,
        },
        summary: {
            categories: categoryData.value,
            top_items: topLitterItems.value,
            top_brands: topBrands.value,
            top_contributors: topContributors.value,
            teams: teamsData.value,
        },
        detailed_breakdown: categoryBreakdown,
        temporal_analysis: {
            photos_by_date: getPhotosByDate(),
            photos_by_hour: getPhotosByHour(),
            photos_by_day_of_week: getPhotosByDayOfWeek(),
        },
    };

    const json = JSON.stringify(report, null, 2);
    downloadFile(json, 'litter-summary-report.json', 'application/json');
};

// Helper functions for temporal analysis
const getPhotosByDate = () => {
    const byDate = {};
    props.pointsData.features.forEach((f) => {
        const date = moment(f.properties.datetime).format('YYYY-MM-DD');
        byDate[date] = (byDate[date] || 0) + 1;
    });
    return byDate;
};

const getPhotosByHour = () => {
    const byHour = new Array(24).fill(0);
    props.pointsData.features.forEach((f) => {
        const hour = moment(f.properties.datetime).hour();
        byHour[hour]++;
    });
    return byHour.map((count, hour) => ({ hour: `${hour}:00`, count }));
};

const getPhotosByDayOfWeek = () => {
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const byDay = new Array(7).fill(0);
    props.pointsData.features.forEach((f) => {
        const day = moment(f.properties.datetime).day();
        byDay[day]++;
    });
    return byDay.map((count, index) => ({ day: days[index], count }));
};

const downloadFile = (content, filename, mimeType) => {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
};
</script>

<style scoped>
.map-drawer-container {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    z-index: 1000;
    display: flex;
}

.drawer-toggle {
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: none;
    border-radius: 0 8px 8px 0;
    padding: 12px 8px;
    cursor: pointer;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    z-index: 1001;
}

.map-drawer-container.open .drawer-toggle {
    left: 350px;
}

.drawer-toggle:hover {
    background: #f3f4f6;
}

.drawer-content {
    position: absolute;
    left: -350px;
    top: 0;
    width: 350px;
    height: 100%;
    background: white;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    transition: left 0.3s ease;
    overflow-y: auto;
}

.map-drawer-container.open .drawer-content {
    left: 0;
}

.drawer-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
}

.drawer-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #1f2937;
}

.close-btn {
    background: none;
    border: none;
    font-size: 20px;
    color: #6b7280;
    cursor: pointer;
    padding: 4px;
}

.close-btn:hover {
    color: #374151;
}

.drawer-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #6b7280;
}

.drawer-loading i {
    font-size: 32px;
    margin-bottom: 12px;
}

.drawer-sections {
    padding-bottom: 20px;
}

.drawer-section {
    padding: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.drawer-section:last-child {
    border-bottom: none;
}

.drawer-section h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
}

.drawer-section h4 i {
    color: #14d145;
}

/* Statistics Grid */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.stat-card {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #14d145;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: #6b7280;
}

/* Date Range */
.date-range-display {
    background: #f9fafb;
    padding: 12px;
    border-radius: 8px;
}

.date-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.date-item:last-child {
    margin-bottom: 0;
}

.date-label {
    font-weight: 500;
    color: #6b7280;
}

.date-value {
    color: #374151;
}

/* Category List */
.category-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.category-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.category-name {
    font-size: 14px;
    color: #374151;
    font-weight: 500;
}

.category-bar-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 20px;
}

.category-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.category-count {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
}

/* Items List */
.items-list {
    max-height: 300px;
    overflow-y: auto;
}

.item-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.item-row:last-child {
    border-bottom: none;
}

.item-rank {
    width: 30px;
    font-weight: 600;
    color: #14d145;
}

.item-name {
    flex: 1;
    color: #374151;
}

.item-count {
    font-weight: 500;
    color: #6b7280;
}

/* Brands List */
.brands-list {
    max-height: 200px;
    overflow-y: auto;
}

.brand-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
    font-size: 14px;
}

.brand-item:last-child {
    border-bottom: none;
}

.brand-rank {
    width: 30px;
    font-weight: 600;
    color: #f59e0b;
}

.brand-name {
    flex: 1;
    color: #374151;
}

.brand-count {
    font-weight: 500;
    color: #6b7280;
}

/* Contributors List */
.contributors-list {
    max-height: 300px;
    overflow-y: auto;
}

.contributor-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.contributor-item:last-child {
    border-bottom: none;
}

.contributor-rank {
    width: 30px;
    font-weight: 600;
    color: #14d145;
}

.contributor-name {
    flex: 1;
    color: #374151;
}

.contributor-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    font-size: 12px;
}

.contributor-count {
    color: #6b7280;
}

.contributor-litter {
    color: #9ca3af;
    font-size: 11px;
}

/* Teams List */
.teams-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.team-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: #f9fafb;
    border-radius: 6px;
}

.team-name {
    font-weight: 500;
    color: #374151;
}

.team-count {
    font-size: 14px;
    color: #6b7280;
}

/* Export Buttons */
.export-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.export-btn {
    flex: 1;
    min-width: 120px;
    padding: 10px 16px;
    background: #14d145;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.export-btn.full-width {
    flex-basis: 100%;
}

.export-btn:hover {
    background: #12b83d;
    transform: translateY(-1px);
}

/* Mobile Responsiveness */
@media (max-width: 640px) {
    .drawer-content {
        width: 280px;
        left: -280px;
    }

    .map-drawer-container.open .drawer-toggle {
        left: 280px;
    }
}

/* Scrollbar Styling */
.drawer-content::-webkit-scrollbar {
    width: 6px;
}

.drawer-content::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.drawer-content::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.drawer-content::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
