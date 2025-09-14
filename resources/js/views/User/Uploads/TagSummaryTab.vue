<template>
    <div class="summary-view">
        <div class="summary-header">
            <h3>Photo Summary (v5 Format)</h3>
            <div class="summary-actions">
                <button class="action-btn" @click="copyToClipboard" :title="copyStatus || 'Copy to clipboard'">
                    {{ copyStatus === 'Copied!' ? '✓' : '📋' }} Copy
                </button>
                <button class="action-btn" @click="expanded = !expanded">
                    {{ expanded ? 'Collapse' : 'Expand' }}
                </button>
            </div>
        </div>

        <div v-if="summary" class="summary-content">
            <!-- Quick Stats from Summary -->
            <div v-if="summary.totals" class="summary-stats">
                <div class="stat-item">
                    <span class="label">Total Tags:</span>
                    <span class="value">{{ summary.totals.total_tags }}</span>
                </div>
                <div class="stat-item">
                    <span class="label">Total Objects:</span>
                    <span class="value">{{ summary.totals.total_objects }}</span>
                </div>
                <div class="stat-item" v-if="summary.totals.materials > 0">
                    <span class="label">Materials:</span>
                    <span class="value">{{ summary.totals.materials }}</span>
                </div>
                <div class="stat-item" v-if="summary.totals.brands > 0">
                    <span class="label">Brands:</span>
                    <span class="value">{{ summary.totals.brands }}</span>
                </div>
                <div class="stat-item" v-if="summary.totals.custom_tags > 0">
                    <span class="label">Custom Tags:</span>
                    <span class="value">{{ summary.totals.custom_tags }}</span>
                </div>
            </div>

            <!-- JSON Display -->
            <pre class="json-content" :class="{ expanded }">{{ formatJSON(summary) }}</pre>
        </div>
        <div v-else class="no-summary">
            <p>No summary generated yet</p>
            <span class="info-text">Summary is generated after photo migration to v5 format</span>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    summary: {
        type: Object,
        default: null,
    },
});

const expanded = ref(false);
const copyStatus = ref('');

const formatJSON = (data) => {
    return JSON.stringify(data, null, 2);
};

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(JSON.stringify(props.summary, null, 2));
        copyStatus.value = 'Copied!';
        setTimeout(() => {
            copyStatus.value = '';
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
        copyStatus.value = 'Failed';
        setTimeout(() => {
            copyStatus.value = '';
        }, 2000);
    }
};
</script>

<style scoped>
.summary-view {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.summary-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    background: #f0f0f0;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #e0e0e0;
}

.summary-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    padding: 16px;
    background: #f8f8f8;
    border-radius: 8px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-item .label {
    font-size: 13px;
    color: #666;
}

.stat-item .value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.json-content {
    background: #f5f5f5;
    padding: 16px;
    border-radius: 6px;
    font-size: 12px;
    margin: 0;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    line-height: 1.5;
    overflow: auto;
    max-height: 400px;
    transition: max-height 0.3s ease;
}

.json-content.expanded {
    max-height: none;
}

.no-summary {
    text-align: center;
    padding: 40px 20px;
    background: #f8f8f8;
    border-radius: 8px;
}

.no-summary p {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #666;
}

.info-text {
    font-size: 13px;
    color: #999;
}

@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: 1fr;
    }
}
</style>
