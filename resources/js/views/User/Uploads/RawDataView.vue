<template>
    <div class="raw-view">
        <div class="raw-section">
            <div class="section-header">
                <h3>Old Tags (v4)</h3>
                <button
                    class="copy-btn"
                    @click="copyToClipboard(oldTags, 'old')"
                    :title="copyStatus.old || 'Copy to clipboard'"
                >
                    {{ copyStatus.old === 'Copied!' ? '✓' : '📋' }}
                </button>
            </div>
            <pre class="raw-content">{{ formatJSON(oldTags || {}) }}</pre>
        </div>

        <div class="raw-section">
            <div class="section-header">
                <h3>New Tags (v5)</h3>
                <button
                    class="copy-btn"
                    @click="copyToClipboard(newTags, 'new')"
                    :title="copyStatus.new || 'Copy to clipboard'"
                >
                    {{ copyStatus.new === 'Copied!' ? '✓' : '📋' }}
                </button>
            </div>
            <pre class="raw-content">{{ formatJSON(newTags || []) }}</pre>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    oldTags: {
        type: Object,
        default: () => ({}),
    },
    newTags: {
        type: Array,
        default: () => [],
    },
});

const copyStatus = ref({ old: '', new: '' });

const formatJSON = (data) => {
    return JSON.stringify(data, null, 2);
};

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
</script>

<style scoped>
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
}
</style>
