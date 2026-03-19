<template>
    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/[0.03] border border-white/5">
        <!-- Status icon -->
        <div class="flex-shrink-0 w-5 h-5 flex items-center justify-center">
            <!-- Waiting -->
            <div v-if="file.status === 'waiting'" class="w-3.5 h-3.5 rounded-full border border-white/20" />

            <!-- Uploading -->
            <div
                v-else-if="file.status === 'uploading'"
                class="w-4 h-4 rounded-full border-2 border-white/20 border-t-emerald-400 animate-spin"
            />

            <!-- Success -->
            <svg
                v-else-if="file.status === 'success'"
                class="w-4 h-4 text-emerald-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="2.5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>

            <!-- Failed -->
            <svg
                v-else-if="file.status === 'failed'"
                class="w-4 h-4 text-red-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="2.5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </div>

        <!-- File info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="text-sm text-white/70 truncate">{{ file.name }}</span>

                <!-- City name on success -->
                <span v-if="file.status === 'success' && file.city" class="text-xs text-white/40 truncate">
                    {{ file.city }}
                </span>
            </div>

            <!-- Progress bar during upload -->
            <div v-if="file.status === 'uploading'" class="mt-1.5 w-full bg-white/5 rounded-full h-1">
                <div
                    class="bg-emerald-500 h-1 rounded-full transition-all duration-300"
                    :style="{ width: Math.round(file.progress * 100) + '%' }"
                />
            </div>

            <!-- Error message -->
            <p v-if="file.status === 'failed' && file.error" class="text-xs text-red-400/70 mt-0.5 truncate">
                {{ file.error }}
            </p>
        </div>

        <!-- XP badge (success only) -->
        <div v-if="file.status === 'success'" class="flex-shrink-0 relative">
            <span class="text-xs font-bold text-emerald-400">+{{ file.xp || 1 }}</span>
            <span v-if="file.showXpAnimation" class="xp-float absolute -top-1 left-0 text-xs font-bold text-emerald-400">
                +{{ file.xp || 1 }}
            </span>
        </div>

        <!-- Upload percentage during upload -->
        <span v-if="file.status === 'uploading'" class="flex-shrink-0 text-xs text-white/30 tabular-nums w-8 text-right">
            {{ Math.round(file.progress * 100) }}%
        </span>
    </div>
</template>

<script setup>
defineProps({
    file: { type: Object, required: true },
});
</script>

<style scoped>
@keyframes float-up {
    0% {
        opacity: 1;
        transform: translateY(0);
    }
    100% {
        opacity: 0;
        transform: translateY(-20px);
    }
}

.xp-float {
    animation: float-up 1.2s ease-out forwards;
    pointer-events: none;
}
</style>
