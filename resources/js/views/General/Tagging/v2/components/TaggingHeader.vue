<template>
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 shadow-2xl">
        <!-- Header - wraps on mobile -->
        <div class="px-4 py-2 md:px-6 md:py-3 flex flex-wrap items-center gap-2 md:gap-4">
            <!-- Photo ID (only when a photo exists) -->
            <div v-if="currentPhoto" class="flex items-center gap-2">
                <div class="bg-blue-500/20 p-1.5 rounded-lg">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-white font-semibold text-sm">#{{ currentPhoto.id }}</div>
                    <div class="text-gray-400 text-xs truncate">{{ formatDate(currentPhoto.datetime) }}</div>
                </div>
            </div>

            <div class="hidden md:block h-8 w-px bg-gray-700"></div>

            <!-- Level -->
            <div class="hidden md:flex items-center gap-2">
                <div
                    class="bg-gradient-to-r from-yellow-500 to-amber-500 w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                >
                    <span class="text-white font-bold text-xs">{{ userLevel }}</span>
                </div>
                <div>
                    <div class="text-white font-semibold text-sm">{{ $t('Lvl') }} {{ userLevel }}</div>
                    <div class="text-gray-400 text-xs truncate">{{ getLevelTitle() }}</div>
                </div>
            </div>

            <div class="hidden md:block h-8 w-px bg-gray-700"></div>

            <!-- XP Bar - takes remaining space on desktop, full width on mobile -->
            <div class="flex-1 min-w-[120px] flex flex-col gap-1 px-1 md:px-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-400">{{ tags.length }} {{ tags.length === 1 ? $t('tag') : $t('tags') }}</span>
                    <span class="text-gray-400">
                        {{ formatNumber(xpIntoLevel)
                        }}<span v-if="xpPreview > 0" class="text-green-400 ml-0.5">+{{ xpPreview }}</span> /
                        {{ formatNumber(xpRequired) }} XP
                    </span>
                </div>
                <div class="relative h-1.5 bg-gray-900/70 rounded-full overflow-hidden">
                    <div
                        class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-600 to-blue-400 transition-all duration-500"
                        :style="{ width: existingXPProgress + '%' }"
                    />
                    <div
                        v-if="xpPreview > 0"
                        class="absolute top-0 h-full bg-gradient-to-r from-green-500 to-green-400 transition-all duration-500"
                        :style="{
                            left: existingXPProgress + '%',
                            width: Math.max(0, totalXPProgress - existingXPProgress) + '%',
                        }"
                    />
                </div>
            </div>

            <div class="hidden md:block h-8 w-px bg-gray-700"></div>

            <!-- Pagination (hidden in edit mode) -->
            <div v-if="!isEditMode" class="flex items-center justify-center gap-2">
                <button
                    @click="$emit('navigate', 'prev')"
                    :disabled="!canGoPrevious"
                    class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="text-center">
                    <template v-if="totalPhotos > 0">
                        <div class="text-white text-xs font-medium">{{ currentNumber }}/{{ totalPhotos }}</div>
                        <div class="text-gray-400 text-xs">{{ untaggedCount }} {{ $t('left') }}</div>
                    </template>
                    <template v-else>
                        <div class="text-gray-400 text-xs">{{ $t('Nothing to tag') }}</div>
                    </template>
                </div>

                <button
                    @click="$emit('navigate', 'next')"
                    :disabled="!canGoNext"
                    class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <!-- Edit mode label -->
            <div v-if="isEditMode" class="flex items-center gap-1">
                <span class="px-2 py-1 bg-amber-500/20 text-amber-400 text-xs font-semibold rounded">
                    {{ $t('Editing') }}
                </span>
            </div>

            <!-- Unresolved tags warning -->
            <span
                v-if="hasUnresolvedTags"
                class="flex items-center gap-1 text-red-400 text-xs font-medium"
                :title="$t('Some tags need a category selected')"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <span class="hidden sm:inline">{{ $t('Unresolved') }}</span>
            </span>

            <div class="hidden md:block h-8 w-px bg-gray-700"></div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button
                    v-if="!isEditMode"
                    @click="$emit('skip')"
                    :disabled="!canGoNext"
                    class="px-3 py-1.5 bg-gray-700/50 text-gray-300 rounded-lg hover:bg-gray-600/50 disabled:opacity-30 disabled:cursor-not-allowed transition-all text-xs text-center"
                >
                    {{ $t('Skip') }}
                </button>

                <button
                    @click="$emit('clear')"
                    :class="[
                        'px-3 py-1.5 rounded-lg transition-all text-xs font-semibold text-center',
                        tags.length > 0
                            ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                            : 'bg-gray-700/30 text-gray-500 cursor-not-allowed',
                    ]"
                    :disabled="tags.length === 0"
                >
                    {{ $t('Clear') }}
                </button>

                <button
                    @click="$emit('submit')"
                    :disabled="tags.length === 0 || submitting || hasUnresolvedTags"
                    class="px-4 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold text-sm disabled:opacity-50 hover:from-green-600 hover:to-green-700 transition-all flex items-center justify-center gap-1"
                >
                    <template v-if="!submitting">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ isEditMode ? $t('Update') : $t('Save') }}</span>
                    </template>
                    <template v-else>
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useUserStore } from '@stores/user/index.js';
import { usePhotosStore } from '@stores/photos/index.js';
import moment from 'moment';

const userStore = useUserStore();
const photosStore = usePhotosStore();

const props = defineProps({
    currentPhoto: Object,
    photos: Object,
    currentIndex: Number,
    tags: { type: Array, default: () => [] },
    xpPreview: { type: Number, default: 0 },
    submitting: { type: Boolean, default: false },
    hasUnresolvedTags: { type: Boolean, default: false },
    isEditMode: { type: Boolean, default: false },
});

defineEmits(['navigate', 'skip', 'clear', 'submit']);

// Navigation
const currentNumber = computed(() => {
    if (!props.photos) return 1;
    return (props.photos.current_page - 1) * props.photos.per_page + props.currentIndex + 1;
});

const totalPhotos = computed(() => props.photos?.total || 0);

const untaggedCount = computed(() => photosStore.untaggedStats.leftToTag ?? props.photos?.total ?? 0);

const canGoPrevious = computed(() => props.currentIndex > 0 || props.photos?.current_page > 1);
const canGoNext = computed(
    () => props.currentIndex < props.photos?.data?.length - 1 || props.photos?.current_page < props.photos?.last_page
);

// XP — uses LevelService threshold data from next_level accessor
const levelInfo = computed(() => userStore.user?.next_level || {});
const currentXP = computed(() => userStore.user?.xp_redis || 0);
const xpRequired = computed(() => levelInfo.value.xp_for_next || 1000);
const userLevel = computed(() => levelInfo.value.level || 1);
const xpIntoLevel = computed(() => levelInfo.value.xp_into_level || 0);

const existingXPProgress = computed(() => xpRequired.value > 0 ? Math.min((xpIntoLevel.value / xpRequired.value) * 100, 100) : 100);
const totalXPProgress = computed(() => xpRequired.value > 0 ? Math.min(((xpIntoLevel.value + props.xpPreview) / xpRequired.value) * 100, 100) : 100);

// Helpers
const formatDate = (datetime) => (datetime ? moment(datetime).format('MMM D, YYYY') : '—');

const formatNumber = (num) => {
    if (num >= 10000) return (num / 1000).toFixed(0) + 'k';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
    return num.toString();
};

const getLevelTitle = () => {
    return userStore.user?.next_level?.title || 'Complete Noob';
};
</script>
