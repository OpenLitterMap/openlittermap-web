<template>
    <div class="bg-white/5 border border-emerald-500/20 rounded-2xl p-6 md:p-8 text-center">
        <!-- Animated checkmark -->
        <div class="flex justify-center mb-5">
            <div class="w-16 h-16 rounded-full bg-emerald-500/20 flex items-center justify-center">
                <svg class="w-9 h-9" viewBox="0 0 24 24" fill="none">
                    <path
                        class="checkmark-draw"
                        d="M4.5 12.75l6 6 9-13.5"
                        stroke="#34d399"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </div>
        </div>

        <!-- Main stat -->
        <h2 class="text-2xl md:text-3xl font-bold text-white mb-1">
            {{ completedCount }} {{ completedCount === 1 ? $t('observation') : $t('observations') }} {{ $t('recorded') }}
        </h2>

        <!-- Secondary stats -->
        <div class="flex items-center justify-center gap-4 text-sm text-white/50 mb-2">
            <span v-if="sessionXp > 0">+{{ sessionXp }} XP {{ $t('earned') }}</span>
            <span v-if="locations.length > 0">{{ locations.length }} {{ locations.length === 1 ? $t('location') : $t('locations') }}</span>
        </div>

        <!-- Location names -->
        <p v-if="locations.length > 0" class="text-white/30 text-sm mb-6">
            {{ locations.slice(0, 5).join(' · ') }}<template v-if="locations.length > 5"> · +{{ locations.length - 5 }} {{ $t('more') }}</template>
        </p>

        <!-- Failed count -->
        <p v-if="failedCount > 0" class="text-red-400/70 text-sm mb-6">
            {{ failedCount }} {{ failedCount === 1 ? $t('file') : $t('files') }} {{ $t('failed to upload') }}
        </p>

        <!-- Level progress -->
        <div v-if="level" class="max-w-xs mx-auto mb-8">
            <div class="flex items-center justify-between text-xs text-white/40 mb-1.5">
                <span>{{ $t('Level') }} {{ level.level }} · {{ level.title }}</span>
                <span>{{ level.xp_into_level?.toLocaleString() }} / {{ level.xp_for_next?.toLocaleString() }} XP</span>
            </div>
            <div class="w-full h-2 bg-white/10 rounded-full overflow-hidden">
                <div
                    class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-full transition-all duration-1000"
                    :style="{ width: (level.progress_percent || 0) + '%' }"
                />
            </div>
        </div>

        <!-- CTAs -->
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <router-link
                to="/tag"
                class="inline-flex items-center justify-center gap-2 w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 px-8 py-3.5 rounded-xl text-white font-semibold transition-all duration-200 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-400/30"
            >
                {{ $t('Tag your photos to earn more XP') }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </router-link>

            <div class="flex items-center gap-3">
                <button
                    @click="$emit('reset')"
                    class="inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:bg-white/10 px-5 py-3 rounded-xl text-white/60 hover:text-white text-sm transition-all duration-200"
                >
                    {{ $t('Upload More') }}
                </button>

                <router-link
                    to="/global"
                    class="inline-flex items-center gap-2 text-white/40 hover:text-white/60 text-sm transition-colors"
                >
                    {{ $t('View on map') }}
                </router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    completedCount: { type: Number, default: 0 },
    failedCount: { type: Number, default: 0 },
    sessionXp: { type: Number, default: 0 },
    locations: { type: Array, default: () => [] },
    level: { type: Object, default: null },
});

defineEmits(['reset']);
</script>

<style scoped>
.checkmark-draw {
    stroke-dasharray: 30;
    stroke-dashoffset: 30;
    animation: draw-check 0.6s ease-out 0.3s forwards;
}

@keyframes draw-check {
    to {
        stroke-dashoffset: 0;
    }
}
</style>
