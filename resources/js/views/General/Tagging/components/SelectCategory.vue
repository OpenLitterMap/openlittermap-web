<template>
    <div>
        <Combobox as="div" v-model="selectedCategory" @change="onChange">
            <div class="relative">
                <ComboboxInput
                    v-model="searchQuery"
                    placeholder="Select or Search Category..."
                    class="w-full rounded bg-white py-2 px-3 text-left shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />

                <ComboboxButton
                    class="absolute inset-y-0 right-0 flex items-center pr-2"
                >
                    <svg
                        class="h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </ComboboxButton>
            </div>

            <transition
                enter="transition duration-100 ease-out"
                enter-from="transform scale-95 opacity-0"
                enter-to="transform scale-100 opacity-100"
                leave="transition duration-75 ease-in"
                leave-from="transform scale-100 opacity-100"
                leave-to="transform scale-95 opacity-0"
            >
                <ComboboxOptions
                    v-if="filteredCategories.length"
                    class="absolute mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                >
                    <ComboboxOption
                        v-for="category in filteredCategories"
                        :key="category"
                        :value="category"
                        :class="
                            ({ active }) => [
                                'relative cursor-default select-none py-2 pl-3 pr-9',
                                active
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-900',
                            ]
                        "
                    >
                        {{ category }}
                    </ComboboxOption>
                </ComboboxOptions>

                <div
                    v-else
                    class="absolute mt-1 w-full rounded-md bg-white py-2 px-3 text-gray-500 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                >
                    No results found.
                </div>
            </transition>
        </Combobox>
    </div>
</template>

<script setup>
    import { ref, computed, watch } from 'vue';
    import { defineProps, defineEmits } from 'vue';
    import {
        Combobox,
        ComboboxInput,
        ComboboxButton,
        ComboboxOptions,
        ComboboxOption,
    } from '@headlessui/vue';

    // Props for parent -> child data
    const props = defineProps({
        modelValue: {
            type: String,
            default: '',
        },
        categories: {
            type: Array,
            required: true,
        },
    });

    // Emit: notify the parent when the user picks a new option
    const emit = defineEmits(['update:modelValue']);

    // The user’s typed query for filtering
    const searchQuery = ref('');

    // The currently selected category
    // We mirror 'modelValue' to keep it in sync with the parent via v-model
    const selectedCategory = ref(props.modelValue);

    // Whenever parent updates modelValue, mirror it locally
    // (this is optional if you want a fully controlled component)
    watch(
        () => props.modelValue,
        (newVal) => {
            selectedCategory.value = newVal;
        }
    );

    // Filter the categories list based on searchQuery
    const filteredCategories = computed(() =>
        props.categories.filter((cat) =>
            cat.toLowerCase().includes(searchQuery.value.toLowerCase())
        )
    );

    /**
     * Fires whenever the user changes the selection in the ComboBox.
     * This triggers our local state and also notifies the parent with 'update:modelValue'.
     */
    function onChange(newVal) {
        emit('update:modelValue', newVal);
    }
</script>

<style scoped>
    /* Optional additional styling */
</style>
