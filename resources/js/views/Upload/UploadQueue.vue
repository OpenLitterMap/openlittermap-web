<template>
    <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between px-3 py-2 border-b border-white/5">
            <span class="text-xs text-white/40 uppercase tracking-wider">{{ $t('Upload Queue') }}</span>
            <span class="text-xs text-white/30 tabular-nums">{{ queue.length }} {{ queue.length === 1 ? 'file' : 'files' }}</span>
        </div>

        <!-- Scrollable list -->
        <div class="flex-1 overflow-y-auto p-2 space-y-1">
            <UploadQueueCard v-for="file in queue" :key="file.id" :file="file" />
        </div>
    </div>
</template>

<script setup>
import UploadQueueCard from './UploadQueueCard.vue';

defineProps({
    queue: { type: Array, default: () => [] },
});
</script>
