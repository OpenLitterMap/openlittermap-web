<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 h-full">
        <div class="flex flex-col min-h-0">
            <div class="flex justify-between items-center mb-3">
                <h3 class="m-0 text-base text-gray-800 font-semibold">Old Tags (v4)</h3>
                <button
                    class="bg-gray-100 hover:bg-gray-200 border-0 px-2.5 py-1.5 rounded cursor-pointer text-sm transition-all"
                    @click="copyToClipboard(oldTags, 'old')"
                    :title="copyStatus.old || 'Copy to clipboard'"
                >
                    {{ copyStatus.old === 'Copied!' ? '✓' : '📋' }}
                </button>
            </div>
            <pre class="bg-gray-50 p-3 rounded-md text-xs m-0 font-mono leading-tight overflow-auto flex-1">{{
                formatJSON(oldTags || {})
            }}</pre>
        </div>

        <div class="flex flex-col min-h-0">
            <div class="flex justify-between items-center mb-3">
                <h3 class="m-0 text-base text-gray-800 font-semibold">New Tags (v5)</h3>
                <button
                    class="bg-gray-100 hover:bg-gray-200 border-0 px-2.5 py-1.5 rounded cursor-pointer text-sm transition-all"
                    @click="copyToClipboard(newTags, 'new')"
                    :title="copyStatus.new || 'Copy to clipboard'"
                >
                    {{ copyStatus.new === 'Copied!' ? '✓' : '📋' }}
                </button>
            </div>
            <pre class="bg-gray-50 p-3 rounded-md text-xs m-0 font-mono leading-tight overflow-auto flex-1">{{
                formatJSON(newTags || [])
            }}</pre>
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
