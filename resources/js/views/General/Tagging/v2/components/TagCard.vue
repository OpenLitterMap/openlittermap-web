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

        <!-- Additional details (collapsed by default) -->
        <div v-if="showDetails || hasDetails">
            <!-- Show existing details -->
            <div v-if="hasDetails" class="mb-2 space-y-1">
                <div v-if="tag.brands?.length" class="text-sm">
                    <span class="text-gray-400">Brands:</span>
                    <span class="ml-2 text-gray-300">{{ tag.brands.map((b) => b.key).join(', ') }}</span>
                </div>
                <div v-if="tag.materials?.length" class="text-sm">
                    <span class="text-gray-400">Materials:</span>
                    <span class="ml-2 text-gray-300">{{ tag.materials.map((m) => m.key).join(', ') }}</span>
                </div>
                <div v-if="tag.customTags?.length" class="text-sm">
                    <span class="text-gray-400">Custom:</span>
                    <span class="ml-2 text-gray-300">{{ tag.customTags.join(', ') }}</span>
                </div>
            </div>

            <!-- Toggle to show/hide detail inputs -->
            <button
                @click="showDetails = !showDetails"
                class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
            >
                {{ showDetails ? 'Hide details' : 'Add details' }} →
            </button>

            <!-- Detail inputs (when expanded) -->
            <div v-if="showDetails" class="mt-3 space-y-2">
                <input
                    v-model="newDetail"
                    @keydown.enter="addDetail"
                    placeholder="Add brand, material, or custom detail..."
                    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white text-sm placeholder-gray-400 focus:outline-none focus:border-blue-500"
                />
                <div class="text-xs text-gray-400">Press Enter to add detail</div>
            </div>
        </div>

        <!-- Show "Add details" button if no details exist yet -->
        <button v-else @click="showDetails = true" class="text-sm text-gray-400 hover:text-gray-300 transition-colors">
            + Add details
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    tag: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['update-quantity', 'toggle-picked-up', 'add-detail', 'remove']);

const showDetails = ref(false);
const newDetail = ref('');

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
    return props.tag.brands?.length > 0 || props.tag.materials?.length > 0 || props.tag.customTags?.length > 0;
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

const addDetail = () => {
    if (newDetail.value.trim()) {
        // For now, treat all as custom details
        // In a real implementation, we'd detect if it's a brand or material
        emit('add-detail', {
            type: 'custom',
            value: newDetail.value.trim(),
        });
        newDetail.value = '';
    }
};
</script>
