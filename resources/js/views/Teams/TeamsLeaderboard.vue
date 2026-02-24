<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-800 mb-6">Teams Leaderboard</h1>

        <p v-if="loading" class="text-slate-500">Loading leaderboard...</p>

        <div v-else class="bg-white rounded-xl shadow-sm overflow-x-auto">
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
                        v-for="(team, i) in leaderboard"
                        :key="team.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="px-4 py-3 text-center font-medium"
                            :class="{
                                'text-yellow-500': i === 0,
                                'text-slate-400': i === 1,
                                'text-amber-600': i === 2,
                                'text-slate-500': i > 2,
                            }"
                        >
                            {{ i + 1 }}
                        </td>
                        <td class="px-4 py-3 font-medium">{{ team.name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ team.total_litter?.toLocaleString() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ team.total_images?.toLocaleString() }}</td>
                        <td class="px-4 py-3 text-right text-slate-500 text-xs">
                            {{ formatDate(team.created_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="!leaderboard.length" class="p-6 text-center text-slate-400">
                No teams on the leaderboard yet.
            </p>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useTeamsStore } from '@/stores/teams';

export default {
    name: 'TeamsLeaderboard',
    setup() {
        const teamsStore = useTeamsStore();
        const loading = ref(true);

        const leaderboard = computed(() => teamsStore.leaderboard);

        const formatDate = (date) => {
            return new Intl.DateTimeFormat('en-IE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            }).format(new Date(date));
        };

        onMounted(async () => {
            await teamsStore.fetchLeaderboard();
            loading.value = false;
        });

        return { loading, leaderboard, formatDate };
    },
};
</script>
