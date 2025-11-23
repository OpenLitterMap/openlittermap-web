<template>
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <!-- Left: Clear button -->
            <button
                v-if="tags.length > 0"
                @click="$emit('clear')"
                class="px-4 py-2 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors"
            >
                Clear All
            </button>
            <div v-else></div>

            <!-- Center: XP Preview -->
            <div class="flex items-center gap-4">
                <div class="text-sm text-gray-400">
                    <span>XP to earn:</span>
                    <span class="ml-2 text-green-400 font-semibold">+{{ xpPreview }}</span>
                </div>

                <!-- Simple XP bar -->
                <div class="w-32 h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-green-500 to-green-400 transition-all duration-300"
                        :style="{ width: Math.min(100, (xpPreview / 50) * 100) + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Right: Submit button -->
            <button
                @click="handleSubmit"
                :disabled="tags.length === 0 || submitting"
                class="px-6 py-2 bg-green-600 text-white rounded font-semibold disabled:opacity-50 disabled:cursor-not-allowed hover:bg-green-700 transition-colors flex items-center gap-2"
            >
                <span v-if="!submitting">Submit Tags</span>
                <span v-else class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    Submitting...
                </span>

                <!-- Keyboard hint -->
                <span v-if="!submitting && tags.length > 0" class="text-xs opacity-70"> ⌘+Enter </span>
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    tags: {
        type: Array,
        default: () => [],
    },
    xpPreview: {
        type: Number,
        default: 0,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['clear', 'submit']);

const handleSubmit = () => {
    emit('submit');
};
</script>
