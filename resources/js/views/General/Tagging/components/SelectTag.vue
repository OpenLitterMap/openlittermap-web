<template>
    <div class="w-[20em] mb-4">
        <Combobox as="div" v-model="internalSelected" @update:modelValue="onChange" by="id">
            <div class="relative">
                <!-- The users text input -->
                <ComboboxInput
                    :displayValue="displayValue"
                    @input="onInput"
                    :placeholder="`${placeholder}`"
                    class="capitalize rounded-md border border-gray-300 bg-white py-2 px-3 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                />

                <!-- Clear selected input -->
                <!-- Material.id is a string -->
                <button
                    v-if="modelValue.id !== 0"
                    @click="clearSelected"
                    class="absolute inset-y-0 right-8 flex items-center pr-2"
                    type="button"
                >
                    <svg
                        class="h-4 w-4 text-gray-400 cursor-pointer"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- The dropdown toggle button -->
                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                    <!-- Dropdown icon -->
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </ComboboxButton>
            </div>

            <transition
                enter="transition duration-150 ease-out"
                enter-from="transform scale-95 opacity-0"
                enter-to="transform scale-100 opacity-100"
                leave="transition duration-100 ease-in"
                leave-from="transform scale-100 opacity-100"
                leave-to="transform scale-95 opacity-0"
            >
                <!-- If we have filtered results, show the dropdown -->
                <ComboboxOptions
                    v-if="filteredOptions?.length"
                    class="absolute z-10 mt-1 w-[20em] max-h-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                >
                    <ComboboxOption
                        v-for="option in filteredOptions"
                        :key="option.id"
                        :value="option"
                        :class="
                            ({ active }) => [
                                'relative cursor-pointer select-none py-2 pl-3 pr-9',
                                active ? 'bg-indigo-600 text-white' : 'text-gray-900',
                            ]
                        "
                        v-slot="{ active, selected }"
                    >
                        <li
                            :class="{
                                'bg-blue-500 text-white': active,
                                'bg-white text-black': !active,
                            }"
                            class="flex py-2 px-4"
                        >
                            <span class="flex-1 capitalize">{{ option.key }}</span>
                            <CheckIcon v-show="selected" class="h-4 w-4" />
                        </li>
                    </ComboboxOption>
                </ComboboxOptions>
            </transition>
        </Combobox>
    </div>
</template>

<script setup>
import { ref, computed, watch, defineProps, defineEmits } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import { CheckIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ id: 0, key: '' }),
    },
    tags: {
        type: Array,
        required: true,
    },
    placeholder: {
        type: String,
        default: '',
        required: false,
    },
});

const emit = defineEmits(['update:modelValue']);

// The user's typed text
const searchQuery = ref('');

// The Combobox local selection.
// We watch the parent to keep it in sync.
const internalSelected = ref(props.modelValue);

watch(
    () => props.modelValue,
    (newVal) => {
        internalSelected.value = newVal;
    }
);

/**
 * Filter your keys by searchQuery
 */
const filteredOptions = computed(() => {
    const q = searchQuery.value.toLowerCase();

    return props.tags?.filter((c) => c.key.toLowerCase().includes(q));
});

/**
 * De-select the current selection.
 * We create a new object to trigger a re-render
 */
function clearSelected() {
    const emptySelection = { id: 0, key: '' };

    emit('update:modelValue', { ...emptySelection });

    internalSelected.value = emptySelection;

    searchQuery.value = '';
}

/**
 * Display function:
 * If something is selected, show its key.
 * Otherwise, show the typed search.
 */
function displayValue(selectedObj) {
    return selectedObj && selectedObj.key ? selectedObj.key : searchQuery.value;
}

/**
 * Handle direct user typing in the input
 */
function onInput(event) {
    searchQuery.value = event.target.value;
}

/**
 * Called when user selects an item from the dropdown.
 * This is the crucial step: We emit 'update:modelValue'
 * so the parent’s v-model is updated.
 */
function onChange(newSelection) {
    emit('update:modelValue', newSelection);

    // Also reflect it in our typed text:
    if (newSelection && newSelection.key) {
        searchQuery.value = newSelection.key;
    }
}
</script>
