<template>
    <div class="h-[calc(100vh-80px)] bg-gray-900 flex flex-col overflow-hidden">
        <!-- Enhanced Header with integrated actions and XP display -->
        <TaggingHeader
            :current-photo="currentPhoto"
            :photos="paginatedPhotos"
            :current-index="currentPhotoIndex"
            :tags="activeTags"
            :xp-preview="calculateXP"
            :submitting="isSubmitting"
            :has-unresolved-tags="hasUnresolvedTags"
            @navigate="handleNavigation"
            @skip="skipPhoto"
            @clear="clearAllTags"
            @submit="submitTags"
        />

        <!-- Main Content Area - Scrollable -->
        <div class="flex-1 overflow-hidden">
            <div class="flex flex-col lg:flex-row p-6 gap-6 h-full">
                <!-- Left Column -->
                <div class="lg:w-1/3 h-full">
                    <!-- Photo Viewer -->
                    <PhotoViewer
                        :photo-src="currentPhotoSrc"
                        :loading="imageLoading"
                        @image-loaded="imageLoading = false"
                    />
                </div>

                <!-- Right Column: Active Tags (2/3 on desktop) -->
                <div class="lg:w-2/3 h-full flex flex-col overflow-hidden">
                    <!-- Search Section -->
                    <div class="space-y-3 mb-4 flex-shrink-0">
                        <!-- Learn about tagging prompt -->
                        <div
                            v-if="showTaggingHelp"
                            class="bg-blue-900/30 border border-blue-700/50 rounded-lg px-4 py-2 flex items-center justify-between"
                        >
                            <a
                                href="/faq/tagging"
                                target="_blank"
                                class="flex items-center gap-2 text-blue-400 hover:text-blue-300 text-sm"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                Learn about tagging
                            </a>
                            <button @click="hideTaggingHelp" class="text-gray-400 hover:text-gray-300">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- Main Search Bar -->
                        <UnifiedTagSearch
                            v-model="searchQuery"
                            :tags="searchableTags"
                            :brands="brandsList"
                            :materials="materialsList"
                            @tag-selected="handleTagSelection"
                            @custom-tag="handleCustomTag"
                            placeholder="Search All Tags or Create Your Own!"
                        />

                        <!-- Quick suggestions -->
                        <div v-if="recentTags.length > 0">
                            <span class="text-xs text-gray-400 mr-2">Recent:</span>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <button
                                    v-for="tag in recentTags"
                                    :key="tag.id"
                                    @click="quickAddTag(tag)"
                                    class="text-xs px-2 py-1 bg-gray-700 text-gray-300 rounded hover:bg-gray-600 transition-colors"
                                >
                                    {{ formatKey(tag.key) }}
                                    <span v-if="tag.categoryKey" class="text-gray-500">· {{ formatKey(tag.categoryKey) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0">
                        <ActiveTagsList
                            :tags="activeTags"
                            :searchable-tags="searchableTags"
                            :brands="brandsList"
                            :materials="materialsList"
                            @update-quantity="updateTagQuantity"
                            @set-picked-up="setPickedUp"
                            @set-type="setTagType"
                            @add-detail="addTagDetail"
                            @remove-tag="removeTag"
                            @remove-detail="removeTagDetail"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePhotosStore } from '@stores/photos/index.js';
import { useTagsStore } from '@stores/tags/index.js';

import TaggingHeader from './components/TaggingHeader.vue';
import UnifiedTagSearch from './components/UnifiedTagSearch.vue';
import PhotoViewer from './components/PhotoViewer.vue';
import ActiveTagsList from './components/ActiveTagsList.vue';

// Stores
const photosStore = usePhotosStore();
const tagsStore = useTagsStore();

// Helpers
const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

// State
const currentPhotoIndex = ref(0);
const searchQuery = ref('');
const tagsByPhoto = ref({}); // { [photoId]: Tag[] }
const recentTags = ref([]);
const imageLoading = ref(true);
const isSubmitting = ref(false);
const showTaggingHelp = ref(true);

// Computed
const paginatedPhotos = computed(() => photosStore.paginated);
const currentPhoto = computed(() => paginatedPhotos.value?.data?.[currentPhotoIndex.value]);
const currentPhotoSrc = computed(() => currentPhoto.value?.filename);

// Get current photo's tags
const activeTags = computed(() => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return [];
    return tagsByPhoto.value[photoId] || [];
});

// Create searchable tags index combining all tag types
// One entry per (object, category) pair to disambiguate objects that appear in multiple categories
const searchableTags = computed(() => {
    const tags = [];

    // Objects: one entry per (object, category) pair
    tagsStore.objects.forEach((obj) => {
        if (obj.categories?.length) {
            obj.categories.forEach((cat) => {
                const cloId = tagsStore.getCloId(cat.id, obj.id);
                tags.push({
                    id: `obj-${obj.id}-cat-${cat.id}`,
                    key: obj.key,
                    lowerKey: obj.key.toLowerCase(),
                    text: obj.key,
                    type: 'object',
                    categoryId: cat.id,
                    categoryKey: cat.key,
                    cloId: cloId,
                    raw: obj,
                });
            });
        } else {
            tags.push({
                id: `obj-${obj.id}`,
                key: obj.key,
                lowerKey: obj.key.toLowerCase(),
                text: obj.key,
                type: 'object',
                categoryId: null,
                categoryKey: null,
                cloId: null,
                raw: obj,
            });
        }
    });

    // Types: one entry per category_object_type, with parent object context
    tagsStore.categoryObjectTypes.forEach((cot) => {
        const typeObj = tagsStore.types.find((t) => t.id === cot.litter_object_type_id);
        if (!typeObj) return;

        const clo = tagsStore.categoryObjects.find((co) => co.id === cot.category_litter_object_id);
        if (!clo) return;

        const obj = tagsStore.objects.find((o) => o.id === clo.litter_object_id);
        const cat = tagsStore.categories.find((c) => c.id === clo.category_id);
        if (!obj || !cat) return;

        tags.push({
            id: `type-${cot.category_litter_object_id}-${cot.litter_object_type_id}`,
            key: typeObj.key,
            lowerKey: typeObj.key.toLowerCase(),
            text: typeObj.key,
            type: 'type',
            cloId: clo.id,
            typeId: typeObj.id,
            objectKey: obj.key,
            categoryKey: cat.key,
            raw: { type: typeObj, object: obj, category: cat, clo: clo },
        });
    });

    tagsStore.brands.forEach((brand) => {
        tags.push({
            id: `brand-${brand.id}`,
            key: brand.key,
            lowerKey: brand.key.toLowerCase(),
            text: brand.key,
            type: 'brand',
            raw: brand,
        });
    });

    tagsStore.materials.forEach((material) => {
        tags.push({
            id: `mat-${material.id}`,
            key: material.key,
            lowerKey: material.key.toLowerCase(),
            text: material.key,
            type: 'material',
            raw: material,
        });
    });

    return tags;
});

const brandsList = computed(() => tagsStore.brands || []);
const materialsList = computed(() => tagsStore.materials || []);

// XP Calculation
const calculateXP = computed(() => {
    let xp = 0;
    activeTags.value.forEach((tag) => {
        xp += tag.quantity || 1;
        if (tag.pickedUp) xp += 5;
        if (tag.brands?.length) xp += tag.brands.length * 3;
        if (tag.materials?.length) xp += tag.materials.length * 2;
    });
    return xp;
});

// Validation: check if any object tag is missing its CLO id
const hasUnresolvedTags = computed(() => {
    return activeTags.value.some((tag) => tag.object && !tag.cloId);
});

// Helper to ensure photo has an array in tagsByPhoto
const ensurePhotoTags = () => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return null;
    if (!tagsByPhoto.value[photoId]) {
        tagsByPhoto.value[photoId] = [];
    }
    return photoId;
};

// Initialize
onMounted(async () => {
    await photosStore.fetchUntaggedData(1, { tagged: false });

    if (tagsStore.objects.length === 0) {
        await tagsStore.GET_ALL_TAGS();
    }

    const stored = localStorage.getItem('recentTags');
    if (stored) {
        // Filter out stale entries from before category disambiguation was added
        const parsed = JSON.parse(stored).filter((t) => t.type !== 'object' || t.cloId);
        recentTags.value = parsed.slice(0, 5);
        localStorage.setItem('recentTags', JSON.stringify(recentTags.value));
    }

    const hideHelp = localStorage.getItem('hideTaggingHelp');
    if (hideHelp === 'true') {
        showTaggingHelp.value = false;
    }

    document.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown);
});

// Keyboard shortcuts
const handleKeyDown = (event) => {
    // Ctrl/Cmd+Enter: always works (even in inputs), but check for unresolved tags
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        if (activeTags.value.length > 0 && !hasUnresolvedTags.value) {
            submitTags();
        }
        return;
    }

    // All other shortcuts: skip if user is typing in a form field
    const target = event.target;
    if (target.tagName === 'INPUT' || target.tagName === 'SELECT' || target.tagName === 'TEXTAREA') {
        return;
    }

    if (event.key >= '1' && event.key <= '9' && !event.ctrlKey && !event.metaKey) {
        if (activeTags.value.length > 0) {
            const photoId = currentPhoto.value?.id;
            if (photoId && tagsByPhoto.value[photoId]?.length > 0) {
                tagsByPhoto.value[photoId][tagsByPhoto.value[photoId].length - 1].quantity = parseInt(event.key);
            }
        }
    }
};

// Navigation
const handleNavigation = async (direction) => {
    if (direction === 'next') {
        if (currentPhotoIndex.value < paginatedPhotos.value.data.length - 1) {
            currentPhotoIndex.value++;
        } else if (paginatedPhotos.value.current_page < paginatedPhotos.value.last_page) {
            await photosStore.GET_USERS_PHOTOS(paginatedPhotos.value.current_page + 1, { tagged: false });
            currentPhotoIndex.value = 0;
        }
    } else {
        if (currentPhotoIndex.value > 0) {
            currentPhotoIndex.value--;
        } else if (paginatedPhotos.value.current_page > 1) {
            await photosStore.GET_USERS_PHOTOS(paginatedPhotos.value.current_page - 1, { tagged: false });
            currentPhotoIndex.value = paginatedPhotos.value.data.length - 1;
        }
    }
    imageLoading.value = true;
};

const skipPhoto = () => {
    handleNavigation('next');
};

const hideTaggingHelp = () => {
    showTaggingHelp.value = false;
    localStorage.setItem('hideTaggingHelp', 'true');
};

// Tag handling
const handleTagSelection = (selected) => {
    if (!selected || !selected.raw) return;
    const photoId = ensurePhotoTags();
    if (!photoId) return;

    const tagId = Math.random().toString(16).slice(2);

    if (selected.type === 'object') {
        // Use pre-resolved cloId and categoryKey from the search index entry
        tagsByPhoto.value[photoId].push({
            id: tagId,
            object: selected.raw,
            cloId: selected.cloId || null,
            categoryId: selected.categoryId || null,
            categoryKey: selected.categoryKey || null,
            typeId: null,
            quantity: 1,
            pickedUp: true,
            brands: [],
            materials: [],
            customTags: [],
        });
    } else if (selected.type === 'type') {
        // Type selection: pre-fill both CLO and type, use parent object from raw
        const parentObject = selected.raw?.object;
        tagsByPhoto.value[photoId].push({
            id: tagId,
            object: parentObject,
            cloId: selected.cloId,
            categoryId: selected.raw?.category?.id || null,
            categoryKey: selected.raw?.category?.key || null,
            typeId: selected.typeId,
            quantity: 1,
            pickedUp: true,
            brands: [],
            materials: [],
            customTags: [],
        });
    } else if (selected.type === 'brand') {
        tagsByPhoto.value[photoId].push({
            id: tagId,
            brand: selected.raw,
            quantity: 1,
            pickedUp: null,
            type: 'brand-only',
        });
    } else if (selected.type === 'material') {
        tagsByPhoto.value[photoId].push({
            id: tagId,
            material: selected.raw,
            quantity: 1,
            pickedUp: null,
            type: 'material-only',
        });
    }

    updateRecentTags(selected);
};

const handleCustomTag = (customTag) => {
    const photoId = ensurePhotoTags();
    if (!photoId) return;

    const tagId = Math.random().toString(16).slice(2);
    tagsByPhoto.value[photoId].push({
        id: tagId,
        custom: true,
        key: customTag.key,
        quantity: 1,
        pickedUp: null,
    });
};

const quickAddTag = (tag) => {
    handleTagSelection(tag);
};

const updateRecentTags = (tag) => {
    const filtered = recentTags.value.filter((t) => t.id !== tag.id);
    recentTags.value = [tag, ...filtered].slice(0, 5);
    localStorage.setItem('recentTags', JSON.stringify(recentTags.value));
};

// Tag modifications
const updateTagQuantity = (tagId, quantity) => {
    const tag = activeTags.value.find((t) => t.id === tagId);
    if (tag) tag.quantity = Math.max(1, Math.min(100, quantity));
};

const setPickedUp = (tagId, value) => {
    const tag = activeTags.value.find((t) => t.id === tagId);
    if (tag) tag.pickedUp = value;
};

const setTagType = (tagId, typeId) => {
    const tag = activeTags.value.find((t) => t.id === tagId);
    if (tag) tag.typeId = typeId;
};

const addTagDetail = (tagId, detail) => {
    const tag = activeTags.value.find((t) => t.id === tagId);
    if (!tag) return;

    if (detail.type === 'brand') {
        if (!tag.brands) tag.brands = [];
        if (!tag.brands.some((b) => b.id === detail.value.id)) {
            tag.brands.push(detail.value);
        }
    } else if (detail.type === 'material') {
        if (!tag.materials) tag.materials = [];
        if (!tag.materials.some((m) => m.id === detail.value.id)) {
            tag.materials.push(detail.value);
        }
    } else if (detail.type === 'object') {
        if (!tag.objects) tag.objects = [];
        if (!tag.objects.some((o) => o.id === detail.value.id)) {
            tag.objects.push(detail.value);
        }
    } else if (detail.type === 'custom') {
        if (!tag.customTags) tag.customTags = [];
        if (!tag.customTags.includes(detail.value)) {
            tag.customTags.push(detail.value);
        }
    }
};

const removeTagDetail = (tagId, detail) => {
    const tag = activeTags.value.find((t) => t.id === tagId);
    if (!tag) return;

    if (detail.type === 'brand') {
        tag.brands = tag.brands?.filter((b) => b.id !== detail.value.id) || [];
    } else if (detail.type === 'material') {
        tag.materials = tag.materials?.filter((m) => m.id !== detail.value.id) || [];
    } else if (detail.type === 'object') {
        tag.objects = tag.objects?.filter((o) => o.id !== detail.value.id) || [];
    } else if (detail.type === 'custom') {
        tag.customTags = tag.customTags?.filter((c) => c !== detail.value) || [];
    }
};

const removeTag = (tagId) => {
    const photoId = currentPhoto.value?.id;
    if (!photoId || !tagsByPhoto.value[photoId]) return;
    tagsByPhoto.value[photoId] = tagsByPhoto.value[photoId].filter((t) => t.id !== tagId);
};

const clearAllTags = () => {
    const photoId = currentPhoto.value?.id;
    if (photoId) {
        tagsByPhoto.value[photoId] = [];
    }
};

// Submit tags
const submitTags = async () => {
    if (activeTags.value.length === 0 || hasUnresolvedTags.value) return;

    isSubmitting.value = true;
    const photoId = currentPhoto.value.id;

    const tagsForUpload = activeTags.value.map((tag) => {
        // Use CLO-based payload when we have a CLO id
        if (tag.cloId) {
            return {
                category_litter_object_id: tag.cloId,
                litter_object_type_id: tag.typeId || null,
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
                materials: tag.materials?.map((m) => m.id) || [],
                brands: tag.brands?.map((b) => ({ id: b.id, quantity: b.quantity || 1 })) || [],
                custom_tags: tag.customTags || [],
            };
        }

        // Legacy format fallback for brand-only, material-only, custom-only
        if (tag.custom) {
            return {
                custom: true,
                key: tag.key,
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
            };
        } else if (tag.type === 'brand-only') {
            return {
                brand_only: true,
                brand: { id: tag.brand.id, key: tag.brand.key },
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
            };
        } else if (tag.type === 'material-only') {
            return {
                material_only: true,
                material: { id: tag.material.id, key: tag.material.key },
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
            };
        } else {
            return {
                object: { id: tag.object.id, key: tag.object.key },
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
                materials: tag.materials?.map((m) => ({ id: m.id, key: m.key })) || [],
                brands: tag.brands?.map((b) => ({ id: b.id, key: b.key })) || [],
                custom_tags: tag.customTags || [],
            };
        }
    });

    try {
        await photosStore.UPLOAD_TAGS({
            photoId: photoId,
            tags: tagsForUpload,
        });

        // Clear only this photo's tags after successful submit
        delete tagsByPhoto.value[photoId];

        // UPLOAD_TAGS already reloaded photos — the tagged photo is removed
        // from the untagged list, so currentPhotoIndex now points to the next
        // photo. Just clamp if we were at the end.
        const newLength = paginatedPhotos.value?.data?.length || 0;
        if (newLength === 0) {
            currentPhotoIndex.value = 0;
        } else if (currentPhotoIndex.value >= newLength) {
            currentPhotoIndex.value = newLength - 1;
        }
        imageLoading.value = true;
    } catch (error) {
        console.error('Failed to submit tags:', error);
    } finally {
        isSubmitting.value = false;
    }
};

// Watch for photo changes
watch(currentPhotoSrc, () => {
    imageLoading.value = true;
});
</script>
