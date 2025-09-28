<template>
    <div class="olm-full bg-gray-800 p-6">
        <UploadsHeader />

        <!-- Photos Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
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
                </div>

                <!-- Photo Info -->
                <div class="p-3">
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="font-bold text-gray-800 cursor-pointer hover:text-blue-600 transition-colors"
                                @click.stop="copyPhotoLink(photo)"
                                :title="'Copy location link'"
                            >
                                #{{ photo.id }}
                            </span>
                            <button
                                class="text-gray-600 hover:text-blue-600 transition-colors p-1"
                                @click.stop="copyPhotoLink(photo)"
                                :title="'Copy location link'"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                    />
                                </svg>
                            </button>
                            <button
                                class="text-gray-600 hover:text-blue-600 transition-colors p-1"
                                @click.stop="openPhotoLink(photo)"
                                :title="'Open location in new tab'"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                    />
                                </svg>
                            </button>
                            <span v-if="copyLinkStatus[photo.id]" class="text-xs text-green-600 font-medium">
                                {{ copyLinkStatus[photo.id] }}
                            </span>
                        </div>
                        <span class="text-sm text-gray-600">{{ formatDate(photo.datetime) }}</span>
                    </div>

                    <!-- Tags Summary -->
                    <div class="grid grid-cols-2 gap-3 p-2 bg-gray-50 rounded">
                        <!-- Stats Column -->
                        <div class="flex flex-col gap-1">
                            <div v-if="getObjectCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">Objects:</span>
                                <span class="font-semibold text-gray-800">{{ getObjectCount(photo) }}</span>
                            </div>
                            <div v-if="photo.total_tags > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">Total tags:</span>
                                <span class="font-semibold text-gray-800">{{ photo.total_tags }}</span>
                            </div>
                            <div v-if="getMaterialCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">Materials:</span>
                                <span class="font-semibold text-gray-800">{{ getMaterialCount(photo) }}</span>
                            </div>
                            <div v-if="getBrandCount(photo) > 0" class="flex justify-between text-xs">
                                <span class="text-gray-600">Brands:</span>
                                <span class="font-semibold text-gray-800">{{ getBrandCount(photo) }}</span>
                            </div>
                        </div>

                        <!-- Tags List Column -->
                        <div class="flex flex-col gap-0.5">
                            <p class="text-xs mb-1">Objects:</p>
                            <div
                                v-for="obj in getObjectsList(photo)"
                                :key="obj.key"
                                class="px-1 py-0.5 bg-white rounded text-xs text-gray-700 truncate"
                                :title="`${obj.key} (×${obj.quantity})`"
                            >
                                {{ obj.key }} ×{{ obj.quantity }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <UploadsPagination />

        <!-- UploadTags Modal -->
        <UploadTags v-if="selectedPhoto" :photo="selectedPhoto" @close="selectedPhoto = null" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePhotosStore } from '@/stores/photos';
import UploadTags from './UploadTags.vue';
import UploadsHeader from './components/UploadsHeader.vue';
import UploadsPagination from './components/UploadsPagination.vue';

const store = usePhotosStore();

// State
const selectedPhoto = ref(null);
const copyLinkStatus = ref({});

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

    // Get current domain (e.g., https://olm.test or https://openlittermap.com)
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

const getObjectCount = (photo) => {
    if (!photo.new_tags) return 0;
    // Sum up the quantity of each object tag
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

const getObjectsList = (photo) => {
    if (!photo.new_tags) return [];
    const objects = [];
    photo.new_tags.forEach((tag) => {
        if (tag.object && tag.object.key) {
            objects.push({
                key: tag.object.key,
                quantity: tag.quantity || 0,
            });
        }
    });
    return objects.slice(0, 5); // Show max 5 objects
};

const openTags = (photo) => {
    selectedPhoto.value = photo;
};

const formatDate = (datetime) => {
    if (!datetime) return 'N/A';
    return new Date(datetime).toLocaleDateString();
};

// Initial load - fetch both photos and stats
onMounted(async () => {
    await store.fetchUntaggedData(1, 25);
});
</script>
