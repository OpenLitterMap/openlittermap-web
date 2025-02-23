<template>
    <div class="bg-[#1e283a] olm-full">
        <div class="h-full py-12 px-24">
            <div v-if="paginatedPhotos?.data?.length === 0">
                <p>No photos</p>
            </div>

            <div v-else>
                <!-- Header-->
                <div class="bg-gray-100 p-5 rounded-md mb-10 flex justify-evenly">
                    <p>Found {{ paginatedPhotos?.data?.length }} photos</p>

                    <p>Team: Cleanup</p>

                    <p>x photos remaining</p>

                    <p>XP to level up: 69</p>
                </div>

                <div class="flex">
                    <div class="w-[5em]">
                        <p>Left col</p>
                    </div>

                    <!-- Image Container -->
                    <div class="flex flex-col items-center w-1/3">
                        <img :src="paginatedPhotos?.data[0]?.filename" alt="photo" />

                        <div class="w-full">
                            <!-- Needs a key to re-render -->
                            <SelectTag
                                :key="searchAllTagsKey"
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
                                    :disabled="!tagSelected"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    v-tooltip="!tagSelected ? 'Please select a tag' : ''"
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
                    <div class="md:w-1/2">
                        <div class="px-20">
                            <!-- Added tags -->
                            <ul role="list" class="grid grid-cols-2 gap-6">
                                <li
                                    v-for="tag in newTags"
                                    :key="tag.id"
                                    class="col-span-1 flex flex-col rounded-lg bg-[#435064] shadow p-4"
                                >
                                    <div class="flex mb-4 items-center">
                                        <p class="text-xl flex-1">
                                            {{ tag.quantity }}
                                            {{ t('litter.' + tag.category.key + '.' + tag.object.key)
                                            }}{{ tag.quantity === 1 ? '' : 's' }}
                                        </p>

                                        <input
                                            type="number"
                                            min="1"
                                            max="100"
                                            step="1"
                                            v-model="tag.quantity"
                                            class="w-16 h-[2.5em] form-input focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <SelectTag
                                        :tags="getMaterials"
                                        v-model="tag.material"
                                        placeholder="Add Materials"
                                        size="small"
                                    />

                                    <SelectTag
                                        :tags="getBrands"
                                        v-model="tag.brand"
                                        placeholder="Add Brands"
                                        size="small"
                                    />

                                    <div class="mb-4">
                                        <p class="text-sm">Suggested tags:</p>

                                        <div
                                            v-if="tag.extraTags && tag.extraTags.length"
                                            class="mt-2 text-sm flex flex-wrap gap-1"
                                        >
                                            <span
                                                v-for="extraTag in tag.extraTags"
                                                :key="extraTag.id"
                                                @click="toggleExtraTag(extraTag)"
                                                :class="[
                                                    'inline-flex cursor-pointer items-center gap-x-1.5 rounded-full px-2 py-1 text-xs font-medium ring-1 ring-inset',
                                                    extraTag.selected
                                                        ? ' ring-green-500 border-green-500'
                                                        : 'text-gray-900 ring-gray-200',
                                                ]"
                                            >
                                                <svg
                                                    class="h-1.5 w-1.5 fill-current"
                                                    :class="extraTag.selected ? 'text-green-500' : 'text-gray-500'"
                                                    viewBox="0 0 6 6"
                                                    aria-hidden="true"
                                                >
                                                    <circle cx="3" cy="3" r="3"></circle>
                                                </svg>
                                                {{ extraTag.key }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex">
                                        <div class="flex w-2/3 m-auto">
                                            <p class="mr-2">Picked up</p>

                                            <ToggleSwitch :model-value="tag.pickedUp" />
                                        </div>

                                        <div class="flex w-1/3 gap-1">
                                            <button
                                                type="button"
                                                @click="deleteTag(tag.id)"
                                                class="inline-flex items-center justify-center px-2.5 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                            >
                                                <i class="fas fa-fw fa-trash-alt text-xs"></i>
                                            </button>

                                            <button
                                                type="button"
                                                @click="duplicateTag(tag.id)"
                                                class="inline-flex items-center justify-center px-2.5 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                            >
                                                <i class="fas fa-fw fa-copy text-xs"></i>
                                            </button>
                                        </div>
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
import { useI18n } from 'vue-i18n';

import { usePhotosStore } from '../../../stores/photos/index.js';
import { useTagsStore } from '../../../stores/tags/index.js';
import SelectTag from './components/SelectTag.vue';
import QuantityPicker from './components/QuantityPicker.vue';
import ToggleSwitch from './components/ToggleSwitch.vue';

const photosStore = usePhotosStore();
const tagsStore = useTagsStore();

const { t } = useI18n();
const $loading = useLoading();
const selectedCategory = ref({ id: 0, key: '', text: '' });
const selectedObject = ref({ id: 0, key: '', text: '' });
const selectedMaterial = ref({ id: 0, key: '', text: '' });
const searchAllTags = ref({ id: 0, key: '', text: '' });
const selectedQuantity = ref(1);
const selectedBrand = ref({ id: 0, key: '', text: '' });
const searchAllTagsKey = ref(0);
const newTags = ref([]);
const newTagSelected = ref(null);

// Needs checkboxes to filter by all tags or materials
const getAllTags = computed(() => {
    const tags = [];

    // Iterate over each category in tagsStore.groupedTags.
    // We assume tagsStore.groupedTags is an object where each key represents a category.
    Object.keys(tagsStore.groupedTags).forEach((categoryKey) => {
        const categoryGroup = tagsStore.groupedTags[categoryKey];
        // Use the category group's id if provided; otherwise fallback to the categoryKey.
        const categoryId = categoryGroup.id || categoryKey;
        const categoryText = t(`litter.categories.${categoryKey}`);

        // Iterate over the litter_objects array within this category.
        (categoryGroup.litter_objects || []).forEach((obj) => {
            const objectText = t(`litter.${categoryKey}.${obj.key}`);

            tags.push({
                id: `cat-${categoryId}-obj-${obj.id}`,
                key: `${categoryKey}-${obj.key}`,
                categoryKey: categoryKey,
                categoryId: categoryId,
                objectKey: obj.key,
                objectId: obj.id,
                text: `${categoryText} - ${objectText}`,
                type: 'object',
                materials: [...(obj.materials || [])],
            });
        });
    });

    return tags;
});

const getCategories = computed(() => {
    return tagsStore.categories.map((category) => {
        return {
            id: category.id,
            key: category.key,
            text: t(`litter.categories.${category.key}`),
        };
    });
});

const getObjects = computed(() => {
    if (selectedCategory.value.key) {
        return tagsStore.groupedTags[selectedCategory.value.key].litter_objects.map((obj) => {
            return {
                id: obj.id,
                key: obj.key,
                text: t(`litter.${selectedCategory.value.key}.${obj.key}`),
            };
        });
    }

    return [];
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
    return tagsStore.brands;
});

watch(selectedObject, (newObj) => {
    if (selectedCategory.value.id !== 0) {
        return;
    }

    if (newObj) {
        if (newObj.categories?.length === 1) {
            selectedCategory.value = newObj.categories[0];
        }

        const materials = getMaterials.value;

        if (materials.length === 1) {
            selectedMaterial.value = materials[0];
        }
    }
});

watch(searchAllTags, (newVal) => {
    //  && newVal.materialId
    if (newVal && newVal.id && newVal.categoryId && newVal.objectId) {
        selectedCategory.value = {
            id: newVal.categoryId,
            key: newVal.categoryKey,
            text: t(`litter.categories.${newVal.categoryKey}`),
        };
        selectedObject.value = {
            id: newVal.objectId,
            key: newVal.objectKey,
            text: t(`litter.${newVal.categoryKey}.${newVal.objectKey}`),
            materials: newVal.materials ? newVal.materials : [],
        };
        // selectedMaterial.value = { id: newVal.materialId, key: newVal.materialKey };
    }
});

const tagSelected = computed(() => {
    return !(searchAllTags.value.id === 0 && selectedObject.value.id === 0);
});

const addTag = () => {
    if (!tagSelected.value) {
        return;
    }

    // If there is only 1 material, apply it
    // If there are 2+ materials, add them to suggested tags
    newTags.value.push({
        id: Math.random().toString(16).slice(2),
        category: { ...selectedCategory.value },
        object: { ...selectedObject.value },
        quantity: selectedQuantity.value,
        pickedUp: true, // change to users default settings

        // Brands, Materials, Custom Tags & anything else
        extraTags:
            selectedObject.value.materials?.length > 0
                ? selectedObject.value.materials.map((material) => ({
                      ...material,
                      selected: false,
                      type: 'material',
                  }))
                : [],
    });

    resetInputs();
};

const toggleExtraTag = (extraTag) => {
    extraTag.selected = !extraTag.selected;
};

const resetInputs = () => {
    selectedCategory.value = { id: 0, key: '', text: '' };
    selectedObject.value = { id: 0, key: '', text: '' };
    selectedMaterial.value = { id: 0, key: '', text: '' };
    searchAllTags.value = { id: 0, key: '', text: '' };
    selectedQuantity.value = 1;
    // Increment key to re-render SelectTag component
    searchAllTagsKey.value++;
};

const deleteTag = (id) => {
    newTags.value = newTags.value.filter((tag) => tag.id !== id);
};

const duplicateTag = (id) => {
    const originalTag = newTags.value.find((tag) => tag.id === id);

    newTags.value.push({
        id: Math.random().toString(16).slice(2),
        category: originalTag.category,
        object: originalTag.object,
        material: originalTag.material,
        quantity: originalTag.quantity,
        pickedUp: originalTag.pickedUp,
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

<style scoped>
input[type='number']::-webkit-inner-spin-button {
    opacity: 1;
}
</style>
