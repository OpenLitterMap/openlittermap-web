<template>
    <div class="mt-8">
        <div class="bg-white/10 backdrop-blur-sm rounded-lg p-6">
            <div class="flex justify-between items-center mb-3">
                <div class="flex items-center gap-3">
                    <div class="bg-yellow-400 rounded-full w-12 h-12 flex items-center justify-center">
                        <span class="text-xl font-bold text-gray-800">{{ currentLevel }}</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-white">Global Level</h3>
                        <p class="text-sm text-white/70">{{ remainingXP }} XP to next level</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-white">{{ progressPercentage }}%</div>
                    <div class="text-sm text-white/70">Complete</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="relative">
                <div class="w-full bg-white/20 rounded-full h-6 overflow-hidden">
                    <div
                        class="h-full bg-gradient-to-r from-yellow-400 to-green-400 rounded-full transition-all duration-1000 flex items-center justify-end pr-2"
                        :style="`width: ${progressPercentage}%`"
                    >
                        <span v-if="progressPercentage > 10" class="text-xs text-gray-800 font-bold">
                            {{ formatNumber(currentXP) }} / {{ formatNumber(nextLevelXP) }}
                        </span>
                    </div>
                </div>

                <!-- Level Markers -->
                <div class="flex justify-between mt-2">
                    <span class="text-xs text-white/70">Level {{ currentLevel }}</span>
                    <span class="text-xs text-white/70">{{ formatNumber(currentXP) }} XP</span>
                    <span class="text-xs text-white/70">Level {{ currentLevel + 1 }}</span>
                </div>
            </div>

            <!-- Milestones -->
            <div v-if="!loading" class="grid grid-cols-3 gap-4 mt-6">
                <div class="text-center">
                    <div class="text-sm text-white/70">Countries Active</div>
                    <div class="text-xl font-bold text-white">{{ totalCountries }}</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-white/70">Avg Per User</div>
                    <div class="text-xl font-bold text-white">{{ avgPerUser }}</div>
                </div>
                <div class="text-center">
                    <div class="text-sm text-white/70">Time to Level</div>
                    <div class="text-xl font-bold text-white">{{ estimatedTime }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useWorldStore } from '@/stores/world';

const worldStore = useWorldStore();

const props = defineProps({
    loading: Boolean,
});

const levels = [
    { level: 0, min: 0, max: 1000 },
    { level: 1, min: 1000, max: 10000 },
    { level: 2, min: 10000, max: 100000 },
    { level: 3, min: 100000, max: 250000 },
    { level: 4, min: 250000, max: 500000 },
    { level: 5, min: 500000, max: 1000000 },
    { level: 6, min: 1000000, max: 2000000 },
    { level: 7, min: 2000000, max: 5000000 },
    { level: 8, min: 5000000, max: 10000000 },
    { level: 9, min: 10000000, max: 25000000 },
    { level: 10, min: 25000000, max: Infinity },
];

const currentXP = computed(() => worldStore.total_litter || 0);

const currentLevelData = computed(() => {
    return levels.find((l) => currentXP.value >= l.min && currentXP.value < l.max) || levels[0];
});

const currentLevel = computed(() => currentLevelData.value.level);

const nextLevelXP = computed(() => currentLevelData.value.max);

const previousLevelXP = computed(() => currentLevelData.value.min);

const progressPercentage = computed(() => {
    const range = nextLevelXP.value - previousLevelXP.value;
    const progress = currentXP.value - previousLevelXP.value;
    return Math.min(Math.round((progress / range) * 100), 100);
});

const remainingXP = computed(() => {
    const remaining = nextLevelXP.value - currentXP.value;
    return formatNumber(remaining);
});

const totalCountries = computed(() => worldStore.total_countries || 0);

const avgPerUser = computed(() => {
    if (!worldStore.total_contributors) return 0;
    return Math.round(worldStore.total_litter / worldStore.total_contributors);
});

const estimatedTime = computed(() => {
    // Simple estimation based on recent growth rate
    // This would need real data tracking to be accurate
    const xpPerDay = 1000; // Example value
    const daysRemaining = Math.ceil((nextLevelXP.value - currentXP.value) / xpPerDay);

    if (daysRemaining < 7) return `${daysRemaining} days`;
    if (daysRemaining < 30) return `${Math.ceil(daysRemaining / 7)} weeks`;
    if (daysRemaining < 365) return `${Math.ceil(daysRemaining / 30)} months`;
    return `${Math.ceil(daysRemaining / 365)} years`;
});

const formatNumber = (num) => {
    if (!num) return '0';
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
    return num.toLocaleString();
};
</script>
