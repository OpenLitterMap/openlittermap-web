<template>
    <div
        :class="[
            'rounded-xl p-3 transition-all duration-200',
            tag.object && !tag.cloId
                ? 'bg-amber-500/10 border border-amber-500/30 border-l-4 border-l-amber-400'
                : 'bg-gradient-to-r from-white/[0.08] to-white/[0.03] border border-white/15 hover:border-emerald-500/30',
        ]"
    >
        <!-- Row 1: Tag name, quantity, actions -->
        <div class="flex items-center gap-2">
            <!-- Custom tag badge -->
            <span
                v-if="tag.custom"
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex-shrink-0"
            >
                Custom
            </span>

            <!-- Tag name -->
            <span class="text-white font-semibold truncate min-w-0" :title="tagDisplay">
                {{ tagDisplay }}
            </span>

            <!-- Type badge (when type is selected) -->
            <span
                v-if="selectedTypeName"
                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium bg-emerald-500/30 text-white border border-emerald-400/50 flex-shrink-0"
            >
                {{ selectedTypeName }}
            </span>

            <!-- Spacer -->
            <div class="flex-1 min-w-0"></div>

            <!-- Quantity controls -->
            <div class="flex items-center gap-0.5 flex-shrink-0 bg-white/5 rounded-lg p-0.5">
                <button
                    @click="decreaseQuantity"
                    :disabled="tag.quantity <= 1"
                    aria-label="Decrease quantity"
                    class="w-7 h-7 rounded-md hover:bg-white/10 disabled:opacity-20 disabled:cursor-not-allowed transition-colors flex items-center justify-center"
                >
                    <span class="text-white/70 text-sm font-bold">-</span>
                </button>

                <input
                    type="number"
                    v-model.number="localQuantity"
                    @blur="commitQuantity"
                    @keydown.enter="commitQuantity"
                    inputmode="numeric"
                    min="1"
                    max="100"
                    aria-label="Quantity"
                    class="w-9 h-7 text-center bg-transparent text-white text-sm font-bold tabular-nums focus:outline-none"
                />

                <button
                    @click="increaseQuantity"
                    :disabled="tag.quantity >= 100"
                    aria-label="Increase quantity"
                    class="w-7 h-7 rounded-md hover:bg-white/10 disabled:opacity-20 disabled:cursor-not-allowed transition-colors flex items-center justify-center"
                >
                    <span class="text-white/70 text-sm font-bold">+</span>
                </button>
            </div>

            <!-- Picked up toggle (tri-state button) -->
            <button
                @click="cyclePickedUp"
                aria-label="Picked up status"
                :class="[
                    'px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-all duration-150 flex-shrink-0 flex items-center gap-1',
                    tag.pickedUp === true
                        ? 'bg-emerald-500/25 text-emerald-300 ring-1 ring-emerald-500/40'
                        : tag.pickedUp === false
                          ? 'bg-orange-500/20 text-orange-300 ring-1 ring-orange-500/30'
                          : 'bg-white/5 text-white/40 ring-1 ring-white/10 hover:ring-white/20',
                ]"
            >
                <span v-if="tag.pickedUp === true">Picked up</span>
                <span v-else-if="tag.pickedUp === false">Still there</span>
                <span v-else>Unknown</span>
            </button>

            <!-- Add details button -->
            <button
                @click="showDetails ? (showDetails = false) : openDetails()"
                class="px-2 py-1 text-[11px] font-medium text-emerald-400/70 hover:text-emerald-300 hover:bg-emerald-500/10 rounded-lg transition-colors flex-shrink-0 border border-transparent hover:border-emerald-500/20"
            >
                {{ showDetails ? 'Hide tagging menu' : '+ Add more tags' }}
            </button>

            <!-- Remove button -->
            <button
                @click="$emit('remove')"
                aria-label="Remove tag"
                title="Remove tag"
                class="w-7 h-7 flex items-center justify-center text-white/20 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors flex-shrink-0"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- XP + Type pills (single row) -->
        <div class="flex items-center gap-2 mt-2 pt-2 border-t border-white/5 text-[11px] min-w-0">
            <span class="text-emerald-400 font-bold bg-emerald-500/10 px-1.5 py-0.5 rounded flex-shrink-0">+{{ tagXp }} XP</span>
            <span v-if="!props.availableTypes.length" class="text-white/30 truncate">{{ tagBreakdown }}</span>
            <div class="flex-1 min-w-0"></div>
            <div v-if="props.availableTypes.length > 0" class="flex items-center gap-1 min-w-0 overflow-x-auto scrollbar-hide">
                <button
                    v-for="t in props.availableTypes"
                    :key="t.id"
                    @click="setType(tag.typeId === t.id ? null : t.id)"
                    :class="[
                        'px-2 py-0.5 rounded-lg text-[11px] font-medium transition-colors border whitespace-nowrap flex-shrink-0',
                        tag.typeId === t.id
                            ? 'bg-emerald-500/30 text-white border-emerald-400/50 ring-1 ring-emerald-400/30'
                            : 'bg-white/5 text-white/40 border-white/10 hover:bg-white/10',
                    ]"
                >
                    {{ formatKey(t.key) }}
                </button>
            </div>
        </div>

        <!-- Suggested materials (quick-add chips) -->
        <div v-if="showDetails && unusedSuggestedMaterials.length > 0" class="flex items-center gap-1 mt-3">
            <div class="flex-1 min-w-0"></div>
            <span class="text-[10px] text-white/30 mr-1 flex-shrink-0">{{ unusedSuggestedMaterials.length > 1 ? 'Materials:' : 'Material:' }}</span>
            <button
                v-for="m in unusedSuggestedMaterials"
                :key="'sm-' + m.id"
                @click="quickAddMaterial(m)"
                class="px-2 py-0.5 rounded-lg text-[11px] font-medium transition-colors border bg-cyan-500/10 text-cyan-400/70 border-cyan-500/20 hover:bg-cyan-500/20 hover:text-cyan-300"
            >
                + {{ formatKey(m.key) }}
            </button>
        </div>

        <!-- Line 2: Detail badges (when panel closed and has details) -->
        <div
            v-if="!showDetails && hasDetails"
            class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 pt-2 border-t border-white/10"
        >
            <div v-if="tag.brands?.length" class="flex items-center gap-1">
                <span class="text-white/40 text-xs">Brands:</span>
                <span
                    v-for="brand in tag.brands"
                    :key="'b-' + brand.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-500/20 text-purple-300 rounded text-xs border border-purple-500/20"
                >
                    {{ formatKey(brand.key) }}
                    <button @click="removeBrand(brand)" aria-label="Remove brand" class="hover:text-red-300 transition-colors">×</button>
                </span>
            </div>
            <div v-if="tag.materials?.length" class="flex items-center gap-1">
                <span class="text-white/40 text-xs">Materials:</span>
                <span
                    v-for="material in tag.materials"
                    :key="'m-' + material.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-cyan-500/20 text-cyan-300 rounded text-xs border border-cyan-500/20"
                >
                    {{ formatKey(material.key) }}
                    <button @click="removeMaterial(material)" aria-label="Remove material" class="hover:text-red-300 transition-colors">×</button>
                </span>
            </div>
            <div v-if="tag.objects?.length" class="flex items-center gap-1">
                <span class="text-white/40 text-xs">Objects:</span>
                <span
                    v-for="obj in tag.objects"
                    :key="'o-' + obj.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-300 rounded text-xs border border-blue-500/20"
                >
                    {{ formatKey(obj.key) }}
                    <button @click="removeObject(obj)" aria-label="Remove object" class="hover:text-red-300 transition-colors">×</button>
                </span>
            </div>
            <div v-if="tag.customTags?.length" class="flex items-center gap-1">
                <span class="text-white/40 text-xs">Custom:</span>
                <span
                    v-for="custom in tag.customTags"
                    :key="'c-' + custom"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-500/20 text-amber-300 rounded text-xs border border-amber-500/20"
                >
                    {{ custom }}
                    <button @click="removeCustom(custom)" aria-label="Remove custom tag" class="hover:text-red-300 transition-colors">×</button>
                </span>
            </div>
        </div>

        <!-- Detail inputs section (collapsible) -->
        <div v-if="showDetails" class="space-y-2 pt-3 mt-3 border-t border-white/10">
            <!-- Hint text -->
            <p v-if="!hasDetails" class="text-xs text-white/40 mb-2">
                Add optional details: brand, material, related objects, or custom tags.
            </p>

            <!-- Current details with remove capability -->
            <div v-if="hasDetails" class="flex flex-wrap gap-1 mb-2">
                <span
                    v-for="brand in tag.brands"
                    :key="'b-' + brand.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-purple-500/20 text-purple-300 rounded text-xs border border-purple-500/20"
                >
                    {{ formatKey(brand.key) }}
                    <button @click="removeBrand(brand)" aria-label="Remove brand" class="hover:text-red-300 transition-colors">×</button>
                </span>
                <span
                    v-for="material in tag.materials"
                    :key="'m-' + material.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-cyan-500/20 text-cyan-300 rounded text-xs border border-cyan-500/20"
                >
                    {{ formatKey(material.key) }}
                    <button @click="removeMaterial(material)" aria-label="Remove material" class="hover:text-red-300 transition-colors">×</button>
                </span>
                <span
                    v-for="obj in tag.objects"
                    :key="'o-' + obj.id"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-300 rounded text-xs border border-blue-500/20"
                >
                    {{ formatKey(obj.key) }}
                    <button @click="removeObject(obj)" aria-label="Remove object" class="hover:text-red-300 transition-colors">×</button>
                </span>
                <span
                    v-for="custom in tag.customTags"
                    :key="'c-' + custom"
                    class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-500/20 text-amber-300 rounded text-xs border border-amber-500/20"
                >
                    {{ custom }}
                    <button @click="removeCustom(custom)" aria-label="Remove custom tag" class="hover:text-red-300 transition-colors">×</button>
                </span>
            </div>

            <!-- Add Brands -->
            <div class="relative">
                <UnifiedTagSearch
                    ref="brandSearchRef"
                    v-model="brandQuery"
                    :tags="brandsForSelect"
                    placeholder="Add brand..."
                    @tag-selected="addBrand"
                    class="detail-select"
                />
            </div>

            <!-- Add Materials -->
            <div class="relative">
                <UnifiedTagSearch
                    ref="materialSearchRef"
                    v-model="materialQuery"
                    :tags="materialsForSelect"
                    placeholder="Add material..."
                    @tag-selected="addMaterial"
                    class="detail-select"
                />
            </div>

            <!-- Add More Objects (only for non-brand/material tags) -->
            <div v-if="!tag.type || tag.type === 'object'" class="relative">
                <UnifiedTagSearch
                    ref="objectSearchRef"
                    v-model="objectQuery"
                    :tags="objectsForSelect"
                    placeholder="Add more objects..."
                    @tag-selected="addObject"
                    @custom-tag="addCustomFromObject"
                    class="detail-select"
                />
            </div>

            <!-- Add Custom Tags -->
            <input
                v-model="customTagInput"
                @keydown.enter="addCustomTag"
                placeholder="Custom tag (press Enter)"
                class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm placeholder-white/30 focus:outline-none focus:border-emerald-500/50"
            />

        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import UnifiedTagSearch from './UnifiedTagSearch.vue';
import { calculateTagXp, getTagBreakdownParts } from '../useXpCalculator.js';

const props = defineProps({
    tag: {
        type: Object,
        required: true,
    },
    brands: {
        type: Array,
        default: () => [],
    },
    materials: {
        type: Array,
        default: () => [],
    },
    searchableTags: {
        type: Array,
        default: () => [],
    },
    availableTypes: {
        type: Array,
        default: () => [],
    },
    suggestedMaterials: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update-quantity', 'set-picked-up', 'set-type', 'add-detail', 'remove-detail', 'remove']);

const showDetails = ref(false);
const brandQuery = ref('');
const materialQuery = ref('');
const objectQuery = ref('');
const customTagInput = ref('');

const brandSearchRef = ref(null);
const materialSearchRef = ref(null);
const objectSearchRef = ref(null);

// Open details panel and focus brand input
const openDetails = () => {
    showDetails.value = true;
    // Double nextTick ensures Headless UI Combobox is fully mounted
    nextTick(() => nextTick(() => {
        brandSearchRef.value?.$el?.querySelector('input')?.focus();
    }));
};

// Local quantity with sync to prop
const localQuantity = ref(props.tag.quantity);
watch(
    () => props.tag.quantity,
    (val) => {
        localQuantity.value = val;
    }
);

const commitQuantity = () => {
    let val = localQuantity.value;
    if (val === null || val === '' || isNaN(val)) {
        val = 1;
    }
    val = Math.max(1, Math.min(100, Math.floor(val)));
    localQuantity.value = val;
    emit('update-quantity', val);
};

// Picked up tri-state cycle: null → true → false → null
const cyclePickedUp = () => {
    let next;
    if (props.tag.pickedUp === null || props.tag.pickedUp === undefined) {
        next = true;
    } else if (props.tag.pickedUp === true) {
        next = false;
    } else {
        next = null;
    }
    emit('set-picked-up', next);
};

// Type pill handler
const setType = (value) => {
    emit('set-type', value ?? null);
};

// Convert brands array to format expected by UnifiedTagSearch
const brandsForSelect = computed(() =>
    props.brands.map((b) => ({
        id: `brand-${b.id}`,
        key: b.key,
        type: 'brand',
        raw: b,
    }))
);

// Convert materials array to format expected by UnifiedTagSearch
const materialsForSelect = computed(() =>
    props.materials.map((m) => ({
        id: `mat-${m.id}`,
        key: m.key,
        type: 'material',
        raw: m,
    }))
);

// Filter objects from searchable tags (deduplicate — detail panel doesn't need per-category entries)
const objectsForSelect = computed(() => {
    const seen = new Set();
    return props.searchableTags.filter((t) => {
        if (t.type !== 'object') return false;
        const objId = t.raw?.id;
        if (seen.has(objId)) return false;
        seen.add(objId);
        return true;
    });
});

const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const tagDisplay = computed(() => {
    if (props.tag.custom) {
        return props.tag.key;
    } else if (props.tag.type === 'brand-only') {
        return `Brand: ${formatKey(props.tag.brand.key)}`;
    } else if (props.tag.type === 'material-only') {
        return `Material: ${formatKey(props.tag.material.key)}`;
    } else if (props.tag.object) {
        const objName = formatKey(props.tag.object.key);
        const catName = props.tag.categoryKey ? formatKey(props.tag.categoryKey) : null;
        return catName ? `${objName} \u00B7 ${catName}` : objName;
    }
    return 'Unknown tag';
});

const selectedTypeName = computed(() => {
    if (!props.tag.typeId) return null;
    const t = props.availableTypes.find((t) => t.id === props.tag.typeId);
    return t ? formatKey(t.key) : null;
});

const tagXp = computed(() => calculateTagXp(props.tag));
const tagBreakdown = computed(() => getTagBreakdownParts(props.tag).join(' \u00B7 '));

// Suggested materials not yet added to this tag
const unusedSuggestedMaterials = computed(() => {
    const addedKeys = new Set((props.tag.materials || []).map((m) => m.key));
    return props.suggestedMaterials.filter((m) => !addedKeys.has(m.key));
});

const quickAddMaterial = (material) => {
    emit('add-detail', { type: 'material', value: material });
};

const hasDetails = computed(() => {
    return (
        props.tag.brands?.length > 0 ||
        props.tag.materials?.length > 0 ||
        props.tag.objects?.length > 0 ||
        props.tag.customTags?.length > 0
    );
});

const increaseQuantity = () => {
    if (localQuantity.value < 100) {
        localQuantity.value++;
        emit('update-quantity', localQuantity.value);
    }
};

const decreaseQuantity = () => {
    if (localQuantity.value > 1) {
        localQuantity.value--;
        emit('update-quantity', localQuantity.value);
    }
};

const addBrand = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'brand',
            value: selected.raw,
        });
        brandQuery.value = '';
        // Move focus to materials input
        nextTick(() => {
            materialSearchRef.value?.$el?.querySelector('input')?.focus();
        });
    }
};

const addMaterial = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'material',
            value: selected.raw,
        });
        materialQuery.value = '';
        // Move focus to objects input
        nextTick(() => {
            objectSearchRef.value?.$el?.querySelector('input')?.focus();
        });
    }
};

const addObject = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'object',
            value: selected.raw,
        });
        objectQuery.value = '';
    }
};

const addCustomFromObject = (customTag) => {
    const value = customTag?.key?.trim().replace(/\s+/g, ' ').slice(0, 64);
    if (value) {
        emit('add-detail', {
            type: 'custom',
            value: value,
        });
        objectQuery.value = '';
    }
};

const addCustomTag = () => {
    const value = customTagInput.value.trim().replace(/\s+/g, ' ').slice(0, 64);
    if (value) {
        emit('add-detail', {
            type: 'custom',
            value: value,
        });
        customTagInput.value = '';
    }
};

// Remove functions
const removeBrand = (brand) => {
    emit('remove-detail', { type: 'brand', value: brand });
};

const removeMaterial = (material) => {
    emit('remove-detail', { type: 'material', value: material });
};

const removeObject = (obj) => {
    emit('remove-detail', { type: 'object', value: obj });
};

const removeCustom = (custom) => {
    emit('remove-detail', { type: 'custom', value: custom });
};
</script>

<style scoped>
:deep(.detail-select input) {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
