<template>
    <div>
        <h1 class="text-2xl font-bold text-white mb-6">Teams Leaderboard</h1>

        <p v-if="loading" class="text-white/60">Loading leaderboard...</p>

        <template v-else>
            <div class="bg-white/5 border border-white/10 rounded-xl overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-white/10 text-white/60 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium text-center w-16">#</th>
                            <th class="px-4 py-3 font-medium">Team</th>
                            <th class="px-4 py-3 font-medium text-right">Litter</th>
                            <th class="px-4 py-3 font-medium text-right">Photos</th>
                            <th class="px-4 py-3 font-medium text-right">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr
                            v-for="(team, i) in teams"
                            :key="team.id"
                            class="hover:bg-white/[0.03]"
                        >
                            <td class="px-4 py-3 text-center font-medium"
                                :class="{
                                    'text-yellow-500': rank(i) === 1,
                                    'text-slate-400': rank(i) === 2,
                                    'text-amber-600': rank(i) === 3,
                                    'text-slate-500': rank(i) > 3,
                                }"
                            >
                                {{ rank(i) }}
                            </td>
                            <td class="px-4 py-3 font-medium text-white">{{ team.name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/70">{{ team.total_tags?.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-white/70">{{ team.total_photos?.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-white/50 text-xs">
                                {{ formatDate(team.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!teams.length" class="p-6 text-center text-white/50">
                    No teams on the leaderboard yet.
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1" class="flex items-center justify-between mt-4">
                <p class="text-sm text-white/50">
                    {{ total }} {{ total === 1 ? 'team' : 'teams' }}
                </p>
                <div class="flex gap-1">
                    <button
                        v-for="p in lastPage"
                        :key="p"
                        class="px-3 py-1 rounded text-sm font-medium transition-colors"
                        :class="p === currentPage
                            ? 'bg-emerald-500 text-white'
                            : 'bg-white/5 text-white/60 hover:bg-white/10 border border-white/10'"
                        @click="goToPage(p)"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useTeamsStore } from '@/stores/teams';

const teamsStore = useTeamsStore();
const loading = ref(true);

const teams = computed(() => teamsStore.leaderboard.data);
const currentPage = computed(() => teamsStore.leaderboard.current_page);
const lastPage = computed(() => teamsStore.leaderboard.last_page);
const total = computed(() => teamsStore.leaderboard.total);

const rank = (index) => (currentPage.value - 1) * 25 + index + 1;

const formatDate = (date) => {
    return new Intl.DateTimeFormat('en-IE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(new Date(date));
};

const goToPage = async (page) => {
    loading.value = true;
    await teamsStore.fetchLeaderboard(page);
    loading.value = false;
};

onMounted(async () => {
    await teamsStore.fetchLeaderboard();
    loading.value = false;
});
</script>
