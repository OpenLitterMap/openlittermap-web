<template>
    <div class="bg-gray-800 border-b border-gray-700 px-6 py-3 flex items-center justify-between flex-shrink-0">
        <!-- Left: Info -->
        <div class="flex items-center gap-4">
            <div class="text-sm">
                <span class="text-yellow-400 font-semibold">{{ pendingCount }}</span>
                <span class="text-gray-400"> pending</span>
            </div>

            <div v-if="photo" class="flex items-center gap-3 text-sm text-gray-400">
                <span>
                    Photo <span class="text-white font-mono">#{{ photo.id }}</span>
                </span>
                <span v-if="photo.user">
                    by <span class="text-gray-300">{{ photo.user.name }}</span>
                </span>
                <span v-if="photo.country_relation" class="text-gray-500">
                    {{ photo.country_relation.shortcode }}
                </span>
            </div>
        </div>

        <!-- Center: Navigation -->
        <div class="flex items-center gap-2">
            <button
                @click="$emit('navigate', 'prev')"
                :disabled="!canGoPrev"
                class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Prev
            </button>
            <span class="text-gray-400 text-sm">
                {{ currentIndex + 1 }} / {{ totalOnPage }}
            </span>
            <button
                @click="$emit('navigate', 'next')"
                :disabled="!canGoNext"
                class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Next
            </button>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-2">
            <button
                @click="$emit('approve')"
                :disabled="!photo || submitting"
                class="px-4 py-1.5 text-sm font-medium bg-green-600 text-white rounded hover:bg-green-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                {{ submitting ? 'Working...' : 'Approve' }}
            </button>
            <button
                @click="$emit('save-edits')"
                :disabled="!photo || submitting || !hasEdits"
                class="px-4 py-1.5 text-sm font-medium bg-blue-600 text-white rounded hover:bg-blue-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Save Edits
            </button>
            <button
                @click="confirmDelete"
                :disabled="!photo || submitting"
                class="px-4 py-1.5 text-sm font-medium bg-red-600 text-white rounded hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Delete
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    photo: {
        type: Object,
        default: null,
    },
    pendingCount: {
        type: Number,
        default: 0,
    },
    currentIndex: {
        type: Number,
        default: 0,
    },
    totalOnPage: {
        type: Number,
        default: 0,
    },
    canGoPrev: {
        type: Boolean,
        default: false,
    },
    canGoNext: {
        type: Boolean,
        default: false,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    hasEdits: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate', 'approve', 'save-edits', 'delete']);

const confirmDelete = () => {
    if (window.confirm('Are you sure you want to delete this photo? This cannot be undone.')) {
        emit('delete');
    }
};
</script>
