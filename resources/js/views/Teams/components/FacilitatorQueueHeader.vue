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
            </div>
        </div>

        <!-- Center: Navigation -->
        <div class="flex items-center gap-2">
            <button
                @click="$emit('navigate', 'prev')"
                :disabled="!canGoPrev"
                class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Previous (J / ArrowLeft)"
            >
                <span class="hidden sm:inline text-gray-500 text-xs mr-1">J</span> Prev
            </button>
            <span class="text-gray-400 text-sm">
                {{ currentIndex + 1 }} / {{ totalOnPage }}
            </span>
            <button
                @click="$emit('navigate', 'next')"
                :disabled="!canGoNext"
                class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Next / Skip (K / S / ArrowRight)"
            >
                Next <span class="hidden sm:inline text-gray-500 text-xs ml-1">K</span>
            </button>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-2">
            <button
                @click="$emit('approve')"
                :disabled="!photo || submitting"
                class="px-4 py-1.5 text-sm font-medium bg-green-600 text-white rounded hover:bg-green-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Approve (A)"
            >
                {{ submitting ? 'Working...' : 'Approve' }}
                <span class="hidden sm:inline text-green-300 text-xs ml-1">A</span>
            </button>
            <button
                @click="$emit('save-edits')"
                :disabled="!photo || submitting || !hasEdits"
                class="px-4 py-1.5 text-sm font-medium bg-blue-600 text-white rounded hover:bg-blue-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Save Edits (E)"
            >
                Save Edits
                <span class="hidden sm:inline text-blue-300 text-xs ml-1">E</span>
            </button>
            <button
                @click="confirmRevoke"
                :disabled="!photo || submitting"
                class="px-4 py-1.5 text-sm font-medium bg-amber-600 text-white rounded hover:bg-amber-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Revoke Approval (R)"
            >
                Revoke
                <span class="hidden sm:inline text-amber-300 text-xs ml-1">R</span>
            </button>
            <button
                @click="confirmDelete"
                :disabled="!photo || submitting"
                class="px-4 py-1.5 text-sm font-medium bg-red-600 text-white rounded hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                title="Delete (D)"
            >
                Delete
                <span class="hidden sm:inline text-red-300 text-xs ml-1">D</span>
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

const emit = defineEmits(['navigate', 'approve', 'save-edits', 'revoke', 'delete']);

const confirmRevoke = () => {
    if (window.confirm('Revoke approval on this photo? It will become private again.')) {
        emit('revoke');
    }
};

const confirmDelete = () => {
    if (window.confirm('Are you sure you want to delete this photo? This cannot be undone.')) {
        emit('delete');
    }
};
</script>
