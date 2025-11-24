<template>
    <div class="bg-gray-700 rounded-lg p-4">
        <!-- Main tag info -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex-1 flex items-center gap-3">
                <!-- Tag name -->
                <span class="text-white font-medium">
                    {{ tagDisplay }}
                </span>

                <!-- Quantity controls -->
                <div class="flex items-center gap-1">
                    <button
                        @click="decreaseQuantity"
                        :disabled="tag.quantity <= 1"
                        class="w-7 h-7 bg-gray-600 rounded hover:bg-gray-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                        <span class="text-white text-sm">−</span>
                    </button>

                    <input
                        type="number"
                        :value="tag.quantity"
                        @input="updateQuantity($event.target.value)"
                        min="1"
                        max="100"
                        class="w-12 text-center bg-gray-800 border border-gray-600 rounded text-white text-sm focus:outline-none focus:border-blue-500"
                    />

                    <button
                        @click="increaseQuantity"
                        :disabled="tag.quantity >= 100"
                        class="w-7 h-7 bg-gray-600 rounded hover:bg-gray-500 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    >
                        <span class="text-white text-sm">+</span>
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <!-- Picked up toggle -->
                <button
                    @click="$emit('toggle-picked-up')"
                    :class="[
                        'px-3 py-1 rounded text-xs font-medium transition-colors',
                        tag.pickedUp
                            ? 'bg-green-600 text-white hover:bg-green-700'
                            : 'bg-gray-600 text-gray-300 hover:bg-gray-500',
                    ]"
                >
                    {{ tag.pickedUp ? 'Picked up' : 'Not picked up' }}
                </button>

                <!-- Remove button -->
                <button @click="$emit('remove')" class="p-1 text-gray-400 hover:text-red-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Show existing details if any -->
        <div v-if="hasDetails" class="mb-2 space-y-1 text-sm">
            <div v-if="tag.brands?.length" class="flex flex-wrap gap-1">
                <span class="text-gray-400">Brands:</span>
                <span
                    v-for="brand in tag.brands"
                    :key="brand.id"
                    class="px-2 py-0.5 bg-gray-600 text-gray-200 rounded-md"
                >
                    {{ brand.key }}
                </span>
            </div>
            <div v-if="tag.materials?.length" class="flex flex-wrap gap-1">
                <span class="text-gray-400">Materials:</span>
                <span
                    v-for="material in tag.materials"
                    :key="material.id"
                    class="px-2 py-0.5 bg-gray-600 text-gray-200 rounded-md"
                >
                    {{ material.key }}
                </span>
            </div>
            <div v-if="tag.objects?.length" class="flex flex-wrap gap-1">
                <span class="text-gray-400">Also contains:</span>
                <span v-for="obj in tag.objects" :key="obj.id" class="px-2 py-0.5 bg-gray-600 text-gray-200 rounded-md">
                    {{ obj.key }}
                </span>
            </div>
            <div v-if="tag.customTags?.length" class="flex flex-wrap gap-1">
                <span class="text-gray-400">Custom:</span>
                <span
                    v-for="custom in tag.customTags"
                    :key="custom"
                    class="px-2 py-0.5 bg-gray-600 text-gray-200 rounded-md"
                >
                    {{ custom }}
                </span>
            </div>
        </div>

        <!-- Toggle to show/hide detail inputs -->
        <button
            v-if="!showDetails"
            @click="showDetails = true"
            class="text-sm text-blue-400 hover:text-blue-300 transition-colors mb-2"
        >
            {{ hasDetails ? 'Add more details' : '+ Add details' }} →
        </button>

        <!-- Detail inputs section (collapsible) -->
        <div v-if="showDetails" class="space-y-2 pt-2 border-t border-gray-600">
            <!-- Add Brands -->
            <div class="relative">
                <UnifiedTagSearch
                    v-model="selectedBrand"
                    :tags="brandsForSelect"
                    placeholder="Add Brands"
                    @tag-selected="addBrand"
                    class="detail-select"
                />
            </div>

            <!-- Add Materials -->
            <div class="relative">
                <UnifiedTagSearch
                    v-model="selectedMaterial"
                    :tags="materialsForSelect"
                    placeholder="Add Materials"
                    @tag-selected="addMaterial"
                    class="detail-select"
                />
            </div>

            <!-- Add More Objects (only for non-brand/material tags) -->
            <div v-if="!tag.type || tag.type === 'object'" class="relative">
                <UnifiedTagSearch
                    v-model="selectedObject"
                    :tags="objectsForSelect"
                    placeholder="Add More Objects"
                    @tag-selected="addObject"
                    class="detail-select"
                />
            </div>

            <!-- Add Custom Tags -->
            <input
                v-model="customTagInput"
                @keydown.enter="addCustomTag"
                placeholder="Add Custom Tags (press Enter)"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-sm placeholder-gray-400 focus:outline-none focus:border-blue-500"
            />

            <button @click="showDetails = false" class="text-sm text-gray-400 hover:text-gray-300 transition-colors">
                Hide details
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import UnifiedTagSearch from './UnifiedTagSearch.vue';

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
});

const emit = defineEmits(['update-quantity', 'toggle-picked-up', 'add-detail', 'remove']);

const showDetails = ref(false);
const selectedBrand = ref(null);
const selectedMaterial = ref(null);
const selectedObject = ref(null);
const customTagInput = ref('');

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

// Filter objects from searchable tags
const objectsForSelect = computed(() => props.searchableTags.filter((t) => t.type === 'object'));

const tagDisplay = computed(() => {
    if (props.tag.custom) {
        return props.tag.key;
    } else if (props.tag.type === 'brand-only') {
        return `Brand: ${props.tag.brand.key}`;
    } else if (props.tag.type === 'material-only') {
        return `Material: ${props.tag.material.key}`;
    } else if (props.tag.object) {
        return props.tag.object.key;
    }
    return 'Unknown tag';
});

const hasDetails = computed(() => {
    return (
        props.tag.brands?.length > 0 ||
        props.tag.materials?.length > 0 ||
        props.tag.objects?.length > 0 ||
        props.tag.customTags?.length > 0
    );
});

const updateQuantity = (value) => {
    const num = parseInt(value);
    if (!isNaN(num)) {
        emit('update-quantity', Math.max(1, Math.min(100, num)));
    }
};

const increaseQuantity = () => {
    if (props.tag.quantity < 100) {
        emit('update-quantity', props.tag.quantity + 1);
    }
};

const decreaseQuantity = () => {
    if (props.tag.quantity > 1) {
        emit('update-quantity', props.tag.quantity - 1);
    }
};

const addBrand = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'brand',
            value: selected.raw,
        });
        selectedBrand.value = null;
    }
};

const addMaterial = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'material',
            value: selected.raw,
        });
        selectedMaterial.value = null;
    }
};

const addObject = (selected) => {
    if (selected?.raw) {
        emit('add-detail', {
            type: 'object',
            value: selected.raw,
        });
        selectedObject.value = null;
    }
};

const addCustomTag = () => {
    if (customTagInput.value.trim()) {
        emit('add-detail', {
            type: 'custom',
            value: customTagInput.value.trim(),
        });
        customTagInput.value = '';
    }
};
</script>

<style scoped>
/* Smaller input size for detail selects */
:deep(.detail-select input) {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}
</style>
