<template>
    <div class="relative">
        <Combobox :modelValue="selected" @update:modelValue="handleSelection" nullable>
            <div class="relative">
                <ComboboxInput
                    id="unified-tag-search-input"
                    @change="query = $event.target.value"
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
                    v-if="query.length > 0 && (groupedTags.length > 0 || query.length > 2)"
                    class="absolute z-10 mt-1 w-full max-h-[30rem] overflow-auto rounded-lg bg-gray-700 py-1 shadow-lg ring-1 ring-black ring-opacity-25 focus:outline-none"
                >
                    <!-- Grouped tag sections -->
                    <template v-for="group in groupedTags" :key="group.type">
                        <li
                            class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-500 sticky top-0 bg-gray-700/95 backdrop-blur-sm border-b border-gray-600/30"
                        >
                            {{ groupLabel(group.type) }}
                        </li>

                        <ComboboxOption
                            v-for="tag in group.tags"
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
                                <span class="flex items-center gap-2">
                                    <span
                                        :class="['truncate', selected ? 'font-semibold' : 'font-normal']"
                                        v-html="highlightMatch(tag.key)"
                                    />

                                    <!-- Category breadcrumb for objects -->
                                    <span
                                        v-if="tag.raw?.categories?.length"
                                        :class="['text-xs shrink-0', active ? 'text-blue-200' : 'text-gray-500']"
                                    >
                                        · {{ tag.raw.categories[0].key }}
                                    </span>

                                    <!-- Type badge -->
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium shrink-0 ml-auto',
                                            active ? 'bg-blue-500/40 text-blue-100' : typeBadgeClass(tag.type),
                                        ]"
                                    >
                                        {{ tag.type }}
                                    </span>
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
                    </template>

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
                            <span class="flex items-center gap-2">
                                <span>Create custom tag: "{{ query }}"</span>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium not-italic ml-auto',
                                        active ? 'bg-blue-500/40 text-blue-100' : 'bg-purple-500/20 text-purple-300',
                                    ]"
                                >
                                    customTag
                                </span>
                            </span>
                        </li>
                    </ComboboxOption>
                </ComboboxOptions>
            </transition>
        </Combobox>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
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

onMounted(() => {
    nextTick(() => {
        document.getElementById('unified-tag-search-input')?.focus();
    });
});

const filteredTags = computed(() => {
    if (query.value === '') return [];

    const searchTerm = query.value.toLowerCase();
    return props.tags.filter((tag) => {
        // Direct key match
        if (tag.key.toLowerCase().includes(searchTerm)) return true;

        // Match objects by their parent category name
        if (tag.type === 'object' && tag.raw?.categories?.length) {
            return tag.raw.categories.some((cat) => cat.key.toLowerCase().includes(searchTerm));
        }

        return false;
    });
});

const groupedTags = computed(() => {
    const groups = {};
    for (const tag of filteredTags.value) {
        const type = tag.type || 'other';
        if (!groups[type]) groups[type] = { type, tags: [] };
        groups[type].tags.push(tag);
    }

    const order = ['object', 'category', 'material', 'brand', 'customTag'];
    return order
        .map((t) => {
            if (!groups[t]) return null;
            return { type: groups[t].type, tags: groups[t].tags.slice(0, 15) };
        })
        .filter(Boolean);
});

const exactMatch = computed(() => {
    const searchTerm = query.value.toLowerCase();
    return props.tags.some((tag) => tag.key.toLowerCase() === searchTerm);
});

const typeBadgeClass = (type) => {
    const classes = {
        category: 'bg-green-500/20 text-green-300',
        object: 'bg-sky-500/20 text-sky-300',
        brand: 'bg-amber-500/20 text-amber-300',
        material: 'bg-teal-500/20 text-teal-300',
        customTag: 'bg-purple-500/20 text-purple-300',
    };
    return classes[type] || 'bg-gray-500/20 text-gray-300';
};

const groupLabel = (type) => {
    const labels = {
        object: 'Objects',
        category: 'Categories',
        material: 'Materials',
        brand: 'Brands',
        customTag: 'Custom Tags',
    };
    return labels[type] || type;
};

const highlightMatch = (text) => {
    if (!query.value) return text;
    const idx = text.toLowerCase().indexOf(query.value.toLowerCase());
    if (idx === -1) return text;
    const before = text.slice(0, idx);
    const match = text.slice(idx, idx + query.value.length);
    const after = text.slice(idx + query.value.length);
    return `${before}<span class="font-bold text-white underline decoration-blue-400/50">${match}</span>${after}`;
};

const handleSelection = (value) => {
    if (!value) return;

    if (value.custom) {
        emit('custom-tag', value);
    } else {
        emit('tag-selected', value);
    }

    query.value = '';
    selected.value = null;
};
</script>
