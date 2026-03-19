<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 relative overflow-hidden">
        <!-- Ambient background blobs -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl"></div>
            <div
                class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl"
            ></div>
            <div
                class="absolute bottom-0 left-1/3 w-[350px] h-[350px] rounded-full bg-purple-500/[0.05] blur-3xl"
            ></div>
        </div>

        <div class="relative container mx-auto px-4 py-8 max-w-4xl">
            <!-- Header -->
            <h1 class="text-white text-2xl font-bold mb-6">{{ t('Leaderboard') }}</h1>

            <!-- Stats Bar -->
            <div :class="userStore.auth ? 'grid-cols-3' : 'grid-cols-2'" class="grid gap-4 mb-6">
                <div v-if="userStore.auth" class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">
                        {{ t('Your Rank') }}
                    </div>
                    <div v-if="leaderboardStore.loading" class="inline-block w-16 h-7 bg-white/10 rounded animate-pulse"></div>
                    <div v-else class="text-white text-2xl font-bold tabular-nums tracking-tight">
                        {{ leaderboardStore.currentUserRank ? getPosition(leaderboardStore.currentUserRank) : '—' }}
                    </div>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">
                        {{ t('Active Users') }}
                    </div>
                    <div v-if="leaderboardStore.loading" class="inline-block w-16 h-7 bg-white/10 rounded animate-pulse"></div>
                    <div v-else class="text-white text-2xl font-bold tabular-nums tracking-tight">
                        {{ leaderboardStore.activeUsers.toLocaleString() }}
                    </div>
                </div>
                <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                    <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">
                        {{ t('Total Users') }}
                    </div>
                    <div v-if="leaderboardStore.loading" class="inline-block w-16 h-7 bg-white/10 rounded animate-pulse"></div>
                    <div v-else class="text-white text-2xl font-bold tabular-nums tracking-tight">
                        {{ leaderboardStore.totalUsers.toLocaleString() }}
                    </div>
                </div>
            </div>

                <!-- Filters -->
                <LeaderboardFilters class="mb-6" @change="onFilterChange" />

                <!-- Loading -->
                <div v-if="leaderboardStore.loading" class="flex justify-center items-center py-32">
                    <div class="animate-spin rounded-full h-10 w-10 border-2 border-white/20 border-t-emerald-400"></div>
                </div>

                <!-- Error -->
                <div v-else-if="leaderboardStore.error" class="text-center py-16">
                    <p class="text-red-300 text-lg">{{ leaderboardStore.error }}</p>
                    <button
                        @click="retry"
                        class="mt-4 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white transition"
                    >
                        Retry
                    </button>
                </div>

                <!-- Leaderboard List -->
                <template v-else>
                    <LeaderboardList :leaders="leaderboardStore.leaderboard" />

                    <!-- Pagination -->
                    <div
                        v-if="leaderboardStore.leaderboard.length"
                        class="flex justify-center items-center gap-3 mt-6"
                    >
                        <button
                            v-show="leaderboardStore.currentPage > 1"
                            class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm hover:bg-white/[0.08] transition"
                            @click="changePage(leaderboardStore.currentPage - 1)"
                        >
                            {{ t('Previous') }}
                        </button>
                        <span class="text-white/50 text-sm tabular-nums">
                            {{ t('Page') }} {{ leaderboardStore.currentPage }}
                        </span>
                        <button
                            v-show="leaderboardStore.hasNextPage"
                            class="px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm hover:bg-white/[0.08] transition"
                            @click="changePage(leaderboardStore.currentPage + 1)"
                        >
                            {{ t('Next') }}
                        </button>
                    </div>
                </template>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLeaderboardStore } from '../../../stores/leaderboard/index.js';
import { useUserStore } from '../../../stores/user/index.js';
import LeaderboardFilters from './components/LeaderboardFilters.vue';
import LeaderboardList from './components/LeaderboardList.vue';

const { t } = useI18n();
const leaderboardStore = useLeaderboardStore();
const userStore = useUserStore();

onMounted(async () => {
    await leaderboardStore.FETCH_LEADERBOARD({ timeFilter: 'all-time' });
});

const onFilterChange = async ({ timeFilter, locationType, locationId }) => {
    await leaderboardStore.FETCH_LEADERBOARD({ timeFilter, locationType, locationId });
};

const changePage = async (page) => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
    await leaderboardStore.FETCH_LEADERBOARD({
        ...leaderboardStore.currentFilters,
        page,
    });
};

const retry = async () => {
    await leaderboardStore.FETCH_LEADERBOARD(leaderboardStore.currentFilters);
};

const getPosition = (rank) => {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const value = rank % 100;
    return rank + (suffixes[(value - 20) % 10] || suffixes[value] || suffixes[0]);
};
</script>
