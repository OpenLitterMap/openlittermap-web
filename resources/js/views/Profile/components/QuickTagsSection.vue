<template>
    <div class="bg-white/5 border border-white/10 rounded-xl p-6">
        <h3 class="text-white font-semibold mb-1">{{ $t('Quick Tags') }}</h3>
        <p class="text-white/30 text-xs mb-4">{{ $t('Tags that appear as shortcuts when tagging photos') }}</p>

        <!-- Loading -->
        <div v-if="quickTagsStore.loading || catalogLoading" class="text-white/40 text-sm py-4 text-center">
            {{ $t('Loading...') }}
        </div>

        <!-- Empty state -->
        <div v-else-if="quickTagsStore.tags.length === 0" class="text-center py-6">
            <svg class="w-10 h-10 mx-auto mb-3 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            <p class="text-white/40 text-sm mb-2">{{ $t('No quick tags yet.') }}</p>
            <p class="text-white/30 text-xs">
                {{ $t('Star any tag while tagging to add it here.') }}
                <router-link to="/tag" class="text-emerald-400 hover:text-emerald-300 underline">
                    {{ $t('Go to tagging') }}
                </router-link>
            </p>
        </div>

        <!-- Tag list -->
        <div v-else class="space-y-1">
            <div v-if="quickTagsStore.dirty" class="text-emerald-400/60 text-xs mb-2">{{ $t('Saving...') }}</div>

            <div
                v-for="(tag, index) in quickTagsStore.tags"
                :key="tag.id"
                class="group"
            >
                <!-- Display row -->
                <div
                    v-if="editingIndex !== index"
                    class="flex items-center gap-2 py-2 px-3 rounded-lg hover:bg-white/5 transition"
                >
                    <!-- Tag info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-white text-sm truncate">{{ resolveTagName(tag) }}</span>
                            <span class="text-white/30 text-xs flex-shrink-0">x{{ tag.quantity }}</span>
                            <span
                                v-if="tag.materials?.length"
                                class="text-white/20 text-xs flex-shrink-0"
                                :title="resolveMaterialNames(tag.materials)"
                            >
                                {{ tag.materials.length }} mat
                            </span>
                            <span
                                v-if="tag.brands?.length"
                                class="text-white/20 text-xs flex-shrink-0"
                                :title="resolveBrandNames(tag.brands)"
                            >
                                {{ tag.brands.length }} brand{{ tag.brands.length > 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div class="text-white/20 text-xs">
                            {{ resolveCategoryName(tag) }}
                            <template v-if="resolveTypeName(tag)"> &middot; {{ resolveTypeName(tag) }}</template>
                            <template v-if="tag.picked_up === true"> &middot; picked up</template>
                            <template v-else-if="tag.picked_up === false"> &middot; not picked up</template>
                        </div>
                    </div>

                    <!-- Controls -->
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            :disabled="index === 0"
                            class="p-1 rounded text-white/30 hover:text-white/60 disabled:opacity-20 disabled:cursor-not-allowed"
                            :title="$t('Move up')"
                            @click="quickTagsStore.moveTag(index, index - 1)"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button
                            :disabled="index === quickTagsStore.tags.length - 1"
                            class="p-1 rounded text-white/30 hover:text-white/60 disabled:opacity-20 disabled:cursor-not-allowed"
                            :title="$t('Move down')"
                            @click="quickTagsStore.moveTag(index, index + 1)"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <button
                            class="p-1 rounded text-white/30 hover:text-emerald-400"
                            :title="$t('Edit')"
                            @click="editingIndex = index; editDraft = { ...tag }"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            v-if="confirmDeleteIndex !== index"
                            class="p-1 rounded text-white/30 hover:text-red-400"
                            :title="$t('Delete')"
                            @click="confirmDeleteIndex = index"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <button
                            v-else
                            class="px-2 py-0.5 rounded text-xs bg-red-500/20 text-red-400 hover:bg-red-500/30"
                            @click="quickTagsStore.deleteTag(index); confirmDeleteIndex = null"
                        >
                            {{ $t('Confirm?') }}
                        </button>
                    </div>
                </div>

                <!-- Inline edit panel -->
                <div
                    v-else
                    class="bg-white/5 border border-white/10 rounded-lg p-4 space-y-3"
                >
                    <div class="text-white text-sm font-medium mb-2">{{ resolveTagName(tag) }}</div>

                    <!-- Quantity -->
                    <div class="flex items-center justify-between">
                        <span class="text-white/60 text-sm">{{ $t('Quantity') }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                class="w-7 h-7 rounded bg-white/10 text-white/60 hover:bg-white/20 text-sm"
                                :disabled="editDraft.quantity <= 1"
                                @click="editDraft.quantity = Math.max(1, editDraft.quantity - 1)"
                            >-</button>
                            <span class="text-white text-sm w-6 text-center">{{ editDraft.quantity }}</span>
                            <button
                                class="w-7 h-7 rounded bg-white/10 text-white/60 hover:bg-white/20 text-sm"
                                :disabled="editDraft.quantity >= 10"
                                @click="editDraft.quantity = Math.min(10, editDraft.quantity + 1)"
                            >+</button>
                        </div>
                    </div>

                    <!-- Picked Up -->
                    <div class="flex items-center justify-between">
                        <span class="text-white/60 text-sm">{{ $t('Picked Up') }}</span>
                        <div class="flex rounded-lg overflow-hidden border border-white/10">
                            <button
                                v-for="opt in pickedUpOptions"
                                :key="String(opt.value)"
                                class="px-3 py-1 text-xs font-medium transition-colors"
                                :class="editDraft.picked_up === opt.value
                                    ? 'bg-emerald-500/20 text-emerald-400'
                                    : 'bg-white/5 text-white/40 hover:bg-white/10'"
                                @click="editDraft.picked_up = opt.value"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Materials multi-select -->
                    <div>
                        <span class="text-white/60 text-sm block mb-1">{{ $t('Materials') }}</span>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="mat in availableMaterials"
                                :key="mat.id"
                                class="px-2 py-0.5 rounded text-xs transition-colors"
                                :class="editDraft.materials?.includes(mat.id)
                                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                    : 'bg-white/5 text-white/40 border border-white/10 hover:bg-white/10'"
                                @click="toggleMaterial(mat.id)"
                            >
                                {{ translateTag(mat.key, 'material') }}
                            </button>
                        </div>
                    </div>

                    <!-- Brands multi-select -->
                    <div>
                        <span class="text-white/60 text-sm block mb-1">{{ $t('Brands') }}</span>
                        <div v-if="editDraft.brands?.length" class="space-y-1 mb-2">
                            <div
                                v-for="(brand, bi) in editDraft.brands"
                                :key="bi"
                                class="flex items-center gap-2"
                            >
                                <span class="text-white/60 text-xs flex-1">{{ resolveBrandName(brand.id) }}</span>
                                <div class="flex items-center gap-1">
                                    <button
                                        class="w-5 h-5 rounded bg-white/10 text-white/60 hover:bg-white/20 text-xs"
                                        :disabled="brand.quantity <= 1"
                                        @click="brand.quantity = Math.max(1, brand.quantity - 1)"
                                    >-</button>
                                    <span class="text-white text-xs w-4 text-center">{{ brand.quantity }}</span>
                                    <button
                                        class="w-5 h-5 rounded bg-white/10 text-white/60 hover:bg-white/20 text-xs"
                                        :disabled="brand.quantity >= 10"
                                        @click="brand.quantity = Math.min(10, brand.quantity + 1)"
                                    >+</button>
                                </div>
                                <button
                                    class="text-red-400/60 hover:text-red-400 text-xs"
                                    @click="editDraft.brands.splice(bi, 1)"
                                >&times;</button>
                            </div>
                        </div>
                        <select
                            class="bg-white/5 border border-white/10 rounded text-white/60 text-xs px-2 py-1 focus:outline-none focus:border-emerald-500/50"
                            @change="addBrand($event)"
                        >
                            <option value="" class="bg-slate-900">{{ $t('Add brand...') }}</option>
                            <option
                                v-for="brand in availableBrands"
                                :key="brand.id"
                                :value="brand.id"
                                :disabled="editDraft.brands?.some(b => b.id === brand.id)"
                                class="bg-slate-900"
                            >
                                {{ translateTag(brand.key, 'brands') }}
                            </option>
                        </select>
                    </div>

                    <!-- Save / Cancel -->
                    <div class="flex gap-2 pt-1">
                        <button
                            class="px-3 py-1.5 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 rounded-lg text-xs hover:bg-emerald-500/30 transition"
                            @click="saveEdit(index)"
                        >
                            {{ $t('Save') }}
                        </button>
                        <button
                            class="px-3 py-1.5 bg-white/5 border border-white/10 text-white/50 rounded-lg text-xs hover:bg-white/10 transition"
                            @click="editingIndex = null"
                        >
                            {{ $t('Cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useQuickTagsStore } from '@stores/quickTags.js';
import { useTagsStore } from '@stores/tags/index.js';

const { t, t: $t } = useI18n();
const quickTagsStore = useQuickTagsStore();
const tagsStore = useTagsStore();

const editingIndex = ref(null);
const editDraft = ref({});
const confirmDeleteIndex = ref(null);
const catalogLoading = ref(false);

const pickedUpOptions = computed(() => [
    { value: true, label: $t('Yes') },
    { value: false, label: $t('No') },
    { value: null, label: $t('Inherit') },
]);

const availableMaterials = computed(() => tagsStore.materials || []);
const availableBrands = computed(() => tagsStore.brands || []);

// --- Name resolution helpers ---

const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const translateTag = (key, i18nPrefix) => {
    const path = `litter.${i18nPrefix}.${key}`;
    const translated = t(path);
    return translated !== path ? translated : formatKey(key);
};

/**
 * Build a lookup: clo_id → { objectKey, categoryKey }
 */
const cloLookup = computed(() => {
    const map = {};
    for (const co of tagsStore.categoryObjects) {
        const obj = tagsStore.objects.find((o) => o.id === co.litter_object_id);
        const cat = tagsStore.categories.find((c) => c.id === co.category_id);
        if (obj && cat) {
            map[co.id] = { objectKey: obj.key, categoryKey: cat.key };
        }
    }
    return map;
});

const typeLookup = computed(() => {
    const map = {};
    for (const t of tagsStore.types) {
        map[t.id] = t;
    }
    return map;
});

const materialLookup = computed(() => {
    const map = {};
    for (const m of tagsStore.materials) {
        map[m.id] = m;
    }
    return map;
});

const brandLookup = computed(() => {
    const map = {};
    for (const b of tagsStore.brands) {
        map[b.id] = b;
    }
    return map;
});

const resolveTagName = (tag) => {
    const clo = cloLookup.value[tag.clo_id];
    if (!clo) return `CLO #${tag.clo_id}`;
    return translateTag(clo.objectKey, clo.categoryKey);
};

const resolveCategoryName = (tag) => {
    const clo = cloLookup.value[tag.clo_id];
    if (!clo) return '';
    return translateTag(clo.categoryKey, 'categories');
};

const resolveTypeName = (tag) => {
    if (!tag.type_id) return null;
    const type = typeLookup.value[tag.type_id];
    return type ? formatKey(type.key) : null;
};

const resolveMaterialNames = (materialIds) => {
    return materialIds
        .map((id) => {
            const mat = materialLookup.value[id];
            return mat ? translateTag(mat.key, 'material') : `#${id}`;
        })
        .join(', ');
};

const resolveBrandName = (brandId) => {
    const brand = brandLookup.value[brandId];
    return brand ? translateTag(brand.key, 'brands') : `#${brandId}`;
};

const resolveBrandNames = (brands) => {
    return brands.map((b) => resolveBrandName(b.id)).join(', ');
};

// --- Edit actions ---

const toggleMaterial = (materialId) => {
    const arr = editDraft.value.materials || [];
    const idx = arr.indexOf(materialId);
    if (idx >= 0) {
        arr.splice(idx, 1);
    } else {
        arr.push(materialId);
    }
    editDraft.value.materials = [...arr];
};

const addBrand = (event) => {
    const brandId = parseInt(event.target.value);
    if (!brandId) return;
    event.target.value = '';
    if (!editDraft.value.brands) editDraft.value.brands = [];
    if (editDraft.value.brands.some((b) => b.id === brandId)) return;
    editDraft.value.brands.push({ id: brandId, quantity: 1 });
};

const saveEdit = (index) => {
    quickTagsStore.updateTag(index, {
        quantity: editDraft.value.quantity,
        picked_up: editDraft.value.picked_up,
        materials: editDraft.value.materials || [],
        brands: editDraft.value.brands || [],
    });
    editingIndex.value = null;
};

// --- Lifecycle ---

onMounted(async () => {
    // Load catalog if not already loaded
    if (!tagsStore.categoryObjects.length) {
        catalogLoading.value = true;
        await tagsStore.GET_ALL_TAGS();
        catalogLoading.value = false;
    }

    await quickTagsStore.FETCH_QUICK_TAGS();
});
</script>
