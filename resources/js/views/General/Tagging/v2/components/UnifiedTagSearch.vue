<template>
    <div class="relative">
        <Combobox :modelValue="selected" @update:modelValue="handleSelection" nullable>
            <div class="relative">
                <ComboboxInput
                    id="unified-tag-search-input"
                    @change="query = $event.target.value"
                    :displayValue="(item) => item?.key || ''"
                    :placeholder="placeholder"
                    inputmode="search"
                    class="w-full rounded-lg bg-white/5 border border-white/10 text-white px-4 py-3 focus:border-emerald-500/50 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 placeholder-white/30"
                />

                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-3">
                    <svg class="h-5 w-5 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </ComboboxButton>
            </div>

            <transition leave="transition ease-in duration-100" leave-from="opacity-100" leave-to="opacity-0">
                <ComboboxOptions
                    v-if="debouncedQuery.length > 0 && (groupedTags.length > 0 || debouncedQuery.length > 2)"
                    class="absolute z-10 mt-1 w-full max-h-[30rem] overflow-auto rounded-lg bg-slate-800/95 backdrop-blur border border-white/10 py-1 shadow-xl focus:outline-none"
                >
                    <!-- Grouped tag sections -->
                    <template v-for="group in groupedTags" :key="group.type">
                        <li
                            class="px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-white/40 sticky top-0 bg-slate-800/95 backdrop-blur-sm border-b border-white/10"
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
                                    active ? 'bg-emerald-500/20 text-white' : 'text-white/80',
                                ]"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        :class="['truncate', selected ? 'font-semibold' : 'font-normal']"
                                        v-html="highlightMatch(formatKey(tag.key))"
                                    />

                                    <!-- Parent object context for type results -->
                                    <span
                                        v-if="tag.type === 'type' && tag.objectKey"
                                        :class="['text-xs shrink-0', active ? 'text-emerald-200' : 'text-white/40']"
                                        v-html="'(' + highlightMatch(formatKey(tag.objectKey)) + ')'"
                                    />

                                    <!-- Category breadcrumb -->
                                    <span
                                        v-if="tag.categoryKey"
                                        :class="['text-xs shrink-0', active ? 'text-emerald-200' : 'text-white/30']"
                                        v-html="'&middot; ' + highlightMatch(formatKey(tag.categoryKey))"
                                    />

                                    <!-- Type badge -->
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium shrink-0 ml-auto',
                                            active ? 'bg-emerald-500/30 text-emerald-100' : typeBadgeClass(tag.type),
                                        ]"
                                    >
                                        {{ tag.type }}
                                    </span>
                                </span>

                                <span
                                    v-if="selected"
                                    :class="[
                                        'absolute inset-y-0 right-0 flex items-center pr-3',
                                        active ? 'text-white' : 'text-emerald-400',
                                    ]"
                                >
                                    <CheckIcon class="h-5 w-5" />
                                </span>
                            </li>
                        </ComboboxOption>
                    </template>

                    <!-- Custom tag option -->
                    <ComboboxOption
                        v-if="debouncedQuery.length > 2 && !exactMatch"
                        :value="{ custom: true, key: query }"
                        v-slot="{ active }"
                    >
                        <li
                            :class="[
                                'relative select-none py-2 pl-4 pr-9 italic',
                                active ? 'bg-emerald-500/20 text-white' : 'text-white/60',
                            ]"
                        >
                            <span class="flex items-center gap-2">
                                <span>Create custom tag: "{{ query }}"</span>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium not-italic ml-auto',
                                        active ? 'bg-emerald-500/30 text-emerald-100' : 'bg-purple-500/20 text-purple-300',
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
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
const debouncedQuery = ref('');
let debounceTimer = null;

watch(query, (val) => {
    clearTimeout(debounceTimer);
    if (val === '') {
        debouncedQuery.value = '';
        return;
    }
    debounceTimer = setTimeout(() => {
        debouncedQuery.value = val;
    }, 100);
});

const formatKey = (key) => {
    if (!key) return '';
    return key.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

onMounted(() => {
    nextTick(() => {
        document.getElementById('unified-tag-search-input')?.focus();
    });
});

onUnmounted(() => {
    clearTimeout(debounceTimer);
});

/**
 * Score a match: lower = better.
 *  0 — key starts with query (e.g. "bot" → "bottle")
 *  1 — a word boundary starts with query (e.g. "bot" → "wine_bottle")
 *  2 — substring match anywhere
 */
const scoreMatch = (lowerKey, term, normalizedTerm) => {
    if (lowerKey.startsWith(term) || lowerKey.startsWith(normalizedTerm)) return 0;
    // Check word boundaries (after _ or space)
    const words = lowerKey.split(/[_ ]/);
    if (words.some((w) => w.startsWith(term) || w.startsWith(normalizedTerm))) return 1;
    return 2;
};

const filteredTags = computed(() => {
    if (debouncedQuery.value === '') return [];

    const searchTerm = debouncedQuery.value.toLowerCase();
    // Normalize spaces to underscores so "wine bottle" matches "wine_bottle"
    const normalizedTerm = searchTerm.replace(/\s+/g, '_');
    const scored = [];

    for (const tag of props.tags) {
        // Use precomputed lowerKey when available
        const lowerKey = tag.lowerKey || tag.key.toLowerCase();
        const lowerCat = tag.categoryKey?.toLowerCase();

        let matched = false;
        let score = 3;

        if (lowerKey.includes(searchTerm) || lowerKey.includes(normalizedTerm)) {
            matched = true;
            score = scoreMatch(lowerKey, searchTerm, normalizedTerm);
        } else if (lowerCat?.includes(searchTerm) || lowerCat?.includes(normalizedTerm)) {
            matched = true;
            score = 2;
        } else if (tag.type === 'type') {
            const lowerObj = tag.objectKey?.toLowerCase();
            if (lowerObj?.includes(searchTerm) || lowerObj?.includes(normalizedTerm)) {
                matched = true;
                score = 2;
            }
        }

        if (matched) {
            scored.push({ tag, score });
        }
    }

    scored.sort((a, b) => a.score - b.score);
    return scored.map((s) => s.tag);
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
    const searchTerm = debouncedQuery.value.toLowerCase();
    return props.tags.some((tag) => tag.key.toLowerCase() === searchTerm);
});

const typeBadgeClass = (type) => {
    const classes = {
        category: 'bg-green-500/20 text-green-300',
        object: 'bg-sky-500/20 text-sky-300',
        type: 'bg-indigo-500/20 text-indigo-300',
        brand: 'bg-amber-500/20 text-amber-300',
        material: 'bg-teal-500/20 text-teal-300',
        customTag: 'bg-purple-500/20 text-purple-300',
    };
    return classes[type] || 'bg-gray-500/20 text-gray-300';
};

const groupLabel = (type) => {
    const labels = {
        object: 'Objects',
        type: 'Types',
        category: 'Categories',
        material: 'Materials',
        brand: 'Brands',
        customTag: 'Custom Tags',
    };
    return labels[type] || type;
};

const highlightMatch = (text) => {
    const q = debouncedQuery.value || query.value;
    if (!q) return text;
    const idx = text.toLowerCase().indexOf(q.toLowerCase());
    if (idx === -1) return text;
    const before = text.slice(0, idx);
    const match = text.slice(idx, idx + q.length);
    const after = text.slice(idx + q.length);
    return `${before}<span class="font-bold text-white underline decoration-emerald-400/50">${match}</span>${after}`;
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
