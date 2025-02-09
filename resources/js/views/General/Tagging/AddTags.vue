<template>
    <div class="bg-slate-800 olm-full">
        <div class="h-full py-12 px-24">
            <div v-if="paginatedPhotos?.data?.length === 0">
                <p>No photos</p>
            </div>

            <div v-else>
                <!-- Header-->
                <div class="bg-gray-100 p-5 rounded-md mb-10">
                    <p>Found {{ paginatedPhotos?.data?.length }} photos</p>
                </div>

                <div class="flex">
                    <!-- Image Container -->
                    <div class="flex flex-col items-center w-1/3">
                        <img :src="paginatedPhotos?.data[0]?.filename" alt="photo" />

                        <div class="w-full">
                            <SelectTag
                                :tags="getAllTags"
                                v-model="searchAllTags"
                                placeholder="Search All Tags or Create Your Own!"
                                class="mt-10"
                            />

                            <div class="flex gap-2">
                                <!-- Select Category -->
                                <SelectTag :tags="getCategories" v-model="selectedCategory" placeholder="category" />

                                <!-- Select Object -->
                                <SelectTag :tags="getObjects" v-model="selectedObject" placeholder="object" />

                                <!-- Select Quantity -->
                                <QuantityPicker v-model="selectedQuantity" />
                            </div>

                            <div class="flex items-center justify-center mt-4 gap-4">
                                <button
                                    :disabled="selectedQuantity === 1"
                                    @click="selectedQuantity--"
                                    class="px-4 py-2 bg-red-600 text-white rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    -
                                </button>

                                <button
                                    @click="addTag"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                                >
                                    Add Tag
                                </button>

                                <button
                                    @click="selectedQuantity++"
                                    :disabled="selectedQuantity === 100"
                                    class="px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-600 transition-colors"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right container-->
                    <div class="md:w-1/2 lg:w-2/3">
                        <div class="px-20">
                            <ul role="list" class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                                <li
                                    v-for="tag in newTags"
                                    :key="tag.id"
                                    @click="newTagSelected = tag.id"
                                    class="col-span-1 flex flex-col rounded-lg bg-slate-700 shadow p-4"
                                    :class="newTagSelected === tag.id ? 'bg-blue-600' : ''"
                                >
                                    <p class="text-xl mb-4">{{ tag.quantity }} {{ tag.object.key }}</p>

                                    <SelectTag
                                        :tags="getMaterials"
                                        v-model="selectedMaterial"
                                        placeholder="Add Materials"
                                    />

                                    <SelectTag :tags="getBrands" v-model="selectedBrand" placeholder="Add Brands" />

                                    <div class="mb-4">
                                        <p>Suggested tags:</p>

                                        <p>tag-1</p>
                                        <p>tag-2</p>
                                    </div>

                                    <div class="flex">
                                        <p class="mr-4">Picked up?</p>

                                        <ToggleSwitch />
                                    </div>

                                    <div class="flex">
                                        <p>Delete</p>

                                        <p>Copy</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useLoading } from 'vue-loading-overlay';
import { onMounted, computed, ref, watch } from 'vue';

import { usePhotosStore } from '../../../stores/photos/index.js';
import { useTagsStore } from '../../../stores/tags/index.js';
import SelectTag from './components/SelectTag.vue';
import QuantityPicker from './components/QuantityPicker.vue';
import ToggleSwitch from './components/ToggleSwitch.vue';

const photosStore = usePhotosStore();
const tagsStore = useTagsStore();

const $loading = useLoading();
const selectedCategory = ref({ id: 0, key: '' });
const selectedObject = ref({ id: 0, key: '' });
const selectedMaterial = ref({ id: 0, key: '' });
const searchAllTags = ref({ id: 0, key: '', text: '' });
const selectedQuantity = ref(1);
const selectedBrand = ref({ id: 0, key: '' });

// Needs checkboxes to filter by all tags or materials
const getAllTags = computed(() => {
    const objectTags = tagsStore.objects.flatMap((obj) =>
        obj.materials.flatMap((material) =>
            obj.categories.map((cat) => ({
                id: `cat-${cat.id}-obj-${obj.id}-mat-${material.id}`,
                key: `${cat.key}-${obj.key}-${material.key}`,

                categoryKey: cat.key,
                categoryId: cat.id,

                objectKey: obj.key,
                objectId: obj.id,

                materialKey: material.key,
                materialId: material.id,

                text: `${cat.key} - ${obj.key} - ${material.key}`,
                type: 'object',
            }))
        )
    );

    const materialTags = tagsStore.materials.map((mat) => {
        return {
            id: mat.id,
            key: mat.key,
            text: mat.key,
            type: 'material',
        };
    });

    return [...objectTags, ...materialTags];
});

const getCategories = computed(() => {
    return tagsStore.categories;
});

const getObjects = computed(() => {
    if (selectedCategory.value.key) {
        return tagsStore.groupedTags[selectedCategory.value.key].litter_objects;
    }

    return tagsStore.objects;
});

const getMaterials = computed(() => {
    let materials = [];

    if (selectedCategory.value.id > 0 && selectedObject.value.id > 0) {
        materials = tagsStore.groupedTags[selectedCategory.value.key].litter_objects.find(
            (obj) => obj.key === selectedObject.value.key
        )?.materials;
    }

    if (materials.length === 0) {
        materials = tagsStore.materials;
    }

    return materials;
});

const getBrands = computed(() => {
    return [];
});

watch(selectedObject, (newObj) => {
    if (selectedCategory.value.id !== 0) {
        return;
    }

    if (newObj) {
        if (newObj.categories.length === 1) {
            selectedCategory.value = newObj.categories[0];
        }

        const materials = getMaterials.value;

        if (materials.length === 1) {
            selectedMaterial.value = materials[0];
        }
    }
});

watch(searchAllTags, (newVal) => {
    selectedCategory.value = { id: newVal.categoryId, key: newVal.categoryKey };
    selectedObject.value = { id: newVal.objectId, key: newVal.objectKey };
    selectedMaterial.value = { id: newVal.materialId, key: newVal.materialKey };
});

const newTags = ref([]);
const newTagSelected = ref(null);

const addTag = () => {
    const uuid = () => Math.random().toString(16).slice(2);

    newTags.value.push({
        id: uuid,
        category: selectedCategory.value,
        object: selectedObject.value,
        material: selectedMaterial.value,
        quantity: selectedQuantity.value,
    });
};

onMounted(async () => {
    const loader = $loading.show({ container: null });

    await photosStore.GET_USERS_UNTAGGED_PHOTOS();

    if (tagsStore.groupedTags.length === 0) {
        await tagsStore.GET_TAGS();
        await tagsStore.GET_ALL_TAGS();
    }

    loader.hide();
});

const paginatedPhotos = computed(() => photosStore.paginated);
</script>

<style scoped></style>
