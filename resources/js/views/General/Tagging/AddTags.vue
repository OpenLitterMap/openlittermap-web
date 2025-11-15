<template>
    <div class="bg-[#1e283a] olm-full">
        <div class="h-full py-12 px-4">
            <div v-if="paginatedPhotos?.data?.length === 0" class="flex flex-col items-center">
                <p class="text-4xl text-white text-center mb-8">No photos to tag</p>
                <img :src="litterWorldImg" alt="Litter World" class="w-1/2 h-1/2" />
            </div>

            <div v-else>
                <div class="flex ml-[2em] w-full md:pr-[3em]">
                    <!-- Left Column: Image Container -->
                    <div class="flex flex-col items-center w-2/5">
                        <!-- Image with Skeleton Loader -->
                        <div class="w-full max-h-[50vh] relative">
                            <!-- Skeleton Loader -->
                            <div v-if="isImageLoading" class="animate-pulse">
                                <div
                                    class="bg-gray-700 rounded-lg flex flex-col items-center justify-center"
                                    style="height: 50vh"
                                >
                                    <svg
                                        class="w-16 h-16 text-gray-600 mb-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                        />
                                    </svg>
                                    <div class="space-y-2 w-1/2">
                                        <div class="h-2 bg-gray-600 rounded animate-pulse"></div>
                                        <div class="h-2 bg-gray-600 rounded animate-pulse w-3/4"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actual Image -->
                            <img
                                v-show="!isImageLoading"
                                :src="currentPhotoSrc"
                                :key="currentPhotoSrc"
                                alt="photo"
                                class="max-h-[50vh] w-auto object-contain mx-auto"
                                @load="onImageLoad"
                                @error="onImageError"
                            />

                            <!-- Error State -->
                            <div
                                v-if="imageError"
                                class="bg-gray-800 rounded-lg flex flex-col items-center justify-center"
                                style="height: 50vh"
                            >
                                <svg
                                    class="w-16 h-16 text-red-500 mb-2"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                <p class="text-gray-400">Failed to load image</p>
                            </div>
                        </div>

                        <!-- Controls Below Image -->
                        <div class="w-full">
                            <!-- Search All Tags -->
                            <SelectTag
                                :key="searchAllTagsKey"
                                :tags="getAllTags"
                                v-model="searchAllTags"
                                placeholder="Search All Tags or Create Your Own!"
                                class="mt-10"
                                @addCustomTag="addCustomTag"
                            />

                            <div class="flex gap-2">
                                <SelectTag :tags="getCategories" v-model="selectedCategory" placeholder="category" />
                                <SelectTag :tags="getObjects" v-model="selectedObject" placeholder="object" />
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

                    <!-- Right Column: Navigation + Tags -->
                    <div class="w-2/3 2xl:w-1/2">
                        <AddTagsHeader
                            :paginatedPhotos="paginatedPhotos"
                            :newTags="newTags"
                            :currentPhotoIndex="currentPhotoIndex"
                            @update:currentPhotoIndex="handlePhotoIndexChange"
                            @clearTags="resetAllInputs"
                        />

                        <div class="pl-12 mt-4 max-h-[60vh] overflow-y-auto">
                            <ul role="list" class="grid grid-cols-2 gap-6">
                                <li
                                    v-for="tag in newTags"
                                    :key="tag.id"
                                    class="col-span-1 flex flex-col rounded-lg bg-[#435064] shadow p-4"
                                >
                                    <div class="flex mb-4 items-center">
                                        <span
                                            v-if="tag.hasOwnProperty('custom') && tag.custom"
                                            class="2xl:text-xl flex-1"
                                        >
                                            {{ tag.key }}
                                        </span>

                                        <p v-else class="2xl:text-xl flex-1">
                                            {{ tag.quantity }}
                                            {{ t('litter.' + tag.category.key + '.' + tag.object.key) }}
                                        </p>

                                        <input
                                            type="number"
                                            min="1"
                                            max="100"
                                            step="1"
                                            v-model.number="tag.quantity"
                                            @input="enforceQuantityRange(tag)"
                                            class="w-10 2xl:w-16 min-w-fit pr-2 text-center h-[2em] 2xl:h-[2.5em] form-input focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <SelectTag
                                        :tags="getBrands"
                                        placeholder="Add Brands"
                                        size="small"
                                        @update:modelValue="(newVal) => updateNestedTag('brand', tag.id, newVal)"
                                    />

                                    <SelectTag
                                        :tags="getMaterials"
                                        placeholder="Add Materials"
                                        size="small"
                                        @update:modelValue="(newVal) => updateNestedTag('material', tag.id, newVal)"
                                    />

                                    <SelectTag
                                        :key="'custom' + searchAllTagsKey"
                                        :tags="getAllTags"
                                        size="small"
                                        placeholder="Add More Objects"
                                        :emit-on-select="true"
                                        :parent-tag-id="tag.id"
                                        @selectedTag="addNestedObject"
                                    />

                                    <CreateTag
                                        placeholder="Add Custom Tags"
                                        size="small"
                                        @createTag="(newVal) => updateNestedTag('custom', tag.id, newVal)"
                                    />

                                    <div class="mb-4">
                                        <div class="flex items-center">
                                            <p class="text-sm pr-2">Extra tags:</p>
                                            <InformationCircleIcon
                                                class="w-4 h-4 text-blue-500"
                                                v-tooltip="'Choose from our suggested tags or add your own'"
                                            />
                                        </div>

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

                                    <div class="flex mt-auto">
                                        <div class="flex w-2/3 m-auto items-center">
                                            <p class="mr-2">Picked up</p>
                                            <ToggleSwitch v-model="tag.pickedUp" />
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
import { onMounted, computed, ref, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';

import { usePhotosStore } from '../../../stores/photos/index.js';
import { useTagsStore } from '../../../stores/tags/index.js';
import AddTagsHeader from './components/AddTagsHeader.vue';
import SelectTag from './components/SelectTag.vue';
import CreateTag from './components/CreateTag.vue';
import QuantityPicker from './components/QuantityPicker.vue';
import ToggleSwitch from './components/ToggleSwitch.vue';

const photosStore = usePhotosStore();
const tagsStore = useTagsStore();

const { t } = useI18n();
const $loading = useLoading();
const currentPhotoIndex = ref(0);
const selectedCategory = ref({ id: 0, key: '', text: '' });
const selectedObject = ref({ id: 0, key: '', text: '' });
const selectedMaterial = ref({ id: 0, key: '', text: '' });
const searchAllTags = ref({ id: 0, key: '', text: '' });
const selectedQuantity = ref(1);
const searchAllTagsKey = ref(0);
const newTags = ref([]);

// Image loading states
const isImageLoading = ref(false);
const imageError = ref(false);

import litterWorldImg from '@/assets/pixel_art/litterworld.jpeg';

onMounted(async () => {
    const loader = $loading.show({ container: null });

    await photosStore.GET_USERS_PHOTOS(1, { tagged: false });

    if (tagsStore.groupedTags.length === 0) {
        await tagsStore.GET_TAGS();
        await tagsStore.GET_ALL_TAGS();
    }

    document.addEventListener('keydown', handleKeyDown);

    loader.hide();
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

// Function to listen for Cmd + Enter (Mac) or Ctrl + Enter (Windows)
const handleKeyDown = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        addTag();
    }
};

const paginatedPhotos = computed(() => photosStore.paginated);
const currentPhotoSrc = computed(() => paginatedPhotos.value?.data[currentPhotoIndex.value]?.filename);

// Watch for photo changes to trigger loading state
watch(currentPhotoSrc, (newSrc) => {
    if (newSrc) {
        isImageLoading.value = true;
        imageError.value = false;
    }
});

// Image event handlers
const onImageLoad = () => {
    isImageLoading.value = false;
    imageError.value = false;
};

const onImageError = () => {
    isImageLoading.value = false;
    imageError.value = true;
};

// Handle photo index change from navigation
const handlePhotoIndexChange = (newIndex) => {
    currentPhotoIndex.value = newIndex;
    isImageLoading.value = true;
    imageError.value = false;
};

// Reset photo index when new photos are loaded
watch(paginatedPhotos, () => {
    currentPhotoIndex.value = 0;
    isImageLoading.value = true;
    imageError.value = false;
});

// All your existing computed properties and methods
const getAllTags = computed(() => {
    const tags = [];
    Object.keys(tagsStore.groupedTags).forEach((categoryKey) => {
        const categoryGroup = tagsStore.groupedTags[categoryKey];
        const categoryId = categoryGroup.id || categoryKey;
        const categoryText = t(`litter.categories.${categoryKey}`);

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
    if (!materials || materials.length === 0) {
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
    }
});

const tagSelected = computed(() => {
    return !(searchAllTags.value.id === 0 && selectedObject.value.id === 0);
});

const addTag = () => {
    if (!tagSelected.value) {
        return;
    }

    newTags.value.push({
        id: Math.random().toString(16).slice(2),
        category: { ...selectedCategory.value },
        object: { ...selectedObject.value },
        quantity: selectedQuantity.value,
        pickedUp: true,
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

const addCustomTag = (tag) => {
    if (tag.custom) {
        newTags.value.push(tag);
        searchAllTags.value = { id: 0, key: '', text: '' };
    }
};

const addNestedObject = (tag) => {
    updateNestedTag(tag.type, tag.parentTagId, tag);
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
    searchAllTagsKey.value++;
};

const resetAllInputs = () => {
    newTags.value = [];
    resetInputs();
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
        extraTags: originalTag.extraTags ? [...originalTag.extraTags] : [],
    });
};

const updateNestedTag = (type, id, newVal) => {
    if (!newVal || !newVal.id) {
        return;
    }

    const tagIndex = newTags.value.findIndex((tag) => tag.id === id);
    if (tagIndex === -1) return;

    const tag = newTags.value[tagIndex];

    const updatedExtraTag = {
        ...newVal,
        selected: true,
        type,
    };

    if (!tag.extraTags) {
        tag.extraTags = [];
    }

    const exists = tag.extraTags.find(
        (extra) => extra.type === type && extra.id === newVal.id && extra.key === newVal.key
    );

    if (!exists) {
        tag.extraTags.push(updatedExtraTag);
    } else {
        Object.assign(exists, updatedExtraTag);
    }

    newTags.value.splice(tagIndex, 1, { ...tag });
};

const enforceQuantityRange = (tag) => {
    if (tag.quantity < 1) {
        tag.quantity = 1;
    } else if (tag.quantity > 100) {
        tag.quantity = 100;
    }
};
</script>

<style scoped>
input[type='number']::-webkit-inner-spin-button {
    opacity: 1;
}
</style>
