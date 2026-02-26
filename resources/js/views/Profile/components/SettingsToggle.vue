<template>
    <div class="flex items-center justify-between gap-4 py-1">
        <div>
            <div class="text-white text-sm">{{ label }}</div>
            <div v-if="description" class="text-white/30 text-xs">{{ description }}</div>
        </div>
        <button
            class="relative w-11 h-6 rounded-full transition-colors"
            :class="value ? 'bg-emerald-500' : 'bg-white/20'"
            @click="$emit('toggle')"
        >
            <span
                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform"
                :class="{ 'translate-x-5': value }"
            ></span>
        </button>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    description: { type: String, default: '' },
    value: { type: Boolean, default: false },
});

defineEmits(['toggle']);
</script>
