<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

// State
const achievements = ref({});
const summary = ref({ total: 0, unlocked: 0, percentage: 0 });
const isLoading = ref(false);
const error = ref(null);
const expandedTypes = ref(new Set());

// Fetch achievements and progress
async function fetchAchievements() {
    isLoading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get('/api/achievements');
        achievements.value = data.achievements;
        summary.value = data.summary;
    } catch (err) {
        console.error('Failed to load achievements:', err);
        error.value = 'Failed to load achievements';
    } finally {
        isLoading.value = false;
    }
}

// Toggle stack expansion
function toggleStack(type) {
    if (expandedTypes.value.has(type)) {
        expandedTypes.value.delete(type);
    } else {
        expandedTypes.value.add(type);
    }
    // Force reactivity
    expandedTypes.value = new Set(expandedTypes.value);
}

// Get processed achievements for a type
function getProcessedAchievements(items) {
    const unlocked = items.filter((a) => a.unlocked);
    const locked = items.filter((a) => !a.unlocked);
    return { unlocked, locked };
}

// Get stack transform styles
function getStackStyle(index, total, isExpanded) {
    if (isExpanded) {
        return {
            transform: `translateY(${index * 120}px) scale(1)`,
            opacity: 1,
            zIndex: total - index,
            pointerEvents: 'auto',
        };
    }

    // Stacked view
    const offset = Math.min(index * 3, 15);
    const scale = 1 - index * 0.02;
    return {
        transform: `translateY(${offset}px) translateX(${offset}px) scale(${scale})`,
        opacity: index < 3 ? 1 : 0,
        zIndex: total - index,
        pointerEvents: index === 0 ? 'auto' : 'none',
    };
}

// Get card classes based on achievement status and progress
function getCardClasses(achievement) {
    if (achievement.unlocked) {
        return 'border-green-500 bg-gradient-to-br from-green-50 to-green-100 shadow-lg shadow-green-200/50';
    }
    if (achievement.percentage >= 75) {
        return 'border-yellow-500 bg-gradient-to-br from-yellow-50 to-yellow-100 shadow-lg shadow-yellow-200/50';
    }
    if (achievement.percentage >= 50) {
        return 'border-blue-500 bg-gradient-to-br from-blue-50 to-blue-100 shadow-lg shadow-blue-200/50';
    }
    return 'border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100 shadow-lg shadow-gray-200/50';
}

// Format type name for display
function formatType(type) {
    return type.charAt(0).toUpperCase() + type.slice(1);
}

// Get achievement title
function getAchievementTitle(achievement) {
    if (achievement.metadata?.name) {
        return achievement.metadata.name;
    }

    const baseTitle = `${formatType(achievement.type)} - ${achievement.threshold}`;
    if (achievement.tag_name) {
        return `${achievement.tag_name} - ${achievement.threshold}`;
    }
    return baseTitle;
}

onMounted(() => {
    fetchAchievements();
});
</script>

<template>
    <div class="max-w-7xl mx-auto p-4">
        <!-- Header with Summary -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold mb-2">Achievements</h1>
            <div class="flex items-center gap-4 text-sm text-gray-600">
                <span class="font-medium">{{ summary.unlocked }} / {{ summary.total }} unlocked</span>
                <div class="flex-1 max-w-xs">
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-green-400 to-green-600 transition-all duration-1000 ease-out"
                            :style="`width: ${summary.percentage}%`"
                        ></div>
                    </div>
                </div>
                <span class="font-medium">{{ summary.percentage }}%</span>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="text-center py-12">
            <div class="inline-flex items-center text-gray-500">
                <svg class="animate-spin h-6 w-6 mr-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                Loading achievements...
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="text-center py-12 text-red-600">
            {{ error }}
        </div>

        <!-- Achievement Groups -->
        <div v-else>
            <div v-for="(items, type) in achievements" :key="type" class="mb-16">
                <h2 class="text-xl font-semibold mb-6 flex items-center gap-2">
                    {{ formatType(type) }}
                    <span class="text-sm font-normal text-gray-500">
                        ({{ items.filter((a) => a.unlocked).length }} unlocked)
                    </span>
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Unlocked Achievements Stack -->
                    <div v-if="getProcessedAchievements(items).unlocked.length > 0" class="relative">
                        <h3 class="text-sm font-medium text-gray-600 mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            Completed Achievements
                            <button
                                v-if="getProcessedAchievements(items).unlocked.length > 1"
                                @click="toggleStack(type)"
                                class="ml-auto text-xs px-2 py-1 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors"
                            >
                                {{ expandedTypes.has(type) ? 'Stack' : 'Expand' }}
                            </button>
                        </h3>

                        <div
                            class="relative"
                            :style="{
                                height: expandedTypes.has(type)
                                    ? `${getProcessedAchievements(items).unlocked.length * 120}px`
                                    : '200px',
                            }"
                        >
                            <div
                                v-for="(achievement, index) in getProcessedAchievements(items).unlocked"
                                :key="achievement.id"
                                :style="
                                    getStackStyle(
                                        index,
                                        getProcessedAchievements(items).unlocked.length,
                                        expandedTypes.has(type)
                                    )
                                "
                                class="absolute top-0 left-0 right-0 transition-all duration-500 ease-out"
                            >
                                <div
                                    :class="[
                                        'relative p-5 border-2 rounded-xl cursor-pointer transform transition-all duration-300 hover:scale-105',
                                        getCardClasses(achievement),
                                    ]"
                                    @click="index === 0 && !expandedTypes.has(type) ? toggleStack(type) : null"
                                >
                                    <!-- Shine effect -->
                                    <div
                                        class="absolute inset-0 rounded-xl bg-gradient-to-tr from-transparent via-white to-transparent opacity-0 hover:opacity-20 transition-opacity duration-300"
                                    ></div>

                                    <!-- Achievement Content -->
                                    <div class="relative">
                                        <div class="flex items-start justify-between mb-3">
                                            <div class="flex-1">
                                                <h3 class="font-semibold text-lg">
                                                    {{ getAchievementTitle(achievement) }}
                                                </h3>
                                                <p
                                                    v-if="achievement.metadata?.description"
                                                    class="text-sm text-gray-600 mt-1"
                                                >
                                                    {{ achievement.metadata.description }}
                                                </p>
                                            </div>

                                            <!-- Status Icon -->
                                            <div class="ml-3">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center animate-pulse"
                                                >
                                                    <svg
                                                        class="w-5 h-5 text-white"
                                                        fill="currentColor"
                                                        viewBox="0 0 20 20"
                                                    >
                                                        <path
                                                            fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Completion Badge -->
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs text-gray-500">
                                                Completed at {{ achievement.threshold }}
                                                {{ achievement.tag_name || achievement.type }}
                                            </span>
                                            <span class="text-xs font-medium text-green-600"> ✨ Earned </span>
                                        </div>
                                    </div>

                                    <!-- Stack indicator -->
                                    <div
                                        v-if="
                                            index === 0 &&
                                            getProcessedAchievements(items).unlocked.length > 1 &&
                                            !expandedTypes.has(type)
                                        "
                                        class="absolute -bottom-2 right-4 bg-gray-700 text-white text-xs px-2 py-1 rounded-full"
                                    >
                                        +{{ getProcessedAchievements(items).unlocked.length - 1 }} more
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Achievements -->
                    <div v-if="getProcessedAchievements(items).locked.length > 0">
                        <h3 class="text-sm font-medium text-gray-600 mb-4 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                />
                            </svg>
                            Next Goals
                        </h3>

                        <div class="space-y-4">
                            <div
                                v-for="achievement in getProcessedAchievements(items).locked"
                                :key="achievement.id"
                                :class="[
                                    'relative p-5 border-2 rounded-xl transition-all duration-300 hover:scale-105 hover:shadow-xl',
                                    getCardClasses(achievement),
                                ]"
                            >
                                <!-- Progress overlay -->
                                <div
                                    class="absolute inset-0 rounded-xl bg-gradient-to-r opacity-10"
                                    :class="{
                                        'from-yellow-400 to-yellow-600': achievement.percentage >= 75,
                                        'from-blue-400 to-blue-600':
                                            achievement.percentage >= 50 && achievement.percentage < 75,
                                        'from-gray-400 to-gray-600': achievement.percentage < 50,
                                    }"
                                    :style="`width: ${achievement.percentage}%`"
                                ></div>

                                <!-- Achievement Content -->
                                <div class="relative">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-lg">
                                                {{ getAchievementTitle(achievement) }}
                                            </h3>
                                            <p
                                                v-if="achievement.metadata?.description"
                                                class="text-sm text-gray-600 mt-1"
                                            >
                                                {{ achievement.metadata.description }}
                                            </p>
                                        </div>

                                        <!-- Lock Icon -->
                                        <div class="ml-3">
                                            <div
                                                class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center"
                                            >
                                                <svg
                                                    class="w-5 h-5 text-gray-500"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                                    />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div>
                                        <div class="flex justify-between text-sm mb-2">
                                            <span class="font-medium">Progress</span>
                                            <span class="text-gray-600"
                                                >{{ achievement.progress }} / {{ achievement.threshold }}</span
                                            >
                                        </div>
                                        <div class="h-3 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                class="h-full transition-all duration-500 rounded-full"
                                                :class="{
                                                    'bg-gradient-to-r from-yellow-400 to-yellow-500':
                                                        achievement.percentage >= 75,
                                                    'bg-gradient-to-r from-blue-400 to-blue-500':
                                                        achievement.percentage >= 50 && achievement.percentage < 75,
                                                    'bg-gradient-to-r from-gray-400 to-gray-500':
                                                        achievement.percentage < 50,
                                                }"
                                                :style="`width: ${achievement.percentage}%`"
                                            ></div>
                                        </div>
                                        <div
                                            v-if="achievement.threshold > achievement.progress"
                                            class="text-xs text-gray-500 mt-2 font-medium"
                                        >
                                            🎯 {{ achievement.threshold - achievement.progress }} more to unlock
                                        </div>
                                    </div>
                                </div>

                                <!-- Next Badge -->
                                <div class="absolute -top-3 -right-3 animate-bounce">
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-lg"
                                    >
                                        Next Goal
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="Object.keys(achievements).length === 0" class="text-center py-12 text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                    />
                </svg>
                <p class="text-lg font-medium">Get started!</p>
                <p>Start uploading photos to unlock achievements.</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add some custom animations */
@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}

.hover\:scale-105:hover {
    transform: scale(1.05);
}
</style>
