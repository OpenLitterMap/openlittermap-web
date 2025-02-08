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
                    <div class="flex flex-col border-gray-50">
                        <!-- Select Category -->
                        <SelectTag :tags="getCategories" v-model="selectedCategory" placeholder="category" />

                        <!-- Select Object -->
                        <!--                        v-show="selectedCategory.id > 0"-->
                        <SelectTag :tags="getLitterObjects" v-model="selectedObject" placeholder="object" />

                        <!-- Select Tag Type -->
                        <!--                        v-show="selectedObject.id > 0 && getTagTypes.length"-->
                        <SelectTag :tags="getTagTypes" v-model="selectedTagType" placeholder="tag type" />

                        <!-- Select Material -->
                        <!--                        v-show="-->
                        <!--                        (getTagTypes && selectedTagType.id > 0) ||-->
                        <!--                        (getLitterObjects &&-->
                        <!--                        selectedObject.id > 0 &&-->
                        <!--                        getMaterialsForLitterObjectOrTagType.length)-->
                        <!--                        "-->
                        <SelectTag
                            :tags="getMaterialsForLitterObjectOrTagType"
                            v-model="selectedMaterial"
                            placeholder="material"
                        />

                        <button class="mt-auto bg-blue-500 text-white px-4 py-2 rounded-md" @click="addTags">
                            Add Tags
                        </button>
                    </div>
                </div>

                <!--                        <div class="mb-4">-->
                <!--                            <input-->
                <!--                                type="text"-->
                <!--                                placeholder="TODO: Search all tags"-->
                <!--                                v-model="searchAllTags"-->
                <!--                            />-->
                <!--                        </div>-->
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
const selectedMaterial = ref([]);

// Computed
const getCategories = computed(() => {
    return tagsStore.categories;
});

const getLitterObjects = computed(() => {
    if (selectedCategory.value.key) {
        return tagsStore.objectsForCategory[selectedCategory.value.key];
    }

    return tagsStore.objects;
});

const getTagTypes = computed(() => {
    if (selectedObject.value.id > 0) {
        const object = getLitterObjects.value.find((o) => o.key === selectedObject.value.key);

        return object?.tag_types || [];
    }

    return [];
});

const getMaterialsForLitterObjectOrTagType = computed(() => {
    const objectMaterials = getLitterObjects.value.find((o) => o.key === selectedObject.value.key)?.materials || [];

    const tagTypeMaterials = getTagTypes.value.find((t) => t.key === selectedTagType.value.key)?.materials || [];

    const stringArray = [...objectMaterials, ...tagTypeMaterials];

    // Convert strings => { id, key } to match id, key logic of SelectTag.vue
    return stringArray.map((item) => ({
        id: item,
        key: item,
    }));
});

const addTags = () => {
    console.log(
        'Add tags',
        selectedCategory.value,
        selectedObject.value,
        selectedTagType.value,
        selectedMaterial.value
    );
};

onMounted(async () => {
    const loader = $loading.show({ container: null });

    await photosStore.GET_USERS_UNTAGGED_PHOTOS();

    if (tagsStore.groupedTags.length === 0) {
        await tagsStore.GET_TAGS();
    }

    loader.hide();
});

const paginatedPhotos = computed(() => photosStore.paginated);
</script>

<style scoped></style>
