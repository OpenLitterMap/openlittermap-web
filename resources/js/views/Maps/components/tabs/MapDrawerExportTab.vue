<template>
    <div class="export-tab">
        <section class="stats-section">
            <h3>📥 Export Data</h3>
            <div class="export-options">
                <button class="export-btn csv" @click="exportCSV" :disabled="isExporting">
                    <i class="fas fa-file-csv"></i>
                    Export as CSV
                </button>
                <button class="export-btn json" @click="exportJSON" :disabled="isExporting">
                    <i class="fas fa-file-code"></i>
                    Export as JSON
                </button>
                <button class="export-btn excel" @click="exportExcel" :disabled="isExporting">
                    <i class="fas fa-file-excel"></i>
                    Export as Excel
                </button>
                <button class="export-btn report" @click="generateReport" :disabled="isExporting">
                    <i class="fas fa-file-pdf"></i>
                    Generate Report
                </button>
            </div>

            <!-- Export Status -->
            <div v-if="isExporting" class="export-status">
                <div class="spinner-small"></div>
                <span>Preparing export...</span>
            </div>

            <div class="export-info">
                <p>Export includes all data from the current view with applied filters.</p>
                <div v-if="processedData.hasFilters" class="applied-filters">
                    <h4>Applied Filters:</h4>
                    <div class="filter-tags">
                        <span v-for="(value, key) in statsData.filters_applied" :key="key" class="filter-tag">
                            <strong>{{ helper.formatFilterKey(key) }}:</strong>
                            {{ Array.isArray(value) ? value.join(', ') : value }}
                        </span>
                    </div>
                </div>

                <!-- Cache Info -->
                <div v-if="statsData.cache_info" class="cache-info">
                    <small> Data source: {{ helper.formatCacheInfo(statsData.cache_info) }} </small>
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

// State
const isExporting = ref(false);

// Methods
const exportCSV = async () => {
    await helper.handleExport(
        helper.exportToCSV,
        (loading) => {
            isExporting.value = loading;
        },
        props.statsData,
        props.statsData.filters_applied || {}
    );
};

const exportJSON = async () => {
    await helper.handleExport(
        helper.exportToJSON,
        (loading) => {
            isExporting.value = loading;
        },
        props.statsData,
        props.statsData.filters_applied || {}
    );
};

const exportExcel = async () => {
    await helper.handleExport(
        () => {
            // TODO: Implement Excel export using SheetJS
            console.log('Excel export - TODO: Implement');
        },
        (loading) => {
            isExporting.value = loading;
        }
    );
};

const generateReport = async () => {
    await helper.handleExport(
        () => {
            // TODO: Implement PDF report generation
            console.log('PDF report generation - TODO: Implement');
        },
        (loading) => {
            isExporting.value = loading;
        }
    );
};
</script>

<style scoped>
.export-tab {
    width: 100%;
    color: white; /* Default text color */
}

/* Export Options */
.export-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.export-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border: 2px solid transparent;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.export-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.export-btn.csv {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.export-btn.csv:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.export-btn.json {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.export-btn.json:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.export-btn.excel {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
}

.export-btn.excel:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);
}

.export-btn.report {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.export-btn.report:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.export-btn i {
    font-size: 16px;
}

/* Export Status */
.export-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    margin-bottom: 20px;
    color: white;
}

.spinner-small {
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.2);
    border-top-color: #14d145;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* Export Info */
.export-info {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    padding: 16px;
}

.export-info p {
    margin: 0 0 16px 0;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
}

.applied-filters h4 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: white;
}

.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.9);
}

.filter-tag strong {
    color: white;
}

.cache-info {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.6);
}

.cache-info small {
    font-size: 12px;
}
</style>
