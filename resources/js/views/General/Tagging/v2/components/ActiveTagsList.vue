<template>
    <div class="bg-white/5 border border-white/10 rounded-xl p-4 h-full overflow-y-auto">
        <h3 class="text-white font-semibold mb-4 flex items-center justify-between">
            <span>Tags</span>
            <span v-if="tags.length > 0" class="text-sm font-normal text-white/40">
                {{ tags.length }} {{ tags.length === 1 ? 'tag' : 'tags' }}
            </span>
        </h3>

        <!-- No photos state -->
        <div v-if="!hasPhotos" class="text-center py-12">
            <p class="text-white/40">You have nothing to tag.</p>
        </div>

        <!-- Empty tags state -->
        <div v-else-if="tags.length === 0" class="text-center py-12">
            <svg class="w-16 h-16 mx-auto mb-4 text-white/10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                />
            </svg>
            <p class="text-white/40 mb-2">No tags added yet</p>
            <p class="text-white/30 text-sm">Use the search bar above or press <kbd class="px-1.5 py-0.5 bg-white/5 rounded text-white/40 font-mono text-xs">/</kbd> to search</p>
        </div>

        <!-- Tags list -->
        <div v-else class="space-y-3">
            <TagCard
                v-for="tag in tags"
                :key="tag.id"
                :tag="tag"
                :brands="brands"
                :materials="materials"
                :searchable-tags="searchableTags"
                :available-types="getTypesForTag(tag)"
                :suggested-materials="getSuggestedMaterials(tag)"
                @update-quantity="(q) => $emit('update-quantity', tag.id, q)"
                @add-detail="(detail) => $emit('add-detail', tag.id, detail)"
                @remove="() => $emit('remove-tag', tag.id)"
                @set-picked-up="(val) => $emit('set-picked-up', tag.id, val)"
                @set-type="(val) => $emit('set-type', tag.id, val)"
                @remove-detail="(detail) => $emit('remove-detail', tag.id, detail)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import TagCard from './TagCard.vue';
import { useTagsStore } from '@stores/tags/index.js';

const tagsStore = useTagsStore();

const materialsByKey = computed(() => new Map(tagsStore.materials.map((m) => [m.key, m])));

defineProps({
    hasPhotos: {
        type: Boolean,
        default: true,
    },
    tags: {
        type: Array,
        default: () => [],
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

defineEmits(['update-quantity', 'set-picked-up', 'set-type', 'add-detail', 'remove-detail', 'remove-tag']);

const getTypesForTag = (tag) => {
    if (!tag.cloId) return [];
    return tagsStore.getTypesForClo(tag.cloId);
};

const getSuggestedMaterials = (tag) => {
    if (!tag.object?.suggested_materials?.length) return [];
    return tag.object.suggested_materials
        .map((key) => materialsByKey.value.get(key))
        .filter(Boolean);
};
</script>
