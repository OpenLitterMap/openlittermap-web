<template>
    <div class="h-[calc(100vh-80px)] bg-gray-900 flex flex-col overflow-hidden">
        <!-- Header -->
        <FacilitatorQueueHeader
            :photo="currentPhoto"
            :pending-count="photosStore.pendingCount"
            :current-index="currentIndex"
            :total-on-page="photos.length"
            :can-go-prev="canGoPrev"
            :can-go-next="canGoNext"
            :submitting="photosStore.submitting"
            :has-edits="hasEdits"
            @navigate="handleNavigation"
            @approve="handleApprove"
            @save-edits="handleSaveEdits"
            @revoke="handleRevoke"
            @delete="handleDelete"
        />

        <!-- First-time explainer banner -->
        <div
            v-if="showApprovalGuide"
            class="mx-4 mt-3 bg-blue-500/10 border border-blue-500/20 rounded-xl px-4 py-3 flex items-start gap-3"
        >
            <svg class="w-5 h-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-sm text-blue-300 flex-1">
                {{ $t('Photos from your students are private until you approve them. Review each photo, check the tags, then press A to approve. You can also edit tags (E), revoke approval (R), or delete (D).') }}
            </p>
            <button class="text-blue-400/60 hover:text-blue-400 shrink-0" @click="dismissGuide">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="photosStore.loading" class="flex-1 flex items-center justify-center">
            <div class="text-gray-400">Loading photos...</div>
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
            <h2 class="text-white text-xl font-semibold mb-2">Queue is clear!</h2>
            <p class="text-gray-400">No photos match the current filters.</p>
        </div>

        <!-- Main content: 3-panel layout -->
        <div v-else class="flex-1 overflow-hidden">
            <div class="flex h-full">
                <!-- Left: Filters sidebar -->
                <div class="w-56 flex-shrink-0 p-4 overflow-y-auto border-r border-gray-700">
                    <FacilitatorQueueFilters
                        :status="filterStatus"
                        :date-from="filterDateFrom"
                        :date-to="filterDateTo"
                        @update-status="handleStatusFilter"
                        @update-date-from="filterDateFrom = $event; reloadPhotos()"
                        @update-date-to="filterDateTo = $event; reloadPhotos()"
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
            v-if="photosStore.photos.last_page > 1"
            class="bg-gray-800 border-t border-gray-700 px-6 py-2 flex items-center justify-center gap-2 flex-shrink-0"
        >
            <button
                @click="goToPage(photosStore.photos.current_page - 1)"
                :disabled="photosStore.photos.current_page <= 1"
                class="px-3 py-1 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Prev Page
            </button>
            <span class="text-gray-400 text-sm">
                Page {{ photosStore.photos.current_page }} / {{ photosStore.photos.last_page }}
            </span>
            <button
                @click="goToPage(photosStore.photos.current_page + 1)"
                :disabled="photosStore.photos.current_page >= photosStore.photos.last_page"
                class="px-3 py-1 text-sm bg-gray-700 text-white rounded hover:bg-gray-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Next Page
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useTeamPhotosStore } from '@stores/teamPhotos.js';
import { useTagsStore } from '@stores/tags/index.js';
import { useToast } from 'vue-toastification';

import FacilitatorQueueHeader from './components/FacilitatorQueueHeader.vue';
import FacilitatorQueueFilters from './components/FacilitatorQueueFilters.vue';
import PhotoViewer from '@/views/General/Tagging/v2/components/PhotoViewer.vue';
import UnifiedTagSearch from '@/views/General/Tagging/v2/components/UnifiedTagSearch.vue';
import ActiveTagsList from '@/views/General/Tagging/v2/components/ActiveTagsList.vue';

const props = defineProps({
    teamId: {
        type: Number,
        required: true,
    },
    isLeader: {
        type: Boolean,
        default: false,
    },
    isSchoolTeam: {
        type: Boolean,
        default: false,
    },
});

const photosStore = useTeamPhotosStore();
const tagsStore = useTagsStore();
const toast = useToast();

const currentIndex = ref(0);
const searchQuery = ref('');

// ── Approval guide banner ──
const showApprovalGuide = ref(
    !localStorage.getItem(`approval-guide-dismissed-${props.teamId}`),
);
const dismissGuide = () => {
    showApprovalGuide.value = false;
    localStorage.setItem(`approval-guide-dismissed-${props.teamId}`, 'true');
};
const tagsByPhoto = ref({});
const imageLoading = ref(true);
const originalTagsJson = ref({});

// Filter state
const filterStatus = ref('pending');
const filterDateFrom = ref('');
const filterDateTo = ref('');

// ─── Computed ──────────────────────────────────────────

const photos = computed(() => photosStore.photos.data || []);
const currentPhoto = computed(() => photos.value[currentIndex.value]);

const canGoPrev = computed(() => currentIndex.value > 0 || photosStore.photos.current_page > 1);
const canGoNext = computed(
    () =>
        currentIndex.value < photos.value.length - 1 ||
        photosStore.photos.current_page < photosStore.photos.last_page
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

        if (apiTag.object) {
            tag.object = { id: apiTag.object.id, key: apiTag.object.key };

            if (!tag.cloId && tag.categoryId) {
                tag.cloId = tagsStore.getCloId(tag.categoryId, apiTag.object.id);
            }
        } else if (apiTag.primary_custom_tag) {
            tag.custom = true;
            tag.key = apiTag.primary_custom_tag.key;
        }

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
    photosStore.setFilter(filterStatus.value);
    await photosStore.fetchPhotos(props.teamId);

    if (tagsStore.objects.length === 0) {
        await tagsStore.GET_ALL_TAGS();
    }

    hydrateCurrentPhoto();
});

watch(currentPhoto, () => {
    imageLoading.value = true;
    hydrateCurrentPhoto();
});

const hydrateCurrentPhoto = () => {
    const photo = currentPhoto.value;
    if (!photo) return;

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
        } else if (photosStore.photos.current_page < photosStore.photos.last_page) {
            await photosStore.fetchPhotos(props.teamId, photosStore.photos.current_page + 1);
            currentIndex.value = 0;
        }
    } else {
        if (currentIndex.value > 0) {
            currentIndex.value--;
        } else if (photosStore.photos.current_page > 1) {
            await photosStore.fetchPhotos(props.teamId, photosStore.photos.current_page - 1);
            currentIndex.value = photos.value.length - 1;
        }
    }
};

const goToPage = async (page) => {
    await photosStore.fetchPhotos(props.teamId, page);
    currentIndex.value = 0;
};

// ─── Filters ──────────────────────────────────────────

const handleStatusFilter = (status) => {
    filterStatus.value = status;
    photosStore.setFilter(status);
    reloadPhotos();
};

const resetFilters = () => {
    filterStatus.value = 'pending';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    photosStore.setFilter('pending');
    reloadPhotos();
};

const reloadPhotos = async () => {
    currentIndex.value = 0;
    await photosStore.fetchPhotos(props.teamId);
};

// ─── Actions ───────────────────────────────────────────

const handleApprove = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const count = await photosStore.approvePhotos(props.teamId, [photoId]);
    if (count > 0) {
        toast.success('Photo approved');
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleSaveEdits = async () => {
    if (!currentPhoto.value || !hasEdits.value) return;
    const photoId = currentPhoto.value.id;

    const tagsForUpload = buildTagsPayload();

    const success = await photosStore.updateTagsAndApprove(props.teamId, photoId, tagsForUpload);
    if (success) {
        toast.success('Tags updated and photo approved');
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleRevoke = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const count = await photosStore.revokePhotos(props.teamId, [photoId]);
    if (count > 0) {
        toast.success('Photo revoked');
        cleanupPhotoState(photoId);
        clampIndex();
    }
};

const handleDelete = async () => {
    if (!currentPhoto.value) return;
    const photoId = currentPhoto.value.id;

    const success = await photosStore.deletePhoto(props.teamId, photoId);
    if (success) {
        toast.success('Photo deleted');
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

// ─── Tag Handling ──────────────────────────────────────

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

// ─── Keyboard Shortcuts ───────────────────────────────

const isInputFocused = () => {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toLowerCase();
    return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
};

const handleKeydown = (e) => {
    if (isInputFocused()) return;
    if (photosStore.submitting) return;
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

        case 'r':
        case 'R':
            e.preventDefault();
            if (window.confirm('Revoke approval on this photo?')) {
                handleRevoke();
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
