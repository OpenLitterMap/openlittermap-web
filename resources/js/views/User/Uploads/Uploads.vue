<template>
    <div class="min-h-[calc(100vh-73px)] bg-gray-800 p-6">
        <UploadsHeader />

        <!-- Empty State -->
        <div v-if="!store.loading.photos && photos.length === 0" class="text-center py-16">
            <p class="text-gray-400 text-lg">{{ $t("You haven't uploaded any photos yet.") }}</p>
            <router-link to="/upload" class="inline-block mt-4 px-6 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded transition-colors">
                {{ $t('Upload Photos') }}
            </router-link>
        </div>

        <!-- Photos Grid -->
        <div v-if="photos.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
            <div
                v-for="photo in photos"
                :key="photo.id"
                class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-lg transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
                :class="{ 'border-l-4 border-green-500': photo.is_migrated }"
                @click="openTags(photo)"
            >
                <!-- Photo Thumbnail -->
                <div class="relative h-44 bg-gray-100">
                    <img :src="photo.filename" :alt="`Photo ${photo.id}`" class="w-full h-full object-cover" />

                    <!-- Untagged badge -->
                    <span
                        v-if="!photo.total_tags"
                        class="absolute top-2 left-2 px-2 py-0.5 bg-yellow-500 text-white text-xs font-semibold rounded"
                    >
                        {{ $t('Untagged') }}
                    </span>
                </div>

                <!-- Photo Info -->
                <div class="p-3">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-1">
                            <span
                                class="font-bold text-gray-800 cursor-pointer hover:text-blue-600 transition-colors"
                                @click.stop="copyPhotoLink(photo)"
                                :title="$t('Copy location link')"
                            >
                                #{{ photo.id }}
                            </span>
                            <button
                                class="text-gray-600 hover:text-blue-600 transition-colors p-1"
                                @click.stop="copyPhotoLink(photo)"
                                :title="$t('Copy location link')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                </svg>
                            </button>
                            <button
                                class="text-gray-600 hover:text-blue-600 transition-colors p-1"
                                @click.stop="openPhotoLink(photo)"
                                :title="$t('Open location in new tab')"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                </svg>
                            </button>
                            <button
                                class="text-red-400 hover:text-red-600 transition-colors p-1"
                                @click.stop="confirmDelete(photo)"
                                :title="$t('Delete photo')"
                                :disabled="deleting[photo.id]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <span v-if="copyLinkStatus[photo.id]" class="text-xs text-green-600 font-medium">
                                {{ copyLinkStatus[photo.id] }}
                            </span>
                        </div>
                        <span class="text-sm text-gray-600">{{ formatDate(photo.datetime) }}</span>
                    </div>

                    <!-- Tags Summary -->
                    <div v-if="photo.total_tags > 0" class="grid grid-cols-2 gap-3 p-2 bg-gray-50 rounded">
                        <!-- Stats Column -->
                        <div class="flex flex-col gap-1">
                            <div v-if="getObjectCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">{{ $t('Objects') }}:</span>
                                <span class="font-semibold text-gray-800">{{ getObjectCount(photo) }}</span>
                            </div>
                            <div v-if="getBrandCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">{{ $t('Brands') }}:</span>
                                <span class="font-semibold text-gray-800">{{ getBrandCount(photo) }}</span>
                            </div>
                            <div v-if="getMaterialCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">{{ $t('Materials') }}:</span>
                                <span class="font-semibold text-gray-800">{{ getMaterialCount(photo) }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">{{ $t('Total tags') }}:</span>
                                <span class="font-semibold text-gray-800">{{ photo.total_tags }}</span>
                            </div>
                        </div>

                        <!-- Tags List Column -->
                        <div class="flex flex-col gap-0.5">
                            <p class="text-xs mb-1">{{ $t('Tags') }}:</p>
                            <div
                                v-for="obj in getTagsList(photo)"
                                :key="obj.key"
                                class="flex items-center gap-1 px-1 py-0.5 bg-white rounded text-xs text-gray-700"
                                :title="`${obj.category ? obj.category + ' / ' : ''}${obj.label} (x${obj.quantity})`"
                            >
                                <span class="truncate">{{ obj.label }} x{{ obj.quantity }}</span>
                                <span
                                    v-if="obj.pickedUp === true"
                                    class="shrink-0 px-1 py-px rounded text-[10px] font-medium bg-green-100 text-green-700"
                                >{{ $t('picked up') }}</span>
                                <span
                                    v-else-if="obj.pickedUp === false"
                                    class="shrink-0 px-1 py-px rounded text-[10px] font-medium bg-amber-100 text-amber-700"
                                >{{ $t('not picked up') }}</span>
                                <span
                                    v-else
                                    class="shrink-0 px-1 py-px rounded text-[10px] font-medium bg-gray-100 text-gray-500"
                                >{{ $t('unknown') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Untagged: show tag prompt -->
                    <div v-else class="p-2 bg-yellow-50 rounded text-center">
                        <router-link
                            :to="{ path: '/tag', query: { photo: photo.id } }"
                            class="text-sm text-yellow-700 hover:text-yellow-900 font-medium"
                            @click.stop
                        >
                            {{ $t('Tag this photo') }}
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <UploadsPagination />

        <!-- UploadTags Modal -->
        <UploadTags v-if="selectedPhoto" :photo="selectedPhoto" @close="selectedPhoto = null" />

        <!-- Delete Confirmation Modal -->
        <div v-if="photoToDelete" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="photoToDelete = null">
            <div class="bg-white rounded-lg p-6 max-w-sm mx-4" @click.stop>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $t('Delete Photo') }}</h3>
                <p class="text-gray-600 mb-4">{{ $t('Delete this photo? This will reverse any XP and metrics. This cannot be undone.') }}</p>
                <div class="flex justify-end gap-3">
                    <button
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                        @click="photoToDelete = null"
                    >
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded transition-colors"
                        :disabled="deleting[photoToDelete.id]"
                        @click="deletePhoto(photoToDelete)"
                    >
                        {{ deleting[photoToDelete.id] ? $t('Deleting...') : $t('Delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { usePhotosStore } from '@/stores/photos';
import UploadTags from './UploadTags.vue';
import UploadsHeader from './components/UploadsHeader.vue';
import UploadsPagination from './components/UploadsPagination.vue';

const store = usePhotosStore();
const router = useRouter();

// State
const selectedPhoto = ref(null);
const copyLinkStatus = ref({});
const photoToDelete = ref(null);
const deleting = ref({});

// Computed
const photos = computed(() => store.photos);

// Methods
const copyPhotoLink = async (photo) => {
    if (!photo.lat || !photo.lon) {
        copyLinkStatus.value[photo.id] = 'No location data';
        setTimeout(() => {
            delete copyLinkStatus.value[photo.id];
        }, 2000);
        return;
    }

    const baseUrl = window.location.origin;
    const link = `${baseUrl}/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photoId=${photo.id}&load=true`;

    try {
        await navigator.clipboard.writeText(link);
        copyLinkStatus.value[photo.id] = 'Copied!';
        setTimeout(() => {
            delete copyLinkStatus.value[photo.id];
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
        copyLinkStatus.value[photo.id] = 'Failed';
        setTimeout(() => {
            delete copyLinkStatus.value[photo.id];
        }, 2000);
    }
};

const openPhotoLink = (photo) => {
    if (!photo.lat || !photo.lon) {
        return;
    }

    const baseUrl = window.location.origin;
    const link = `${baseUrl}/global?lat=${photo.lat}&lon=${photo.lon}&zoom=17&photoId=${photo.id}&load=true`;
    window.open(link, '_blank');
};

const confirmDelete = (photo) => {
    photoToDelete.value = photo;
};

const deletePhoto = async (photo) => {
    deleting.value[photo.id] = true;
    try {
        await store.DELETE_PHOTO(photo.id);
        photoToDelete.value = null;
    } catch (error) {
        console.error('Delete failed:', error);
    } finally {
        deleting.value[photo.id] = false;
    }
};

const getObjectCount = (photo) => {
    if (!photo.new_tags) return 0;
    return photo.new_tags.reduce((total, tag) => {
        if (tag.object) {
            return total + (tag.quantity || 0);
        }
        return total;
    }, 0);
};

const getMaterialCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'material') {
                    count += extra.quantity || 0;
                }
            });
        }
    });
    return count;
};

const getBrandCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            tag.extra_tags.forEach((extra) => {
                if (extra.type === 'brand') {
                    count += extra.quantity || 0;
                }
            });
        }
    });
    return count;
};

const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const MAX_TAG_DISPLAY = 8;

const getTagsList = (photo) => {
    if (!photo.new_tags) return [];
    const items = [];
    for (const tag of photo.new_tags) {
        if (items.length >= MAX_TAG_DISPLAY) break;
        if (tag.object && tag.object.key) {
            const typeLabel = tag.type?.key ? formatKey(tag.type.key) + ' ' : '';
            const label = typeLabel + formatKey(tag.object.key);
            items.push({
                key: `obj-${tag.id}`,
                label,
                category: tag.category?.key,
                quantity: tag.quantity || 0,
                pickedUp: tag.picked_up,
            });
        } else if (tag.extra_tags?.length > 0) {
            for (const extra of tag.extra_tags) {
                if (items.length >= MAX_TAG_DISPLAY) break;
                const label = extra.tag?.key ? formatKey(extra.tag.key) : formatKey(extra.type);
                items.push({
                    key: `extra-${tag.id}-${extra.type}-${extra.tag?.id}`,
                    label,
                    category: extra.type,
                    quantity: extra.quantity || tag.quantity || 1,
                    pickedUp: tag.picked_up,
                });
            }
        }
    }
    return items;
};

const openTags = (photo) => {
    // Navigate to /tag?photo=<id> for editing
    router.push({ path: '/tag', query: { photo: photo.id } });
};

const formatDate = (datetime) => {
    if (!datetime) return 'N/A';
    return new Date(datetime).toLocaleDateString();
};
</script>
