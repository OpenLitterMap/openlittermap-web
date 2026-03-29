<template>
    <div class="h-[calc(100vh-73px)] bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 flex flex-col overflow-hidden">
        <!-- Success flash overlay -->
        <transition name="flash">
            <div v-if="showSuccessFlash" class="absolute inset-0 z-40 pointer-events-none border-2 border-emerald-400/60 rounded-xl" />
        </transition>

        <!-- Onboarding step indicator -->
        <div v-if="onboarding" class="px-4">
            <StepIndicator :current-step="3" />
        </div>

        <!-- Enhanced Header with integrated actions and XP display -->
        <TaggingHeader
            :current-photo="currentPhoto"
            :photos="paginatedPhotos"
            :current-index="currentPhotoIndex"
            :tags="activeTags"
            :xp-preview="calculateXP"
            :submitting="isSubmitting"
            :has-unresolved-tags="hasUnresolvedTags"
            :is-edit-mode="isEditMode"
            @navigate="handleNavigation"
            @skip="skipPhoto"
            @clear="clearAllTags"
            @submit="submitTags"
        />

        <!-- Main Content Area -->
        <div class="flex-1 overflow-hidden relative">
            <!-- Empty state (no untagged photos) -->
            <div v-if="!hasPhotos && !imageLoading" class="flex items-center justify-center h-full">
                <div class="text-center max-w-md px-6">
                    <div class="w-16 h-16 mx-auto mb-6 bg-emerald-500/20 border border-emerald-500/30 rounded-2xl flex items-center justify-center">
                        <svg class="w-8 h-8 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-white mb-2">{{ $t('All photos tagged!') }}</h2>
                    <p class="text-white/50 text-sm mb-6">{{ $t('Upload more photos or review your tagged photos.') }}</p>
                    <div class="flex items-center justify-center gap-3">
                        <router-link to="/upload" class="px-4 py-2 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg text-sm font-medium hover:bg-emerald-500/30 transition-colors">
                            {{ $t('Upload Photos') }}
                        </router-link>
                        <router-link to="/uploads" class="px-4 py-2 bg-white/5 text-white/60 border border-white/10 rounded-lg text-sm font-medium hover:bg-white/10 transition-colors">
                            {{ $t('My Photos') }}
                        </router-link>
                    </div>
                </div>
            </div>

            <!-- Two-panel layout -->
            <div v-else class="flex flex-col lg:flex-row p-4 lg:p-6 gap-4 lg:gap-6 h-full overflow-hidden">
                <!-- Left Panel: Photo Viewer (50-60%) -->
                <div class="lg:w-[55%] h-[35vh] lg:h-full flex-shrink-0">
                    <PhotoViewer
                        :photo-src="currentPhotoSrc"
                        :loading="imageLoading"
                        :deleting="isDeleting"
                        @image-loaded="imageLoading = false"
                        @delete="deletePhoto"
                    />
                </div>

                <!-- Right Panel: Tag Panel (40-50%) -->
                <div class="lg:w-[45%] flex-1 min-h-0 flex flex-col overflow-hidden">
                    <!-- Search Section -->
                    <div class="space-y-3 mb-4 flex-shrink-0">
                        <!-- Learn about tagging prompt -->
                        <div
                            v-if="showTaggingHelp"
                            class="bg-blue-500/10 border border-blue-500/20 rounded-lg px-4 py-2 flex items-center justify-between"
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
                                {{ $t('Learn about tagging') }}
                            </a>
                            <button @click="hideTaggingHelp" class="text-white/30 hover:text-white/60 transition-colors">
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

                        <!-- Onboarding quick-select chips -->
                        <OnboardingChips
                            v-if="onboarding && hasPhotos"
                            @add-tag="handleTagSelection"
                        />

                        <!-- Reassurance copy during onboarding -->
                        <p v-if="onboarding && activeTags.length > 0" class="mb-2 text-xs text-emerald-400/70">
                            One tag is enough to get started. You can always edit later.
                        </p>

                        <!-- Main Search Bar -->
                        <UnifiedTagSearch
                            ref="searchRef"
                            v-model="searchQuery"
                            :tags="searchableTags"
                            :brands="brandsList"
                            :materials="materialsList"
                            input-id="unified-tag-search-input"
                            @tag-selected="handleTagSelection"
                            @custom-tag="handleCustomTag"
                            placeholder="Search All Tags or Create Your Own!"
                        />

                        <!-- Quick suggestions -->
                        <div v-if="recentTags.length > 0 && hasPhotos">
                            <span class="text-xs text-white/40 mr-2">{{ $t('Recent:') }}</span>
                            <div class="flex flex-wrap gap-2 mt-1">
                                <button
                                    v-for="tag in recentTags"
                                    :key="tag.id"
                                    @click="quickAddTag(tag)"
                                    class="text-xs px-2 py-1 bg-white/5 text-white/60 border border-white/10 rounded hover:bg-white/10 transition-colors"
                                >
                                    {{ tag.label || formatKey(tag.key) }}
                                    <span v-if="tag.categoryKey" class="text-white/30">· {{ tag.categoryLabel || formatKey(tag.categoryKey) }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0">
                        <ActiveTagsList
                            :has-photos="hasPhotos"
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

                    <!-- Confirm button (sticky bottom of tag panel) -->
                    <div class="flex-shrink-0 pt-3 mt-auto">
                        <button
                            @click="submitTags"
                            :disabled="!canSubmit"
                            class="w-full py-3 bg-emerald-500 hover:bg-emerald-600 disabled:bg-white/5 disabled:text-white/20 disabled:border-white/10 text-white font-semibold rounded-xl transition-all flex items-center justify-center gap-2 text-sm"
                        >
                            <template v-if="!isSubmitting">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ isEditMode ? (activeTags.length === 0 ? $t('Clear All Tags') : $t('Update Tags')) : $t('Confirm Tags') }}
                                <span v-if="activeTags.length > 0" class="text-emerald-200/80 text-xs">({{ activeTags.length }})</span>
                            </template>
                            <template v-else>
                                <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
                                </svg>
                                {{ $t('Saving...') }}
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Keyboard shortcuts hint -->
        <div class="absolute bottom-4 left-4 z-30">
            <button
                @click="showShortcuts = !showShortcuts"
                class="w-7 h-7 bg-white/5 border border-white/10 rounded-lg flex items-center justify-center text-white/30 hover:text-white/60 hover:bg-white/10 transition-colors text-xs font-mono"
                :title="$t('Keyboard shortcuts')"
            >?</button>
            <div
                v-if="showShortcuts"
                class="absolute bottom-9 left-0 bg-slate-800/95 backdrop-blur border border-white/10 rounded-xl p-4 text-xs space-y-1.5 w-56 max-w-[calc(100vw-2rem)] shadow-xl"
            >
                <p class="text-white/50 text-[10px] font-semibold uppercase tracking-widest mb-2">{{ $t('Keyboard shortcuts') }}</p>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Confirm tags') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">Enter</kbd></div>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Focus search') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">/</kbd></div>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Clear search') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">Esc</kbd></div>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Previous photo') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">J / ←</kbd></div>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Next photo') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">K / →</kbd></div>
                <div class="flex justify-between"><span class="text-white/60">{{ $t('Set quantity') }}</span><kbd class="text-white/40 bg-white/5 px-1.5 py-0.5 rounded font-mono">1–9</kbd></div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vue-toastification';
import { usePhotosStore } from '@stores/photos/index.js';
import { useTagsStore } from '@stores/tags/index.js';
import { useUserStore } from '@stores/user/index.js';

import TaggingHeader from './components/TaggingHeader.vue';
import UnifiedTagSearch from './components/UnifiedTagSearch.vue';
import PhotoViewer from './components/PhotoViewer.vue';
import ActiveTagsList from './components/ActiveTagsList.vue';
import StepIndicator from '@/components/onboarding/StepIndicator.vue';
import OnboardingChips from '@/components/onboarding/OnboardingChips.vue';
import { calculateTotalXp, getToastSummary } from './useXpCalculator.js';

const props = defineProps({
    onboarding: {
        type: Boolean,
        default: false,
    },
});

const { t } = useI18n();
const toast = useToast();
const route = useRoute();
const router = useRouter();

// Stores
const photosStore = usePhotosStore();
const tagsStore = useTagsStore();
const userStore = useUserStore();

// User's default picked_up preference (items_remaining=false means picked_up=true)
const defaultPickedUp = computed(() => {
    const val = userStore.user?.picked_up;
    if (val === true || val === 1) return true;
    if (val === false || val === 0) return false;
    return null;
});

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
const isEditMode = ref(false);
const editPhotoId = ref(null);
const showShortcuts = ref(false);
const showSuccessFlash = ref(false);
const isDeleting = ref(false);
const searchRef = ref(null);

// Computed
const paginatedPhotos = computed(() => photosStore.paginated);
const currentPhoto = computed(() => paginatedPhotos.value?.data?.[currentPhotoIndex.value]);
const currentPhotoSrc = computed(() => currentPhoto.value?.filename);
const hasPhotos = computed(() => !!currentPhoto.value);

// Get current photo's tags
const activeTags = computed(() => {
    const photoId = currentPhoto.value?.id;
    if (!photoId) return [];
    return tagsByPhoto.value[photoId] || [];
});

/**
 * Translate a tag key using i18n. Returns the translated label if different from the key path,
 * otherwise falls back to formatKey (title-casing the raw key).
 */
const translateTag = (key, i18nPrefix) => {
    const path = `litter.${i18nPrefix}.${key}`;
    const translated = t(path);
    // vue-i18n returns the key path if no translation exists
    return translated !== path ? translated : formatKey(key);
};

// Create searchable tags index combining all tag types
// One entry per (object, category) pair to disambiguate objects that appear in multiple categories
const searchableTags = computed(() => {
    const tags = [];
    const seenCompound = new Set();

    // Build set of all base object keys so compound entries don't duplicate them
    const baseObjectKeys = new Set(tagsStore.objects.map((o) => o.key));

    // Objects: one entry per (object, category) pair + compound entries for types
    tagsStore.objects.forEach((obj) => {
        if (obj.categories?.length) {
            obj.categories.forEach((cat) => {
                const cloId = tagsStore.getCloId(cat.id, obj.id);
                const label = translateTag(obj.key, cat.key);
                const catLabel = translateTag(cat.key, 'categories');

                // Base object entry (always present)
                tags.push({
                    id: `obj-${obj.id}-cat-${cat.id}`,
                    key: obj.key,
                    label,
                    categoryLabel: catLabel,
                    lowerKey: `${obj.key} ${label}`.toLowerCase(),
                    text: obj.key,
                    type: 'object',
                    categoryId: cat.id,
                    categoryKey: cat.key,
                    cloId: cloId,
                    raw: obj,
                });

                // Compound entries: one per type valid for this CLO (skip 'unknown')
                if (cloId) {
                    const cloTypes = tagsStore.getTypesForClo(cloId);
                    cloTypes.forEach((typeObj) => {
                        if (typeObj.key === 'unknown') return;
                        if (obj.key.includes(typeObj.key)) return;
                        const compoundKey = `${typeObj.key}_${obj.key}`;
                        if (seenCompound.has(compoundKey)) return;
                        if (baseObjectKeys.has(compoundKey)) return; // skip if old object with same key exists
                        seenCompound.add(compoundKey);
                        const compoundLabel = `${formatKey(typeObj.key)} ${label}`;
                        tags.push({
                            id: `obj-${obj.id}-cat-${cat.id}-type-${typeObj.key}`,
                            key: `${typeObj.key}_${obj.key}`,
                            label: compoundLabel,
                            categoryLabel: catLabel,
                            lowerKey: `${typeObj.key} ${obj.key} ${typeObj.key}_${obj.key} ${cat.key} ${compoundLabel} ${label}`.toLowerCase(),
                            text: `${typeObj.key}_${obj.key}`,
                            type: 'object',
                            objectId: obj.id,
                            objectKey: obj.key,
                            preselectedType: typeObj.key,
                            categoryId: cat.id,
                            categoryKey: cat.key,
                            cloId: cloId,
                            raw: obj,
                        });
                    });
                }
            });
        } else {
            const label = formatKey(obj.key);
            tags.push({
                id: `obj-${obj.id}`,
                key: obj.key,
                label,
                lowerKey: `${obj.key} ${label}`.toLowerCase(),
                text: obj.key,
                type: 'object',
                categoryId: null,
                categoryKey: null,
                cloId: null,
                raw: obj,
            });
        }
    });

    tagsStore.brands.forEach((brand) => {
        const label = translateTag(brand.key, 'brands');
        tags.push({
            id: `brand-${brand.id}`,
            key: brand.key,
            label,
            lowerKey: `${brand.key} ${label} ${formatKey(brand.key)}`.toLowerCase(),
            text: brand.key,
            type: 'brand',
            raw: brand,
        });
    });

    tagsStore.materials.forEach((material) => {
        const label = translateTag(material.key, 'material');
        tags.push({
            id: `mat-${material.id}`,
            key: material.key,
            label,
            lowerKey: `${material.key} ${label}`.toLowerCase(),
            text: material.key,
            type: 'material',
            raw: material,
        });
    });

    return tags;
});

const brandsList = computed(() => tagsStore.brands || []);
const materialsList = computed(() => tagsStore.materials || []);

// Progress tracking
const untaggedTotal = computed(() => photosStore.untaggedStats.leftToTag ?? paginatedPhotos.value?.total ?? 0);
const taggedCount = computed(() => {
    const initial = photosStore.untaggedStats.totalPhotos ?? 0;
    return Math.max(0, initial - untaggedTotal.value);
});
const progressPercent = computed(() => {
    const total = photosStore.untaggedStats.totalPhotos ?? 0;
    if (total === 0) return 0;
    return Math.min(100, (taggedCount.value / total) * 100);
});

// XP Calculation — delegates to shared composable (useXpCalculator.js)
const calculateXP = computed(() => {
    if (!hasPhotos.value) return 0;
    return calculateTotalXp(activeTags.value);
});

// Validation: check if any object tag is missing its CLO id
const hasUnresolvedTags = computed(() => {
    return activeTags.value.some((tag) => tag.object && !tag.cloId);
});

const canSubmit = computed(() => {
    if (isSubmitting.value || hasUnresolvedTags.value) return false;
    if (activeTags.value.length === 0 && !isEditMode.value) return false;
    return true;
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

// Convert API new_tags format to frontend tag format
const convertExistingTags = (photo) => {
    if (!photo.new_tags?.length) return [];

    return photo.new_tags.map((apiTag) => {
        const tagId = Math.random().toString(16).slice(2);

        // Extra-tag-only (no object) — classify by primary extra type
        if (!apiTag.object && apiTag.extra_tags?.length) {
            const brands = [];
            const materials = [];
            const customTags = [];

            apiTag.extra_tags.forEach((extra) => {
                if (extra.type === 'brand' && extra.tag) {
                    brands.push({ id: extra.tag.id, key: extra.tag.key, quantity: extra.quantity || 1 });
                } else if (extra.type === 'material' && extra.tag) {
                    materials.push({ id: extra.tag.id, key: extra.tag.key });
                } else if (extra.type === 'custom_tag' && extra.tag) {
                    customTags.push(extra.tag.key);
                }
            });

            // Single brand with no other extras → brand-only card
            if (brands.length === 1 && !materials.length && !customTags.length) {
                return {
                    id: tagId,
                    brand: brands[0],
                    quantity: apiTag.quantity || 1,
                    pickedUp: apiTag.picked_up,
                    type: 'brand-only',
                };
            }
            // Single material with no other extras → material-only card
            if (materials.length === 1 && !brands.length && !customTags.length) {
                return {
                    id: tagId,
                    material: materials[0],
                    quantity: apiTag.quantity || 1,
                    pickedUp: apiTag.picked_up,
                    type: 'material-only',
                };
            }
            // Single custom tag with no other extras → custom tag card
            if (customTags.length === 1 && !brands.length && !materials.length) {
                return {
                    id: tagId,
                    custom: true,
                    key: customTags[0],
                    quantity: apiTag.quantity || 1,
                    pickedUp: apiTag.picked_up,
                };
            }
            // Mixed extras or multiple of same type → custom card with all details
            return {
                id: tagId,
                custom: true,
                key: customTags[0] || brands[0]?.key || materials[0]?.key || 'custom tag',
                quantity: apiTag.quantity || 1,
                pickedUp: apiTag.picked_up,
                brands,
                materials,
                customTags: customTags.slice(1),
            };
        }

        // Standard object tag
        const cloId = apiTag.category_litter_object_id || tagsStore.getCloId(apiTag.category?.id, apiTag.object?.id);

        const brands = [];
        const materials = [];
        const customTags = [];

        apiTag.extra_tags?.forEach((extra) => {
            if (extra.type === 'brand' && extra.tag) {
                brands.push({ id: extra.tag.id, key: extra.tag.key, quantity: extra.quantity || 1 });
            } else if (extra.type === 'material' && extra.tag) {
                materials.push({ id: extra.tag.id, key: extra.tag.key });
            } else if (extra.type === 'custom_tag' && extra.tag) {
                customTags.push(extra.tag.key);
            }
        });

        return {
            id: tagId,
            object: apiTag.object,
            cloId: cloId,
            categoryId: apiTag.category?.id || null,
            categoryKey: apiTag.category?.key || null,
            typeId: apiTag.litter_object_type_id || null,
            typeKey: apiTag.litter_object_type_id
                ? tagsStore.types.find((t) => t.id === apiTag.litter_object_type_id)?.key || null
                : null,
            quantity: apiTag.quantity || 1,
            pickedUp: apiTag.picked_up ?? true,
            brands,
            materials,
            customTags,
        };
    });
};

// Initialize
onMounted(async () => {
    const tagsPromise = tagsStore.objects.length === 0
        ? tagsStore.GET_ALL_TAGS()
        : Promise.resolve();

    // Check for specific photo (photo query param)
    const photoIdParam = route.query.photo;
    if (photoIdParam) {
        // Edit mode needs tags for CLO lookups — await tags first
        await tagsPromise;

        editPhotoId.value = parseInt(photoIdParam);

        const photo = await photosStore.GET_SINGLE_PHOTO(editPhotoId.value);
        if (photo) {
            // Set up the store with just this photo
            photosStore.paginated = {
                data: [photo],
                current_page: 1,
                last_page: 1,
                per_page: 1,
                total: 1,
            };
            photosStore.photos = [photo];

            // Only enter edit mode if photo already has tags (replace vs add)
            if (photo.new_tags?.length > 0) {
                isEditMode.value = true;
                tagsByPhoto.value[photo.id] = convertExistingTags(photo);
            }
        }
    } else {
        // Normal mode: load tags and photos in parallel
        await Promise.all([
            tagsPromise,
            photosStore.fetchUntaggedData(1, { tagged: false }),
        ]);
    }

    // If no photos available after fetch, stop showing loading state
    if (!currentPhoto.value) {
        imageLoading.value = false;
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
    // Ctrl/Cmd+Enter or bare Enter (when not in input): confirm tags
    if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        if (activeTags.value.length > 0 && !hasUnresolvedTags.value) {
            submitTags();
        }
        return;
    }

    // All other shortcuts: skip if user is typing in a form field
    const target = event.target;
    const isInput = target.tagName === 'INPUT' || target.tagName === 'SELECT' || target.tagName === 'TEXTAREA';

    // Escape: clear search or close shortcuts panel
    if (event.key === 'Escape') {
        if (showShortcuts.value) {
            showShortcuts.value = false;
            return;
        }
        if (isInput) {
            target.blur();
            return;
        }
    }

    if (isInput) return;

    // Enter (bare, not in input): confirm tags
    if (event.key === 'Enter') {
        event.preventDefault();
        if (activeTags.value.length > 0 && !hasUnresolvedTags.value) {
            submitTags();
        }
        return;
    }

    // / : focus search
    if (event.key === '/') {
        event.preventDefault();
        const input = searchRef.value?.$el?.querySelector('input') || document.getElementById('unified-tag-search-input');
        input?.focus();
        return;
    }

    // J or ArrowLeft: previous photo
    if (event.key === 'j' || event.key === 'J' || event.key === 'ArrowLeft') {
        handleNavigation('prev');
        return;
    }

    // K or ArrowRight: next photo
    if (event.key === 'k' || event.key === 'K' || event.key === 'ArrowRight') {
        handleNavigation('next');
        return;
    }

    // 1-9: set quantity on last tag
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
    // Only show loading skeleton if there's actually a photo to load
    imageLoading.value = !!currentPhoto.value;
};

const skipPhoto = () => {
    handleNavigation('next');
};

const deletePhoto = async () => {
    const photoId = currentPhoto.value?.id;
    if (!photoId || isDeleting.value) return;

    isDeleting.value = true;
    try {
        // Clear any pending tags for this photo
        delete tagsByPhoto.value[photoId];

        // DELETE_PHOTO handles metrics reversal, S3 cleanup, soft delete, toast, and refreshes photos
        await photosStore.DELETE_PHOTO(photoId);

        // Refresh user XP/level (non-blocking)
        userStore.REFRESH_USER();

        // Clamp index after the photo list is refreshed
        const newLength = paginatedPhotos.value?.data?.length || 0;
        if (newLength === 0) {
            currentPhotoIndex.value = 0;
        } else if (currentPhotoIndex.value >= newLength) {
            currentPhotoIndex.value = newLength - 1;
        }
        imageLoading.value = true;
    } catch (error) {
        // DELETE_PHOTO already shows error toast
        console.error('Failed to delete photo:', error);
    } finally {
        isDeleting.value = false;
    }
};

const hideTaggingHelp = () => {
    showTaggingHelp.value = false;
    localStorage.setItem('hideTaggingHelp', 'true');
};

// Tag handling — increments quantity if an identical tag already exists
const handleTagSelection = (selected) => {
    if (!selected || !selected.raw) return;
    const photoId = ensurePhotoTags();
    if (!photoId) return;

    const tags = tagsByPhoto.value[photoId];

    // Resolve preselectedType name to a type ID
    let resolvedTypeId = null;
    if (selected.preselectedType && selected.cloId) {
        const availableTypes = tagsStore.getTypesForClo(selected.cloId);
        const matched = availableTypes.find((t) => t.key === selected.preselectedType);
        resolvedTypeId = matched?.id || null;
    }

    // Check for existing duplicate and increment quantity instead of adding a new entry
    let existing = null;
    if (selected.type === 'object') {
        if (resolvedTypeId) {
            existing = tags.find((t) => t.cloId === selected.cloId && t.typeId === resolvedTypeId);
        } else {
            existing = tags.find((t) => t.cloId && t.cloId === selected.cloId && !t.typeId);
        }
    } else if (selected.type === 'brand') {
        existing = tags.find((t) => t.type === 'brand-only' && t.brand?.id === selected.raw.id);
    } else if (selected.type === 'material') {
        existing = tags.find((t) => t.type === 'material-only' && t.material?.id === selected.raw.id);
    }

    if (existing) {
        existing.quantity = Math.min(100, existing.quantity + 1);
        updateRecentTags(selected);
        return;
    }

    const tagId = Math.random().toString(16).slice(2);

    if (selected.type === 'object') {
        // Use pre-resolved cloId and categoryKey from the search index entry
        tags.push({
            id: tagId,
            object: selected.raw,
            cloId: selected.cloId || null,
            categoryId: selected.categoryId || null,
            categoryKey: selected.categoryKey || null,
            typeId: resolvedTypeId,
            typeKey: resolvedTypeId ? tagsStore.types.find((t) => t.id === resolvedTypeId)?.key || null : null,
            quantity: 1,
            pickedUp: defaultPickedUp.value,
            brands: [],
            materials: [],
            customTags: [],
        });
    } else if (selected.type === 'brand') {
        tags.push({
            id: tagId,
            brand: selected.raw,
            quantity: 1,
            pickedUp: defaultPickedUp.value,
            type: 'brand-only',
        });
    } else if (selected.type === 'material') {
        tags.push({
            id: tagId,
            material: selected.raw,
            quantity: 1,
            pickedUp: defaultPickedUp.value,
            type: 'material-only',
        });
    }

    updateRecentTags(selected);
};

const handleCustomTag = (customTag) => {
    const photoId = ensurePhotoTags();
    if (!photoId) return;

    const tags = tagsByPhoto.value[photoId];
    const existing = tags.find((t) => t.custom && t.key === customTag.key);
    if (existing) {
        existing.quantity = Math.min(100, existing.quantity + 1);
        return;
    }

    const tagId = Math.random().toString(16).slice(2);
    tags.push({
        id: tagId,
        custom: true,
        key: customTag.key,
        quantity: 1,
        pickedUp: defaultPickedUp.value,
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
    if (!tag) return;
    tag.typeId = typeId;
    tag.typeKey = typeId ? tagsStore.types.find((t) => t.id === typeId)?.key || null : null;
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
    if (isSubmitting.value) return;
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    const photoId = currentPhoto.value.id;
    const photoLat = currentPhoto.value.lat;
    const photoLon = currentPhoto.value.lon;

    // Snapshot XP before tags are cleared
    const submittedXp = calculateXP.value;
    const submittedTags = [...activeTags.value];

    // Consolidate standalone custom tags into a single payload
    const rawTags = activeTags.value;
    const standaloneCustom = rawTags.filter((t) => t.custom && !t.cloId);
    const nonCustom = rawTags.filter((t) => !t.custom || t.cloId);

    const consolidated = [...nonCustom];
    if (standaloneCustom.length > 0) {
        // Merge all standalone custom tags into one entry
        const allKeys = [];
        const allBrands = [];
        const allMaterials = [];
        standaloneCustom.forEach((t) => {
            allKeys.push(t.key);
            if (t.brands?.length) allBrands.push(...t.brands);
            if (t.materials?.length) allMaterials.push(...t.materials);
            if (t.customTags?.length) allKeys.push(...t.customTags);
        });
        consolidated.push({
            custom: true,
            key: allKeys[0],
            customTags: allKeys.slice(1),
            brands: allBrands,
            materials: allMaterials,
            quantity: standaloneCustom[0].quantity,
            pickedUp: standaloneCustom[0].pickedUp,
        });
    }

    const tagsForUpload = consolidated.map((tag) => {
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
            const payload = {
                custom: true,
                key: tag.key,
                quantity: tag.quantity,
                picked_up: tag.pickedUp,
            };
            // Include extras if the custom card has detail tags
            if (tag.brands?.length) payload.brands = tag.brands.map((b) => ({ id: b.id, quantity: b.quantity || 1 }));
            if (tag.materials?.length) payload.materials = tag.materials.map((m) => m.id);
            if (tag.customTags?.length) payload.custom_tags = tag.customTags;
            return payload;
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
        if (isEditMode.value) {
            await photosStore.REPLACE_TAGS({
                photoId: photoId,
                tags: tagsForUpload,
            });

            userStore.REFRESH_USER();
            router.push('/uploads');
        } else {
            const result = await photosStore.UPLOAD_TAGS({
                photoId: photoId,
                tags: tagsForUpload,
            });

            if (result && !result.success) {
                throw new Error(result.message || 'Failed to save tags');
            }

            // Refresh user XP/level (non-blocking)
            userStore.REFRESH_USER();

            // Show XP toast
            toast.success(`+${submittedXp} XP earned\n${getToastSummary(submittedTags)}`, { timeout: 3000 });

            // Clear only this photo's tags after successful submit
            delete tagsByPhoto.value[photoId];

            // Onboarding: set completion optimistically (avoids redirect loop
            // if REFRESH_USER hasn't returned before Celebration CTAs are clicked)
            if (props.onboarding) {
                if (userStore.user) {
                    userStore.user.onboarding_completed_at = new Date().toISOString();
                }
                router.push({ path: '/onboarding/complete', query: { photo: photoId, lat: photoLat, lon: photoLon } });
                return;
            }

            // If we came from a specific photo link, go back to uploads
            if (editPhotoId.value) {
                router.push('/uploads');
            } else {
                // Show success flash then auto-advance
                showSuccessFlash.value = true;
                setTimeout(() => { showSuccessFlash.value = false; }, 400);

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
            }
        }
    } catch (error) {
        console.error('Failed to submit tags:', error);
        const msg = error?.response?.data?.message || error?.response?.data?.errors?.tags?.[0] || 'Failed to save tags. Please try again.';
        toast.error(msg);
    } finally {
        isSubmitting.value = false;
    }
};

// Watch for photo changes
watch(currentPhotoSrc, () => {
    imageLoading.value = true;
});
</script>

<style scoped>
.flash-enter-active {
    animation: flash-in 0.4s ease-out;
}
.flash-leave-active {
    animation: flash-out 0.3s ease-in;
}
@keyframes flash-in {
    0% { opacity: 0; }
    30% { opacity: 1; }
    100% { opacity: 0; }
}
@keyframes flash-out {
    from { opacity: 0; }
    to { opacity: 0; }
}
</style>
