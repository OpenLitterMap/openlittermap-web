<template>
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 shadow-2xl">
        <!-- Single Row Header -->
        <div class="px-6 py-3 flex items-center gap-4">
            <!-- Photo ID - fixed width -->
            <div class="w-36 flex items-center gap-2">
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
                    <div class="text-white font-semibold text-sm">#{{ currentPhoto?.id || '—' }}</div>
                    <div class="text-gray-400 text-xs truncate">{{ formatDate(currentPhoto?.datetime) }}</div>
                </div>
            </div>

            <div class="h-8 w-px bg-gray-700"></div>

            <!-- Level - fixed width -->
            <div class="w-24 flex items-center gap-2">
                <div
                    class="bg-gradient-to-r from-yellow-500 to-amber-500 w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                >
                    <span class="text-white font-bold text-xs">{{ userLevel }}</span>
                </div>
                <div>
                    <div class="text-white font-semibold text-sm">Lvl {{ userLevel }}</div>
                    <div class="text-gray-400 text-xs">{{ getLevelTitle() }}</div>
                </div>
            </div>

            <div class="h-8 w-px bg-gray-700"></div>

            <!-- XP Bar - takes remaining space -->
            <div class="flex-1 flex flex-col gap-1 px-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-400">{{ tags.length }} {{ tags.length === 1 ? 'tag' : 'tags' }}</span>
                    <span class="text-gray-400">
                        {{ formatNumber(currentXP)
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

            <div class="h-8 w-px bg-gray-700"></div>

            <!-- Pagination - fixed width -->
            <div class="w-32 flex items-center justify-center gap-2">
                <button
                    @click="$emit('navigate', 'prev')"
                    :disabled="!canGoPrevious"
                    class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="w-16 text-center">
                    <div class="text-white text-xs font-medium">{{ currentNumber }}/{{ totalPhotos }}</div>
                    <div class="text-gray-400 text-xs">{{ untaggedCount }} left</div>
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

            <div class="h-8 w-px bg-gray-700"></div>

            <!-- Actions - fixed width -->
            <div class="w-48 flex items-center justify-end gap-2">
                <button
                    @click="$emit('skip')"
                    class="w-12 py-1.5 bg-gray-700/50 text-gray-300 rounded-lg hover:bg-gray-600/50 transition-all text-xs text-center"
                >
                    Skip
                </button>

                <button
                    @click="$emit('clear')"
                    :class="[
                        'w-12 py-1.5 rounded-lg transition-all text-xs font-semibold text-center',
                        tags.length > 0
                            ? 'bg-red-500/20 text-red-400 hover:bg-red-500/30'
                            : 'bg-gray-700/30 text-gray-500 cursor-not-allowed',
                    ]"
                    :disabled="tags.length === 0"
                >
                    Clear
                </button>

                <button
                    @click="$emit('submit')"
                    :disabled="tags.length === 0 || submitting"
                    class="w-20 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold text-sm disabled:opacity-50 hover:from-green-600 hover:to-green-700 transition-all flex items-center justify-center gap-1"
                >
                    <template v-if="!submitting">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Save</span>
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
import moment from 'moment';

const userStore = useUserStore();

const props = defineProps({
    currentPhoto: Object,
    photos: Object,
    currentIndex: Number,
    tags: { type: Array, default: () => [] },
    xpPreview: { type: Number, default: 0 },
    submitting: { type: Boolean, default: false },
});

defineEmits(['navigate', 'skip', 'clear', 'submit']);

// Navigation
const currentNumber = computed(() => {
    if (!props.photos) return 1;
    return (props.photos.current_page - 1) * props.photos.per_page + props.currentIndex + 1;
});

const totalPhotos = computed(() => props.photos?.total || 0);

const untaggedCount = computed(() => {
    if (!props.photos?.data) return 0;
    return props.photos.data.filter((p) => {
        const hasOld = p.old_tags && Object.keys(p.old_tags).length > 0;
        const hasNew = p.new_tags && p.new_tags.length > 0;
        return !hasOld && !hasNew;
    }).length;
});

const canGoPrevious = computed(() => props.currentIndex > 0 || props.photos?.current_page > 1);
const canGoNext = computed(
    () => props.currentIndex < props.photos?.data?.length - 1 || props.photos?.current_page < props.photos?.last_page
);

// XP
const currentXP = computed(() => userStore.user?.xp_redis || 0);
const xpRequired = computed(() => userStore.user?.next_level?.xp || 1000);
const userLevel = computed(() => userStore.user?.level || 1);

const totalXPProgress = computed(() => Math.min(((currentXP.value + props.xpPreview) / xpRequired.value) * 100, 100));
const existingXPProgress = computed(() => Math.min((currentXP.value / xpRequired.value) * 100, 100));

// Helpers
const formatDate = (datetime) => (datetime ? moment(datetime).format('MMM D, YYYY') : '—');

const formatNumber = (num) => {
    if (num >= 10000) return (num / 1000).toFixed(0) + 'k';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
    return num.toString();
};

const getLevelTitle = () => {
    return 'todo';
};
</script>
