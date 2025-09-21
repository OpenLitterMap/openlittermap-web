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
                        <span class="font-bold text-gray-800">#{{ photo.id }}</span>
                        <span class="text-sm text-gray-600">{{ formatDate(photo.datetime) }}</span>
                    </div>

                    <!-- Tags Summary -->
                    <div class="grid grid-cols-2 gap-3 p-2 bg-gray-50 rounded">
                        <!-- Stats Column -->
                        <div class="flex flex-col gap-1">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Total tags:</span>
                                <span class="font-semibold text-gray-800">{{ photo.total_tags || 0 }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Objects:</span>
                                <span class="font-semibold text-gray-800">{{ getObjectCount(photo) }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Materials:</span>
                                <span class="font-semibold text-gray-800">{{ getMaterialCount(photo) }}</span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Brands:</span>
                                <span class="font-semibold text-gray-800">{{ getBrandCount(photo) }}</span>
                            </div>
                        </div>

                        <!-- Tags List Column -->
                        <div class="flex flex-col gap-0.5">
                            <div
                                v-for="obj in getObjectsList(photo)"
                                :key="obj"
                                class="px-1 py-0.5 bg-white rounded text-xs text-gray-700 truncate"
                            >
                                {{ obj }}
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

// Computed
const photos = computed(() => store.photos);

// Methods
const getObjectCount = (photo) => {
    if (!photo.new_tags) return 0;
    return photo.new_tags.filter((tag) => tag.object).length;
};

const getMaterialCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            count += tag.extra_tags.filter((e) => e.type === 'material').length;
        }
    });
    return count;
};

const getBrandCount = (photo) => {
    if (!photo.new_tags) return 0;
    let count = 0;
    photo.new_tags.forEach((tag) => {
        if (tag.extra_tags) {
            count += tag.extra_tags.filter((e) => e.type === 'brand').length;
        }
    });
    return count;
};

const getObjectsList = (photo) => {
    if (!photo.new_tags) return [];
    const objects = [];
    photo.new_tags.forEach((tag) => {
        if (tag.object && tag.object.key) {
            objects.push(tag.object.key);
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
