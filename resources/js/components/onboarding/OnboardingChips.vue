<template>
    <div v-if="chips.length" class="mb-4">
        <p class="mb-2 text-sm text-white/50">Quick tags — tap to add:</p>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="chip in chips"
                :key="chip.id"
                @click="$emit('add-tag', chip)"
                class="rounded-full border border-white/20 bg-white/5 px-4 py-1.5 text-sm font-medium text-white/80 transition-all hover:border-emerald-500/50 hover:bg-emerald-500/10 hover:text-emerald-300 active:scale-95"
            >
                {{ chip.label }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useTagsStore } from '@stores/tags/index.js';

defineEmits(['add-tag']);

const tagsStore = useTagsStore();

const CHIP_ITEMS = [
    { objectKey: 'butts', categoryKey: 'smoking', label: 'Cigarette butt' },
    { objectKey: 'bottle', categoryKey: 'softdrinks', label: 'Bottle' },
    { objectKey: 'can', categoryKey: 'softdrinks', label: 'Can' },
    { objectKey: 'wrapper', categoryKey: 'food', label: 'Wrapper' },
    { objectKey: 'cup', categoryKey: 'coffee', label: 'Cup' },
    { objectKey: 'bag', categoryKey: 'food', label: 'Bag' },
];

const chips = computed(() => {
    return CHIP_ITEMS.map((item) => {
        const obj = tagsStore.objects.find((o) => o.key === item.objectKey);
        if (!obj) return null;

        const cat = obj.categories?.find((c) => c.key === item.categoryKey);
        if (!cat) return null;

        const cloId = tagsStore.getCloId(cat.id, obj.id);
        if (!cloId) return null;

        return {
            id: `chip-${obj.id}-${cat.id}`,
            key: obj.key,
            label: item.label,
            type: 'object',
            cloId,
            categoryId: cat.id,
            categoryKey: cat.key,
            raw: obj,
        };
    }).filter(Boolean);
});
</script>
