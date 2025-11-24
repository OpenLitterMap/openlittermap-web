<template>
    <div class="relative">
        <Combobox :modelValue="selected" @update:modelValue="handleSelection" nullable>
            <div class="relative">
                <ComboboxInput
                    @change="query = $event.target.value"
                    @keydown.enter.prevent="handleEnter"
                    :displayValue="(item) => item?.key || ''"
                    :placeholder="placeholder"
                    class="w-full rounded-lg bg-gray-700 border border-gray-600 text-white px-4 py-3 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 placeholder-gray-400"
                />

                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </ComboboxButton>
            </div>

            <transition leave="transition ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0">
                <ComboboxOptions
                    v-if="filteredTags.length > 0 || query.length > 2"
                    class="absolute z-10 mt-1 w-full max-h-60 overflow-auto rounded-lg bg-gray-700 py-1 shadow-lg ring-1 ring-black ring-opacity-25 focus:outline-none"
                >
                    <!-- Regular tag options -->
                    <ComboboxOption
                        v-for="tag in filteredTags"
                        :key="tag.id"
                        :value="tag"
                        v-slot="{ active, selected }"
                        class="cursor-pointer"
                    >
                        <li
                            :class="[
                                'relative select-none py-2 pl-4 pr-9',
                                active ? 'bg-blue-600 text-white' : 'text-gray-200',
                            ]"
                        >
                            <span :class="['block truncate', selected ? 'font-semibold' : 'font-normal']">
                                {{ tag.key }}
                            </span>

                            <span
                                v-if="selected"
                                :class="[
                                    'absolute inset-y-0 right-0 flex items-center pr-3',
                                    active ? 'text-white' : 'text-blue-600',
                                ]"
                            >
                                <CheckIcon class="h-5 w-5" />
                            </span>
                        </li>
                    </ComboboxOption>

                    <!-- Custom tag option -->
                    <ComboboxOption
                        v-if="query.length > 2 && !exactMatch"
                        :value="{ custom: true, key: query }"
                        v-slot="{ active }"
                    >
                        <li
                            :class="[
                                'relative select-none py-2 pl-4 pr-9 italic',
                                active ? 'bg-blue-600 text-white' : 'text-gray-300',
                            ]"
                        >
                            Create custom tag: "{{ query }}"
                        </li>
                    </ComboboxOption>
                </ComboboxOptions>
            </transition>
        </Combobox>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/vue';
import { CheckIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    modelValue: String,
    tags: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: 'Search or create tag...',
    },
});

const emit = defineEmits(['update:modelValue', 'tag-selected', 'custom-tag']);

const query = ref('');
const selected = ref(null);

const filteredTags = computed(() => {
    if (query.value === '') {
        return props.tags.slice(0, 20); // Show first 20 when empty
    }

    const searchTerm = query.value.toLowerCase();
    return props.tags.filter((tag) => tag.key.toLowerCase().includes(searchTerm)).slice(0, 50);
});

const exactMatch = computed(() => {
    const searchTerm = query.value.toLowerCase();
    return props.tags.some((tag) => tag.key.toLowerCase() === searchTerm);
});

const handleSelection = (value) => {
    if (!value) return;

    if (value.custom) {
        emit('custom-tag', value);
    } else {
        emit('tag-selected', value);
    }

    // Clear after selection
    query.value = '';
    selected.value = null;
};

const handleEnter = () => {
    if (filteredTags.value.length > 0) {
        handleSelection(filteredTags.value[0]);
    } else if (query.value.length > 2) {
        handleSelection({ custom: true, key: query.value });
    }
};
</script>
