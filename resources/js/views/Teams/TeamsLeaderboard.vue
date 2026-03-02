<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Teams Leaderboard</h1>

        <p v-if="loading" class="text-slate-500">Loading leaderboard...</p>

        <template v-else>
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium text-center w-16">#</th>
                            <th class="px-4 py-3 font-medium">Team</th>
                            <th class="px-4 py-3 font-medium text-right">Litter</th>
                            <th class="px-4 py-3 font-medium text-right">Photos</th>
                            <th class="px-4 py-3 font-medium text-right">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="(team, i) in teams"
                            :key="team.id"
                            class="hover:bg-slate-50"
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
                            <td class="px-4 py-3 font-medium">{{ team.name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ team.total_tags?.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ team.total_images?.toLocaleString() }}</td>
                            <td class="px-4 py-3 text-right text-slate-500 text-xs">
                                {{ formatDate(team.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p v-if="!teams.length" class="p-6 text-center text-slate-400">
                    No teams on the leaderboard yet.
                </p>
            </div>

            <!-- Pagination -->
            <div v-if="lastPage > 1" class="flex items-center justify-between mt-4">
                <p class="text-sm text-slate-500">
                    {{ total }} {{ total === 1 ? 'team' : 'teams' }}
                </p>
                <div class="flex gap-1">
                    <button
                        v-for="p in lastPage"
                        :key="p"
                        class="px-3 py-1 rounded text-sm font-medium transition-colors"
                        :class="p === currentPage
                            ? 'bg-blue-600 text-white'
                            : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'"
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
