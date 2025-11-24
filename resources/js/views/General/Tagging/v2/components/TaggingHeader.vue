<template>
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 shadow-2xl">
        <!-- Main Header Content -->
        <div class="px-6 py-3 grid grid-cols-5 items-center gap-4">
            <!-- Left Section: Photo ID (1/5) -->
            <div class="flex items-center gap-2">
                <div class="bg-blue-500/20 p-1.5 rounded-lg flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </div>
                <div>
                    <div class="text-white font-semibold text-sm">Photo #{{ currentPhoto?.id || '—' }}</div>
                    <div class="text-gray-400 text-xs">{{ formatDate(currentPhoto?.datetime) }}</div>
                </div>
            </div>

            <!-- Team Section (1/5) -->
            <div class="border-l border-gray-700 pl-4">
                <div class="text-gray-400 text-xs">Team</div>
                <div class="text-white text-sm font-medium">
                    {{ currentPhoto?.team?.name || 'Solo' }}
                </div>
            </div>

            <!-- Center: Navigation (1/5) -->
            <div class="flex items-center justify-center gap-2">
                <button
                    @click="$emit('navigate', 'prev')"
                    :disabled="!canGoPrevious"
                    class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                >
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <div class="px-3 py-1 bg-white/10 rounded-lg">
                    <div class="text-white text-xs font-medium text-center">{{ currentNumber }}/{{ totalPhotos }}</div>
                    <div class="text-gray-400 text-xs text-center">{{ untaggedCount }} left</div>
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

            <!-- Right Section: Actions (2/5) -->
            <div class="col-span-2 flex items-center justify-end gap-3">
                <!-- Skip Button -->
                <button
                    @click="$emit('skip')"
                    class="px-3 py-1.5 bg-gray-700/50 text-gray-300 rounded-lg hover:bg-gray-600/50 transition-all text-xs"
                >
                    Skip
                </button>

                <!-- Divider -->
                <div class="h-8 w-px bg-gray-600"></div>

                <!-- Clear Button -->
                <button
                    v-if="tags.length > 0"
                    @click="$emit('clear')"
                    class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-all text-xs font-semibold"
                >
                    Clear
                </button>

                <!-- Submit Button -->
                <button
                    @click="$emit('submit')"
                    :disabled="tags.length === 0 || submitting"
                    class="px-5 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold text-sm disabled:opacity-50 hover:from-green-600 hover:to-green-700 transition-all shadow-lg flex items-center gap-2"
                >
                    <span v-if="!submitting" class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Submit
                        <span class="text-xs opacity-70">
                            {{ keyboardShortcut }}
                        </span>
                    </span>
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
                </button>
            </div>
        </div>

        <!-- XP Progress Bar Section -->
        <div
            v-if="tags.length > 0 || xpPreview > 0"
            class="relative bg-gradient-to-r from-gray-900/50 via-gray-800/50 to-gray-900/50 px-6 py-3 border-t border-gray-700/50"
        >
            <!-- Level & XP Info Row -->
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-4">
                    <!-- Level Badge -->
                    <div class="flex items-center gap-2">
                        <div
                            class="bg-gradient-to-r from-yellow-500 to-amber-500 w-8 h-8 rounded-lg flex items-center justify-center shadow-lg"
                        >
                            <span class="text-white font-bold text-sm">{{ userLevel }}</span>
                        </div>
                        <div>
                            <div class="text-white font-bold text-sm">Level {{ userLevel }}</div>
                            <div class="text-gray-400 text-xs">{{ getLevelTitle() }}</div>
                        </div>
                    </div>

                    <!-- XP Values -->
                    <div class="text-sm">
                        <span class="text-gray-400">XP:</span>
                        <span class="text-white font-semibold ml-1">{{ formatNumber(currentXP) }}</span>
                        <span class="text-green-400 font-bold ml-1" v-if="xpPreview > 0"> (+{{ xpPreview }}) </span>
                        <span class="text-gray-500 mx-1">/</span>
                        <span class="text-gray-300">{{ formatNumber(xpRequired) }}</span>
                    </div>
                </div>

                <!-- Middle: Tags Counter -->
                <div v-if="tags.length > 0" class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    <span class="text-white text-sm font-semibold">
                        {{ tags.length }} {{ tags.length === 1 ? 'tag' : 'tags' }}
                    </span>
                </div>

                <!-- Percentage & Remaining -->
                <div class="text-right">
                    <div class="text-white font-bold text-base">{{ xpProgress }}%</div>
                    <div class="text-gray-400 text-xs">{{ xpToNextLevel }} XP to next level</div>
                </div>
            </div>

            <!-- Horizontal Progress Bar -->
            <div class="relative h-3 bg-gray-900/70 rounded-full overflow-hidden shadow-inner">
                <!-- Background pattern -->
                <div class="absolute inset-0 opacity-20">
                    <div
                        class="h-full w-full"
                        style="
                            background-image: repeating-linear-gradient(
                                45deg,
                                transparent,
                                transparent 10px,
                                rgba(255, 255, 255, 0.05) 10px,
                                rgba(255, 255, 255, 0.05) 20px
                            );
                        "
                    ></div>
                </div>

                <!-- Existing XP (blue) -->
                <div
                    class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-blue-400 transition-all duration-700"
                    :style="{ width: existingXPProgress + '%' }"
                >
                    <div class="absolute inset-0 bg-gradient-to-t from-transparent via-transparent to-white/20"></div>
                </div>

                <!-- New XP (green with glow) -->
                <div
                    v-if="xpPreview > 0"
                    class="absolute top-0 h-full bg-gradient-to-r from-green-500 to-green-400 transition-all duration-700 shadow-lg"
                    :style="{
                        left: existingXPProgress + '%',
                        width: Math.max(0, totalXPProgress - existingXPProgress) + '%',
                    }"
                >
                    <div class="absolute inset-0 bg-gradient-to-t from-transparent via-transparent to-white/30"></div>
                    <div class="absolute inset-0 animate-pulse">
                        <div
                            class="h-full w-full bg-gradient-to-r from-transparent via-green-300/30 to-transparent"
                        ></div>
                    </div>
                </div>

                <!-- Current position marker -->
                <div
                    class="absolute -top-1 -bottom-1 w-1 bg-white shadow-xl transition-all duration-700"
                    :style="{ left: `calc(${totalXPProgress}% - 2px)` }"
                >
                    <div class="absolute inset-0 bg-white blur-sm"></div>
                    <div
                        class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-3 h-3 bg-white rounded-full"
                    ></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useUserStore } from '@stores/user/index.js';
import moment from 'moment';

const userStore = useUserStore();

const props = defineProps({
    currentPhoto: Object,
    photos: Object,
    currentIndex: Number,
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

const emit = defineEmits(['navigate', 'skip', 'clear', 'submit']);

// OS Detection for keyboard shortcut
const isMac = ref(false);
onMounted(() => {
    isMac.value = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
});

const keyboardShortcut = computed(() => {
    return isMac.value ? '⌘+Enter' : 'Ctrl+Enter';
});

// Navigation computed properties
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

// XP Calculations
const currentXP = computed(() => userStore.user?.xp_redis || 0);
const xpRequired = computed(() => userStore.user?.next_level?.xp || 1000);
const userLevel = computed(() => userStore.user?.level || 1);

const totalXP = computed(() => currentXP.value + props.xpPreview);
const totalXPProgress = computed(() => Math.round(Math.min((totalXP.value / xpRequired.value) * 100, 100)));
const existingXPProgress = computed(() => Math.round(Math.min((currentXP.value / xpRequired.value) * 100, 100)));
const xpProgress = computed(() => totalXPProgress.value);
const xpToNextLevel = computed(() => formatNumber(Math.max(0, xpRequired.value - totalXP.value)));

// Helper functions
const formatDate = (datetime) => {
    if (!datetime) return '—';
    return moment(datetime).format('MMM D, YYYY • h:mm A');
};

const formatNumber = (num) => {
    if (num >= 10000) return (num / 1000).toFixed(0) + 'k';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
    return num.toString();
};

const getLevelTitle = () => {
    if (userLevel.value < 5) return 'Beginner';
    if (userLevel.value < 10) return 'Contributor';
    if (userLevel.value < 20) return 'Expert';
    if (userLevel.value < 50) return 'Master';
    return 'Legend';
};
</script>
