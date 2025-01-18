<template>
    <div class="w-[20em]">
        <Combobox
            as="div"
            :modeValue="internalSelected"
            @update:modelValue="onChange"
            by="id"
        >
            <div class="relative">
                <ComboboxInput
                    :displayValue="displayValue"
                    @input="onInput"
                    placeholder="Search or Select Category..."
                    class="rounded-md border border-gray-300 bg-white py-2 px-3 text-left shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                />
                <ComboboxButton
                    class="absolute inset-y-0 right-0 flex items-center pr-2"
                >
                    <svg
                        class="h-4 w-4 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
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
                enter="transition duration-150 ease-out"
                enter-from="transform scale-95 opacity-0"
                enter-to="transform scale-100 opacity-100"
                leave="transition duration-100 ease-in"
                leave-from="transform scale-100 opacity-100"
                leave-to="transform scale-95 opacity-0"
            >
                <!-- If we have filtered results, show the dropdown -->
                <ComboboxOptions
                    v-if="filteredCategories.length"
                    class="absolute z-10 mt-1 w-[20em] max-h-60 overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
                >
                    <ComboboxOption
                        v-for="category in filteredCategories"
                        :key="category.id"
                        :value="category"
                        :class="
                            ({ active }) => [
                                'relative cursor-pointer select-none py-2 pl-3 pr-9',
                                active
                                    ? 'bg-indigo-600 text-white'
                                    : 'text-gray-900',
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
                            <span class="flex-1 capitalize">{{
                                category.category
                            }}</span>
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
    import {
        Combobox,
        ComboboxInput,
        ComboboxButton,
        ComboboxOptions,
        ComboboxOption,
    } from '@headlessui/vue';
    import { CheckIcon } from '@heroicons/vue/20/solid';

    // PROPS
    const props = defineProps({
        modelValue: {
            // This is the selected object, e.g. { id: 0, category: '' }
            type: Object,
            default: () => ({ id: 0, category: '' }),
        },
        // An array of objects like [{ id: 1, category: 'food' }, ...]
        categories: {
            type: Array,
            required: true,
        },
    });

    // EMITS
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
     * Filter your categories by searchQuery
     */
    const filteredCategories = computed(() => {
        const q = searchQuery.value.toLowerCase();
        return props.categories.filter((c) =>
            c.category.toLowerCase().includes(q)
        );
    });

    /**
     * Display function:
     * If something is selected, show its .category.
     * Otherwise, show the typed search.
     */
    function displayValue(selectedObj) {
        return selectedObj && selectedObj.category
            ? selectedObj.category
            : searchQuery.value;
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
        if (newSelection && newSelection.category) {
            searchQuery.value = newSelection.category;
        }
    }
</script>
