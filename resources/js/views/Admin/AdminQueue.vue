<template>
    <div class="h-[calc(100vh-80px)] bg-gray-900 flex flex-col overflow-hidden">
        <!-- Header -->
        <AdminQueueHeader
            :photo="currentPhoto"
            :pending-count="adminStore.pendingCount"
            :current-index="currentIndex"
            :total-on-page="photos.length"
            :can-go-prev="canGoPrev"
            :can-go-next="canGoNext"
            :submitting="adminStore.submitting"
            :has-edits="hasEdits"
            @navigate="handleNavigation"
            @approve="handleApprove"
            @save-edits="handleSaveEdits"
            @reset-tags="handleResetTags"
            @delete="handleDelete"
        />

        <!-- Loading -->
        <div v-if="adminStore.loading" class="flex-1 flex items-center justify-center">
            <div class="text-gray-400">{{ $t('Loading photos...') }}</div>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="photos.length === 0"
            class="flex-1 flex flex-col items-center justify-center text-center px-4"
        >
            <svg class="w-20 h-20 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <h2 class="text-white text-xl font-semibold mb-2">{{ $t('Queue is clear!') }}</h2>
            <p class="text-gray-400">{{ $t('No pending photos match the current filters.') }}</p>
        </div>

        <!-- Main content -->
        <div v-else class="flex-1 overflow-hidden">
            <div class="flex h-full">
                <!-- Left: Filters sidebar -->
                <div class="w-56 flex-shrink-0 p-4 overflow-y-auto border-r border-gray-700">
                    <AdminQueueFilters
                        :filters="adminStore.filters"
                        :countries="adminStore.countries"
                        @update-filter="handleFilterUpdate"
                        @apply="applyFilters"
                        @reset="resetFilters"
                    />
                </div>

                <!-- Center: Photo viewer -->
                <div class="flex-1 p-4 min-w-0">
                    <PhotoViewer
                        :photo-src="currentPhoto?.filename"
                        :loading="imageLoading"
                        @image-loaded="imageLoading = false"
                    />
                </div>

                <!-- Right: Tags panel -->
                <div class="w-[480px] flex-shrink-0 h-full flex flex-col overflow-hidden p-4">
                    <!-- Search -->
                    <div class="space-y-3 mb-4 flex-shrink-0">
                        <UnifiedTagSearch
                            v-model="searchQuery"
                            :tags="searchableTags"
                            :brands="brandsList"
                            :materials="materialsList"
                            @tag-selected="handleTagSelection"
                            @custom-tag="handleCustomTag"
                            placeholder="Search tags to add..."
                        />
                    </div>

                    <!-- Active tags list -->
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

        <!-- Pagination -->
        <div
            v-if="adminStore.photos.last_page > 1"
            class="bg-gray-800 border-t border-gray-700 px-6 py-2 flex items-center justify-center gap-2 flex-shrink-0"
        >
            <button
                @click="goToPage(adminStore.photos.current_page - 1)"
                :disabled="adminStore.photos.current_page <= 1"
                class="px-3 py-1 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                {{ $t('Prev Page') }}
            </button>
            <span class="text-gray-400 text-sm">
                Page {{ adminStore.photos.current_page }} / {{ adminStore.photos.last_page }}
            </span>
            <button
                @click="goToPage(adminStore.photos.current_page + 1)"
                :disabled="adminStore.photos.current_page >= adminStore.photos.last_page"
                class="px-3 py-1 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                {{ $t('Next Page') }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useAdminStore } from '@stores/admin.js';
import { useTagsStore } from '@stores/tags/index.js';

import AdminQueueHeader from './components/AdminQueueHeader.vue';
import AdminQueueFilters from './components/AdminQueueFilters.vue';
import PhotoViewer from '@/views/General/Tagging/v2/components/PhotoViewer.vue';
import UnifiedTagSearch from '@/views/General/Tagging/v2/components/UnifiedTagSearch.vue';
import ActiveTagsList from '@/views/General/Tagging/v2/components/ActiveTagsList.vue';

const adminStore = useAdminStore();
const tagsStore = useTagsStore();

const currentIndex = ref(0);
const searchQuery = ref('');
const tagsByPhoto = ref({}); // { [photoId]: Tag[] }
const imageLoading = ref(true);
const originalTagsJson = ref({}); // Track original tags per photo for edit detection

// ─── Computed ──────────────────────────────────────────

const photos = computed(() => adminStore.photos.data || []);
const currentPhoto = computed(() => photos.value[currentIndex.value]);

const canGoPrev = computed(() => currentIndex.value > 0 || adminStore.photos.current_page > 1);
const canGoNext = computed(
    () =>
        currentIndex.value < photos.value.length - 1 ||
        adminStore.photos.current_page < adminStore.photos.last_page
);

const activeTags = computed(() => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return [];
    return tagsByPhoto.value[photoId] || [];
});

const hasEdits = computed(() => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return false;
    const current = JSON.stringify(tagsByPhoto.value[photoId] || []);
    return current !== (originalTagsJson.value[photoId] || '[]');
});

const searchableTags = computed(() => {
    const tags = [];

    // Objects: one entry per (object, category) pair for CLO disambiguation
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

    // Types
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

// ─── Tag Hydration ─────────────────────────────────────

/**
 * Convert API new_tags format to TagCard format.
 *
 * API:     { id, object: {id, key}, category: {id, key}, quantity, picked_up, extra_tags: [{type, tag: {id, key}}] }
 * TagCard: { id, object: {id, key}, quantity, pickedUp, brands: [{id, key}], materials: [{id, key}], customTags: [] }
 */
const hydrateTagsForPhoto = (photo) => {
    if (!photo?.new_tags?.length) return [];

    return photo.new_tags.map((apiTag) => {
        const tag = {
            id: `existing-${apiTag.id}`,
            quantity: apiTag.quantity || 1,
            pickedUp: apiTag.picked_up === true ? true : apiTag.picked_up === false ? false : null,
            cloId: apiTag.category_litter_object_id || null,
            categoryId: apiTag.category?.id || null,
            categoryKey: apiTag.category?.key || null,
            typeId: apiTag.litter_object_type_id || null,
            brands: [],
            materials: [],
            customTags: [],
        };

        // Primary tag type
        if (apiTag.object) {
            tag.object = { id: apiTag.object.id, key: apiTag.object.key };

            // Resolve cloId from tagsStore if not in API response
            if (!tag.cloId && tag.categoryId) {
                tag.cloId = tagsStore.getCloId(tag.categoryId, apiTag.object.id);
            }
        } else if (apiTag.primary_custom_tag) {
            tag.custom = true;
            tag.key = apiTag.primary_custom_tag.key;
        }

        // Extra tags → brands, materials, custom
        if (apiTag.extra_tags) {
            apiTag.extra_tags.forEach((extra) => {
                if (extra.type === 'brand' && extra.tag) {
                    tag.brands.push({ id: extra.tag.id, key: extra.tag.key });
                } else if (extra.type === 'material' && extra.tag) {
                    tag.materials.push({ id: extra.tag.id, key: extra.tag.key });
                } else if (extra.type === 'custom_tag' && extra.tag) {
                    tag.customTags.push(extra.tag.key);
                }
            });
        }

        return tag;
    });
};

// ─── Init ──────────────────────────────────────────────

onMounted(async () => {
    await Promise.all([adminStore.fetchPhotos(), adminStore.fetchCountries()]);

    if (tagsStore.objects.length === 0) {
        await tagsStore.GET_ALL_TAGS();
    }

    hydrateCurrentPhoto();
});

// Watch for photo changes to hydrate tags
watch(currentPhoto, () => {
    imageLoading.value = true;
    hydrateCurrentPhoto();
});

const hydrateCurrentPhoto = () => {
    const photo = currentPhoto.value;
    if (!photo) return;

    // Only hydrate if not already present (user edits preserved)
    if (!tagsByPhoto.value[photo.id]) {
        const hydrated = hydrateTagsForPhoto(photo);
        tagsByPhoto.value[photo.id] = hydrated;
        originalTagsJson.value[photo.id] = JSON.stringify(hydrated);
    }
};

// ─── Navigation ────────────────────────────────────────

const handleNavigation = async (direction) => {
    if (direction === 'next') {
        if (currentIndex.value < photos.value.length - 1) {
            currentIndex.value++;
        } else if (adminStore.photos.current_page < adminStore.photos.last_page) {
            await adminStore.fetchPhotos(adminStore.photos.current_page + 1);
            currentIndex.value = 0;
        }
    } else {
        if (currentIndex.value > 0) {
            currentIndex.value--;
        } else if (adminStore.photos.current_page > 1) {
            await adminStore.fetchPhotos(adminStore.photos.current_page - 1);
            currentIndex.value = photos.value.length - 1;
        }
    }
};

const goToPage = async (page) => {
    await adminStore.fetchPhotos(page);
    currentIndex.value = 0;
};

// ─── Actions ───────────────────────────────────────────

const handleApprove = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const success = await adminStore.approvePhoto(photoId);
    if (success) {
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleSaveEdits = async () => {
    if (!currentPhoto.value || !hasEdits.value) return;
    const photoId = currentPhoto.value.id;

    const tagsForUpload = buildTagsPayload();

    const success = await adminStore.updateTagsAndApprove(photoId, tagsForUpload);
    if (success) {
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleResetTags = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const success = await adminStore.resetTags(photoId);
    if (success) {
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleDelete = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const success = await adminStore.deletePhoto(photoId);
    if (success) {
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const cleanupPhotoState = (photoId) => {
    delete tagsByPhoto.value[photoId];
    delete originalTagsJson.value[photoId];
};

const clampIndex = () => {
    const len = photos.value.length;
    if (len === 0) {
        currentIndex.value = 0;
    } else if (currentIndex.value >= len) {
        currentIndex.value = len - 1;
    }
};

// ─── Tag Handling (same pattern as AddTags.vue) ────────

const ensurePhotoTags = () => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return null;
    if (!tagsByPhoto.value[photoId]) {
        tagsByPhoto.value[photoId] = [];
    }
    return photoId;
};

const handleTagSelection = (selected) => {
    if (!selected || !selected.raw) return;
    const photoId = ensurePhotoTags();
    if (!photoId) return;

    const tagId = Math.random().toString(16).slice(2);

    if (selected.type === 'object') {
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
    } else if (detail.type === 'custom') {
        tag.customTags = tag.customTags?.filter((c) => c !== detail.value) || [];
    }
};

const removeTag = (tagId) => {
    const photoId = currentPhoto.value?.id;
    if (!photoId || !tagsByPhoto.value[photoId]) return;
    tagsByPhoto.value[photoId] = tagsByPhoto.value[photoId].filter((t) => t.id !== tagId);
};

// ─── Filters ───────────────────────────────────────────

const handleFilterUpdate = (key, value) => {
    adminStore.setFilter(key, value);
};

const applyFilters = async () => {
    currentIndex.value = 0;
    await adminStore.fetchPhotos(1);
};

const resetFilters = async () => {
    adminStore.resetFilters();
    currentIndex.value = 0;
    await adminStore.fetchPhotos(1);
};

// ─── Keyboard Shortcuts ───────────────────────────────

const isInputFocused = () => {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
};

const handleKeydown = (e) => {
    if (isInputFocused()) return;
    if (adminStore.submitting) return;
    if (!currentPhoto.value) return;

    switch (e.key) {
        case 'a':
        case 'A':
            e.preventDefault();
            handleApprove();
            break;

        case 'd':
        case 'D':
            e.preventDefault();
            if (window.confirm('Delete this photo?')) {
                handleDelete();
            }
            break;

        case 'e':
        case 'E':
            if (hasEdits.value) {
                e.preventDefault();
                handleSaveEdits();
            }
            break;

        case 's':
        case 'S':
        case 'ArrowRight':
        case 'k':
        case 'K':
            e.preventDefault();
            handleNavigation('next');
            break;

        case 'ArrowLeft':
        case 'j':
        case 'J':
            e.preventDefault();
            handleNavigation('prev');
            break;

        case 'Escape':
            e.preventDefault();
            searchQuery.value = '';
            break;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

// ─── Build upload payload ──────────────────────────────

const buildTagsPayload = () => {
    return activeTags.value.map((tag) => {
        // Use CLO-based payload when we have a CLO id (matches AddTags.vue)
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
};
</script>
