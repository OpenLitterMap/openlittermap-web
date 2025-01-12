<template>
    <Listbox
        as="div"
        :value="modelValue"
        @update:modelValue="onChange"
        class="relative"
    >
        <!-- Button -->
        <ListboxButton
            class="relative w-60 cursor-default rounded bg-white py-2 pl-3 pr-8 text-left shadow-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-indigo-500"
        >
            <!-- If no selection, show placeholder text -->
            <span class="block truncate">
                {{ modelValue || 'Select Category' }}
            </span>

            <!-- Down Arrow Icon -->
            <span
                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2 text-gray-500"
            >
                <svg
                    class="h-4 w-4"
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
            </span>
        </ListboxButton>

        <!-- Transition for opening/closing the options -->
        <transition
            enter="transition duration-100 ease-out"
            enter-from="transform scale-95 opacity-0"
            enter-to="transform scale-100 opacity-100"
            leave="transition duration-75 ease-in"
            leave-from="transform scale-100 opacity-100"
            leave-to="transform scale-95 opacity-0"
        >
            <ListboxOptions
                class="absolute mt-1 max-h-60 w-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
            >
                <!-- Input field for filtering -->
                <div class="px-3 py-2">
                    <input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search..."
                        class="w-full rounded border border-gray-300 p-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Loop over the filtered categories array -->
                <ListboxOption
                    v-for="category in filteredCategories"
                    :key="category"
                    :value="category"
                    :class="
                        ({ active, selected }) => [
                            'relative cursor-default select-none py-2 pl-10 pr-4',
                            active
                                ? 'bg-indigo-600 text-white'
                                : 'text-gray-900',
                            selected ? 'font-medium' : 'font-normal',
                        ]
                    "
                >
                    <!-- Show the category string -->
                    <span class="block truncate">
                        {{ category }}
                    </span>

                    <!-- Checkmark if this option is the selected one -->
                    <span
                        v-if="modelValue === category"
                        class="absolute inset-y-0 left-0 flex items-center pl-3 text-white"
                    >
                        <svg
                            class="h-5 w-5"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </span>
                </ListboxOption>
            </ListboxOptions>
        </transition>
    </Listbox>
</template>

<script setup>
    import { ref, computed } from 'vue';
    import { defineProps, defineEmits } from 'vue';
    import {
        Listbox,
        ListboxButton,
        ListboxOptions,
        ListboxOption,
    } from '@headlessui/vue';

    // Props
    // - modelValue = the current selected category (a string)
    // - categories = array of category strings
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

    // Emit events
    // We'll emit 'update:modelValue' whenever the user picks a new category
    const emit = defineEmits(['update:modelValue']);

    // State for the search query
    const searchQuery = ref('');

    // Filtered categories based on the search query
    const filteredCategories = computed(() =>
        props.categories.filter((category) =>
            category.toLowerCase().includes(searchQuery.value.toLowerCase())
        )
    );

    // Called when user selects a new value in the Listbox
    function onChange(newValue) {
        console.log({ newValue });
        // This tells the parent to update selectedCategory
        emit('update:modelValue', newValue);
    }
</script>

<style scoped>
    /* Additional styles or Tailwind classes can go here if needed. */
</style>
