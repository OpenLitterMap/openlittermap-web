<template>
    <div class="bg-gray-800 border-b border-gray-700 px-6 py-3">
        <div class="flex items-center justify-between">
            <!-- Left: Photo Info -->
            <div class="flex items-center gap-4">
                <div class="text-sm">
                    <span class="text-gray-400">Photo</span>
                    <span class="text-white font-semibold ml-2"> #{{ currentPhoto?.id || '—' }} </span>
                </div>

                <div class="text-sm text-gray-400">
                    {{ formatDate(currentPhoto?.datetime) }}
                </div>
            </div>

            <!-- Center: Navigation -->
            <div class="flex items-center gap-3">
                <button
                    @click="$emit('navigate', 'prev')"
                    :disabled="!canGoPrevious"
                    class="p-2 bg-gray-700 rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="px-4 py-1 bg-gray-700 rounded text-center">
                    <div class="text-white font-medium">{{ currentNumber }} / {{ totalPhotos }}</div>
                    <div class="text-gray-400 text-xs">{{ untaggedCount }} untagged</div>
                </div>

                <button
                    @click="$emit('navigate', 'next')"
                    :disabled="!canGoNext"
                    class="p-2 bg-gray-700 rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Right: Skip Button -->
            <button
                @click="$emit('skip')"
                class="px-4 py-2 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors"
            >
                Skip
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import moment from 'moment';

const props = defineProps({
    currentPhoto: Object,
    photos: Object,
    currentIndex: Number,
});

const emit = defineEmits(['navigate', 'skip']);

const currentNumber = computed(() => {
    if (!props.photos) return 1;
    const pageOffset = (props.photos.current_page - 1) * props.photos.per_page;
    return pageOffset + props.currentIndex + 1;
});

const totalPhotos = computed(() => props.photos?.total || 0);

const untaggedCount = computed(() => {
    if (!props.photos?.data) return 0;
    return props.photos.data.filter((photo) => {
        const hasOldTags = photo.old_tags && Object.keys(photo.old_tags).length > 0;
        const hasNewTags = photo.new_tags && photo.new_tags.length > 0;
        return !hasOldTags && !hasNewTags;
    }).length;
});

const canGoPrevious = computed(() => {
    return props.currentIndex > 0 || props.photos?.current_page > 1;
});

const canGoNext = computed(() => {
    return props.currentIndex < props.photos?.data?.length - 1 || props.photos?.current_page < props.photos?.last_page;
});

const formatDate = (datetime) => {
    if (!datetime) return '—';
    return moment(datetime).format('MMM D, YYYY • h:mm A');
};
</script>
