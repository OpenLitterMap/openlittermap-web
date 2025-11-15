<template>
    <div class="bg-gradient-to-r from-gray-900 via-gray-800 to-gray-900 rounded-xl shadow-2xl overflow-visible">
        <!-- Main Header Content - Using Grid for Fixed Positioning -->
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
                    <div class="text-gray-400 text-xs">{{ getShortDate() }}</div>
                </div>
            </div>

            <!-- Team Section (1/5) -->
            <div class="border-l border-gray-700 pl-4">
                <div class="text-gray-400 text-xs">Team</div>
                <div class="text-white text-sm font-medium">
                    {{ currentPhoto?.team?.name || 'Solo' }}
                </div>
            </div>

            <!-- Empty spacer (1/5) -->
            <div></div>

            <!-- Right Section - Navigation & Actions (2/5) -->
            <div class="col-span-2 flex items-center justify-end gap-3">
                <!-- Navigation -->
                <div class="flex items-center gap-2">
                    <button
                        @click="previousPhoto"
                        :disabled="!canGoPrevious"
                        class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                    >
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <div class="px-3 py-1 bg-white/10 rounded-lg">
                        <div class="text-white text-xs font-medium text-center">
                            {{ currentPhotoNumber }}/{{ totalPhotos }}
                        </div>
                        <div class="text-gray-400 text-xs text-center">{{ photosWithoutTags }} left</div>
                    </div>

                    <button
                        @click="nextPhoto"
                        :disabled="!canGoNext"
                        class="p-1.5 bg-white/10 rounded-lg hover:bg-white/20 disabled:opacity-30 disabled:cursor-not-allowed transition-all"
                    >
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Divider -->
                <div class="h-8 w-px bg-gray-600"></div>

                <!-- Clear Button -->
                <button
                    v-if="newTags.length"
                    @click="$emit('clearTags')"
                    class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-all text-xs font-semibold"
                >
                    Clear
                </button>

                <!-- Submit Button -->
                <button
                    @click="submit"
                    :disabled="!newTags.length"
                    class="px-5 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-lg font-semibold text-sm disabled:opacity-50 hover:from-green-600 hover:to-green-700 transition-all shadow-lg"
                    v-tooltip="!newTags.length ? 'Add tags to submit' : ''"
                >
                    <span v-if="!isUploading" class="flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Submit
                    </span>
                    <Spinner v-else class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- XP Progress Bar Section -->
        <div
            class="relative bg-gradient-to-r from-gray-900/50 via-gray-800/50 to-gray-900/50 px-6 py-3 border-t border-gray-700/50"
        >
            <!-- Level & XP Info Row with Tags Counter -->
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
                        <span class="text-green-400 font-bold ml-1" v-if="newXP > 0">(+{{ newXP }})</span>
                        <span class="text-gray-500 mx-1">/</span>
                        <span class="text-gray-300">{{ formatNumber(xpRequired) }}</span>
                    </div>
                </div>

                <!-- Middle: Tags Counter -->
                <div v-if="newTags.length" class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-lg">
                    <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    <span class="text-white text-sm font-semibold">
                        {{ newTags.length }} {{ newTags.length === 1 ? 'tag' : 'tags' }}
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
                    v-if="newXP > 0"
                    class="absolute top-0 h-full bg-gradient-to-r from-green-500 to-green-400 transition-all duration-700 shadow-lg"
                    :style="{
                        left: existingXPProgress + '%',
                        width: Math.max(0, xpProgress - existingXPProgress) + '%',
                    }"
                >
                    <div class="absolute inset-0 bg-gradient-to-t from-transparent via-transparent to-white/30"></div>
                    <!-- Animated glow effect -->
                    <div class="absolute inset-0 animate-pulse">
                        <div
                            class="h-full w-full bg-gradient-to-r from-transparent via-green-300/30 to-transparent"
                        ></div>
                    </div>
                </div>

                <!-- Current position marker -->
                <div
                    class="absolute -top-1 -bottom-1 w-1 bg-white shadow-xl transition-all duration-700"
                    :style="{ left: `calc(${xpProgress}% - 2px)` }"
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
import { computed, defineProps, defineEmits, ref } from 'vue';
import { usePhotosStore } from '../../../../stores/photos/index.js';
import { useUserStore } from '../../../../stores/user/index.js';
import Spinner from '../../../../components/Loading/Spinner.vue';
import moment from 'moment';

const photosStore = usePhotosStore();
const userStore = useUserStore();

const props = defineProps({
    newTags: {
        type: Array,
        required: true,
    },
    paginatedPhotos: {
        type: Object,
        required: true,
    },
    currentPhotoIndex: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['clearTags', 'update:currentPhotoIndex']);

// Current photo from the array
const currentPhoto = computed(() => props.paginatedPhotos?.data?.[props.currentPhotoIndex]);

// Navigation computed properties
const totalPhotos = computed(() => props.paginatedPhotos?.total || 0);
const photosOnCurrentPage = computed(() => props.paginatedPhotos?.data?.length || 0);
const currentPhotoNumber = computed(() => {
    if (!props.paginatedPhotos) return 1;
    const pageOffset = (props.paginatedPhotos.current_page - 1) * props.paginatedPhotos.per_page;
    return pageOffset + props.currentPhotoIndex + 1;
});

// Count photos without tags
const photosWithoutTags = computed(() => {
    if (!props.paginatedPhotos?.data) return 0;
    return props.paginatedPhotos.data.filter((photo) => {
        // Check if photo has no tags (both old and new)
        const hasOldTags = photo.old_tags && Object.keys(photo.old_tags).length > 0;
        const hasNewTags = photo.new_tags && photo.new_tags.length > 0;
        return !hasOldTags && !hasNewTags;
    }).length;
});

// Navigation state
const canGoPrevious = computed(() => {
    return props.currentPhotoIndex > 0 || props.paginatedPhotos?.current_page > 1;
});

const canGoNext = computed(() => {
    return (
        props.currentPhotoIndex < photosOnCurrentPage.value - 1 ||
        props.paginatedPhotos?.current_page < props.paginatedPhotos?.last_page
    );
});

// Navigation methods
const previousPhoto = async () => {
    if (props.currentPhotoIndex > 0) {
        // Go to previous photo on current page
        emit('update:currentPhotoIndex', props.currentPhotoIndex - 1);
    } else if (props.paginatedPhotos?.current_page > 1) {
        // Load previous page and go to last photo
        await photosStore.GET_USERS_PHOTOS(props.paginatedPhotos.current_page - 1, { tagged: false });
        // After loading, set to last photo of that page
        const lastIndex = photosStore.paginated?.data?.length - 1 || 0;
        emit('update:currentPhotoIndex', lastIndex);
    }
};

const nextPhoto = async () => {
    if (props.currentPhotoIndex < photosOnCurrentPage.value - 1) {
        // Go to next photo on current page
        emit('update:currentPhotoIndex', props.currentPhotoIndex + 1);
    } else if (props.paginatedPhotos?.current_page < props.paginatedPhotos?.last_page) {
        // Load next page and go to first photo
        await photosStore.GET_USERS_PHOTOS(props.paginatedPhotos.current_page + 1, { tagged: false });
        emit('update:currentPhotoIndex', 0);
    }
};

// XP Calculations
const currentXP = ref(userStore.user?.xp_redis || 0);
const xpRequired = ref(userStore.user?.next_level?.xp || 1000);
const userLevel = ref(userStore.user?.level || 1);

const calculateXP = (tags) => {
    let totalXP = 0;
    tags.forEach((tag) => {
        totalXP += tag.quantity || 0;
        if (tag.extraTags) {
            tag.extraTags.forEach((extra) => {
                if (extra.selected) {
                    totalXP++;
                }
            });
        }
    });
    return totalXP;
};

const newXP = computed(() => calculateXP(props.newTags));
const totalXP = computed(() => currentXP.value + newXP.value);
const xpProgress = computed(() => Math.round(Math.min((totalXP.value / xpRequired.value) * 100, 100)));
const existingXPProgress = computed(() => Math.round(Math.min((currentXP.value / xpRequired.value) * 100, 100)));
const xpToNextLevel = computed(() => formatNumber(Math.max(0, xpRequired.value - totalXP.value)));

const isUploading = ref(false);

// Helper functions
const formatNumber = (num) => {
    if (num >= 10000) {
        return (num / 1000).toFixed(0) + 'k';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'k';
    }
    return num.toString();
};

const getShortDate = () => {
    const datetime = currentPhoto.value?.datetime;
    return datetime ? moment(datetime).format('MMM D, YYYY • h:mm A') : '—';
};

const getLevelTitle = () => {
    if (userLevel.value < 5) return 'Beginner';
    if (userLevel.value < 10) return 'Contributor';
    if (userLevel.value < 20) return 'Expert';
    if (userLevel.value < 50) return 'Master';
    return 'Legend';
};

const prepareTagsForUpload = () => {
    return props.newTags.map((tag) => {
        const materials =
            tag.extraTags
                ?.filter((extraTag) => extraTag.selected && extraTag.type === 'material')
                .map((extraTag) => ({
                    id: extraTag.id,
                    key: extraTag.key,
                })) || [];

        const custom_tags =
            tag.extraTags
                ?.filter((extraTag) => extraTag.selected && extraTag.type === 'custom')
                .map((extraTag) => extraTag.key) || [];

        if (tag.hasOwnProperty('custom') && tag.custom) {
            return {
                custom: true,
                key: tag.key,
                picked_up: tag.pickedUp,
                quantity: tag.quantity || 1,
                materials,
                custom_tags,
            };
        } else {
            return {
                category: { id: tag.category.id, key: tag.category.key },
                object: { id: tag.object.id, key: tag.object.key },
                quantity: tag.quantity || 1,
                picked_up: tag.pickedUp,
                materials,
                custom_tags,
            };
        }
    });
};

const submit = async () => {
    isUploading.value = true;
    const tags = prepareTagsForUpload();

    await photosStore.UPLOAD_TAGS({
        photoId: currentPhoto.value.id,
        tags,
    });

    isUploading.value = false;
    emit('clearTags');

    // After submission, auto-advance to next photo if available
    if (canGoNext.value) {
        await nextPhoto();
    }
};
</script>

<style scoped>
/* Keep animations smooth */
@keyframes shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.animate-shine {
    animation: shine 2s infinite;
}
</style>
