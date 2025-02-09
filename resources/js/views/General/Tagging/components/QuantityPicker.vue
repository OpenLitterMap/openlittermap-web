<template>
    <div class="w-[10em] mb-4">
        <Combobox as="div" v-model="internalSelected" @update:modelValue="onChange">
            <div class="relative">
                <!-- Text input (shows current value or search text) -->
                <ComboboxInput
                    :displayValue="displayValue"
                    @input="onInput"
                    :placeholder="placeholder"
                    class="rounded-md border border-gray-300 bg-white py-2 px-3 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                />
                <!-- Dropdown button -->
                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </ComboboxButton>
            </div>

            <!-- Options list -->
            <TransitionRoot
                as="template"
                enter="transition duration-150 ease-out"
                enter-from="transform scale-95 opacity-0"
                enter-to="transform scale-100 opacity-100"
                leave="transition duration-100 ease-in"
                leave-from="transform scale-100 opacity-100"
                leave-to="transform scale-95 opacity-0"
            >
                <ComboboxOptions
                    class="absolute z-10 mt-1 max-h-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                >
                    <ComboboxOption
                        v-for="n in filteredNumbers"
                        :key="n"
                        :value="n"
                        class="relative cursor-pointer select-none py-2 pl-3 pr-9"
                        :class="({ active }) => (active ? 'bg-indigo-600 text-white' : 'text-gray-900')"
                        v-slot="{ active, selected }"
                    >
                        <div class="flex items-center">
                            <span class="flex-1">{{ n }}</span>
                            <span v-if="selected" class="absolute inset-y-0 right-0 flex items-center pr-4">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </span>
                        </div>
                    </ComboboxOption>
                </ComboboxOptions>
            </TransitionRoot>
        </Combobox>
    </div>
</template>

<script setup>
import { ref, computed, watch, defineProps, defineEmits } from 'vue';
import {
    Combobox,
    ComboboxInput,
    ComboboxButton,
    ComboboxOptions,
    ComboboxOption,
    TransitionRoot,
} from '@headlessui/vue';

const props = defineProps({
    modelValue: {
        type: Number,
        default: 1,
    },
});

const emit = defineEmits(['update:modelValue']);

// Generate an array of numbers from 1 to 100.
const numbers = Array.from({ length: 100 }, (_, i) => i + 1);

// Optional: If you want to support filtering via the input,
// store the search query.
const searchQuery = ref('');

// Our internal model (keeps the current number).
const internalSelected = ref(props.modelValue);

// Sync internal value with external model.
watch(
    () => props.modelValue,
    (newVal) => {
        internalSelected.value = newVal;
    }
);

// Display function: shows the selected number as a string.
function displayValue(selected) {
    return selected ? selected.toString() : searchQuery.value;
}

// When user types, update the search query.
// (This is optional if you want filtering functionality.)
function onInput(event) {
    searchQuery.value = event.target.value;
}

// When a new value is selected, emit the update.
function onChange(newSelection) {
    emit('update:modelValue', newSelection);
    searchQuery.value = newSelection.toString();
}

// Optionally, filter the numbers based on the search query.
// If you don't need filtering, simply return the full numbers array.
const filteredNumbers = computed(() => {
    if (!searchQuery.value) {
        return numbers;
    }
    return numbers.filter((n) => n.toString().includes(searchQuery.value));
});
</script>

<style scoped>
/* Optionally add custom styles if needed */
</style>
