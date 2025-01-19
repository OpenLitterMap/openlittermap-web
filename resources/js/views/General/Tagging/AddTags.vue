<template>
    <div class="bg-slate-800 olm-full">
        <div class="h-full py-12 px-24">
            <p>Add Tags</p>

            <div v-if="paginatedPhotos?.data?.length === 0">
                <p>No photos</p>
            </div>

            <div v-else>
                <p>Found {{ paginatedPhotos?.data?.length }} photos</p>

                <div class="flex">
                    <img :src="paginatedPhotos?.data[0]?.filename" alt="photo" class="max-w-[42em] mr-10" />

                    <!-- Add Tags -->
                    <div class="border-gray-50">
                        <!--                        <div class="mb-4">-->
                        <!--                            <input-->
                        <!--                                type="text"-->
                        <!--                                placeholder="TODO: Search all tags"-->
                        <!--                                v-model="searchAllTags"-->
                        <!--                            />-->
                        <!--                        </div>-->

                        <!-- Select Category -->
                        <SelectTag
                            :tags="tagsStore.categories"
                            v-model="selectedCategory"
                            placeholder="category"
                            class="mb-4"
                        />

                        <!-- Select Object -->
                        <SelectTag
                            v-show="selectedCategory.id > 0"
                            :tags="tagsStore.objectsForCategory[selectedCategory.key]"
                            v-model="selectedObject"
                            placeholder="object"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useLoading } from 'vue-loading-overlay';
import { onMounted, computed, ref } from 'vue';

import { usePhotosStore } from '../../../stores/photos/index.js';
import { useTagsStore } from '../../../stores/tags/index.js';
import SelectTag from './components/SelectTag.vue';

const photosStore = usePhotosStore();
const tagsStore = useTagsStore();

const $loading = useLoading();
const searchAllTags = ref('');
const selectedCategory = ref({ id: 0, key: '' });
const selectedObject = ref({ id: 0, key: '' });
const selectedTagType = ref({ id: 0, key: '' });

onMounted(async () => {
    const loader = $loading.show({ container: null });

    await photosStore.GET_USERS_UNTAGGED_PHOTOS();

    if (tagsStore.tags.length === 0) {
        await tagsStore.GET_TAGS();
    }

    loader.hide();
});

const paginatedPhotos = computed(() => photosStore.paginated);
</script>

<style scoped></style>
