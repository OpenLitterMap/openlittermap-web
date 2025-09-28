<template>
    <div class="flex flex-col gap-4">
        <div class="flex justify-between items-center">
            <h3 class="m-0 text-lg text-gray-800 font-semibold">Photo Summary (v5 Format)</h3>
            <div class="flex gap-2">
                <button
                    class="bg-gray-100 hover:bg-gray-200 border-0 px-3 py-1.5 rounded cursor-pointer text-xs transition-all"
                    @click="copyToClipboard"
                    :title="copyStatus || 'Copy to clipboard'"
                >
                    {{ copyStatus === 'Copied!' ? '✓' : '📋' }} Copy
                </button>
                <button
                    class="bg-gray-100 hover:bg-gray-200 border-0 px-3 py-1.5 rounded cursor-pointer text-xs transition-all"
                    @click="expanded = !expanded"
                >
                    {{ expanded ? 'Collapse' : 'Expand' }}
                </button>
            </div>
        </div>

        <div v-if="summary" class="flex flex-col gap-4">
            <!-- Quick Stats from Summary - Reordered -->
            <div
                v-if="summary.totals"
                class="grid grid-cols-1 md:grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3 p-4 bg-gray-100 rounded-lg"
            >
                <!-- Objects First -->
                <div class="flex justify-between items-center">
                    <span class="text-xs text-gray-600">Total Objects:</span>
                    <span class="text-base font-semibold text-gray-800">{{ summary.totals.total_objects }}</span>
                </div>

                <!-- Materials -->
                <div v-if="summary.totals.materials > 0" class="flex justify-between items-center">
                    <span class="text-xs text-gray-600">Materials:</span>
                    <span class="text-base font-semibold text-gray-800">{{ summary.totals.materials }}</span>
                </div>

                <!-- Brands -->
                <div v-if="summary.totals.brands > 0" class="flex justify-between items-center">
                    <span class="text-xs text-gray-600">Brands:</span>
                    <span class="text-base font-semibold text-gray-800">{{ summary.totals.brands }}</span>
                </div>

                <!-- Custom Tags -->
                <div v-if="summary.totals.custom_tags > 0" class="flex justify-between items-center">
                    <span class="text-xs text-gray-600">Custom Tags:</span>
                    <span class="text-base font-semibold text-gray-800">{{ summary.totals.custom_tags }}</span>
                </div>

                <!-- Total Tags Last (distinct) -->
                <div
                    class="flex justify-between items-center border-t md:border-t-0 md:border-l border-gray-300 pt-3 md:pt-0 md:pl-3 col-span-full md:col-span-1"
                >
                    <span class="text-xs text-gray-600 font-medium">Total Tags:</span>
                    <span class="text-base font-semibold text-gray-800">{{ summary.totals.total_tags }}</span>
                </div>
            </div>

            <!-- JSON Display -->
            <pre
                class="bg-gray-50 p-4 rounded-md text-xs m-0 font-mono leading-relaxed overflow-auto transition-all"
                :class="expanded ? 'max-h-none' : 'max-h-96'"
                >{{ formatJSON(summary) }}</pre
            >
        </div>

        <div v-else class="text-center py-10 px-5 bg-gray-100 rounded-lg">
            <p class="m-0 mb-2 text-base text-gray-600">No summary generated yet</p>
            <span class="text-xs text-gray-400">Summary is generated after photo migration to v5 format</span>
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
