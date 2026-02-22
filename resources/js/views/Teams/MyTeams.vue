<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-800 mb-4">My Teams</h1>

        <!-- Active team status -->
        <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-white rounded-xl p-4 shadow-sm mb-6"
        >
            <div>
                <p v-if="activeTeam" class="text-slate-700">
                    Contributing to <strong>{{ activeTeam.name }}</strong
                    >. All uploads will be attributed to this team.
                </p>
                <p v-else-if="hasTeams" class="text-amber-600">
                    No active team — uploads won't be attributed to any team.
                </p>
                <p v-else class="text-slate-500">You haven't joined any teams yet.</p>
            </div>

            <button
                v-if="activeTeam"
                class="mt-3 sm:mt-0 shrink-0 px-4 py-2 text-sm font-medium rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                @click="deactivate"
            >
                Deactivate
            </button>
        </div>

        <template v-if="hasTeams">
            <!-- Team members -->
            <div class="mb-8">
                <div class="flex items-center gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-slate-700">Team Members</h2>
                    <select
                        v-model="viewTeamId"
                        class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                        @change="loadMembers"
                    >
                        <option v-for="team in teams" :key="team.id" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>
                </div>

                <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium text-center w-16">#</th>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Username</th>
                                <th class="px-4 py-3 font-medium text-center">Status</th>
                                <th class="px-4 py-3 font-medium text-right">Photos</th>
                                <th class="px-4 py-3 font-medium text-right">Litter</th>
                                <th class="px-4 py-3 font-medium text-right">Last active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(member, i) in members.data" :key="member.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-center text-slate-500">
                                    {{ rank(i) }}
                                </td>
                                <td class="px-4 py-3">{{ member.name || '—' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ member.username || '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full"
                                        :class="
                                            member.active_team === viewTeamId
                                                ? 'bg-green-100 text-green-700'
                                                : 'bg-slate-100 text-slate-500'
                                        "
                                    >
                                        {{ member.active_team === viewTeamId ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ member.pivot.total_photos }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ member.pivot.total_litter }}</td>
                                <td class="px-4 py-3 text-right text-slate-500 text-xs">
                                    {{ member.pivot.updated_at ? formatDate(member.pivot.updated_at) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="members.last_page > 1" class="flex justify-center gap-3 mt-4">
                    <button
                        :disabled="members.current_page <= 1"
                        class="px-4 py-2 text-sm rounded-lg border border-slate-300 bg-white disabled:opacity-40"
                        @click="changePage(members.current_page - 1)"
                    >
                        Previous
                    </button>
                    <span class="px-3 py-2 text-sm text-slate-500">
                        {{ members.current_page }} / {{ members.last_page }}
                    </span>
                    <button
                        :disabled="members.current_page >= members.last_page"
                        class="px-4 py-2 text-sm rounded-lg border border-slate-300 bg-white disabled:opacity-40"
                        @click="changePage(members.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>

            <!-- All my teams table -->
            <div>
                <h2 class="text-lg font-semibold text-slate-700 mb-4">All My Teams</h2>

                <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Identifier</th>
                                <th class="px-4 py-3 font-medium text-right">Members</th>
                                <th class="px-4 py-3 font-medium text-right">Photos</th>
                                <th class="px-4 py-3 font-medium text-right">Litter</th>
                                <th class="px-4 py-3 font-medium text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="team in teams"
                                :key="team.id"
                                :class="team.id === activeTeamId ? 'bg-blue-50/50' : ''"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 font-medium">{{ team.name }}</td>
                                <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ team.identifier }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ team.members }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ team.total_images }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ team.total_litter }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-center gap-1">
                                        <!-- Set active -->
                                        <button
                                            :disabled="team.id === activeTeamId"
                                            class="p-1.5 rounded text-blue-600 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed"
                                            :title="
                                                team.id === activeTeamId ? 'Currently active' : 'Set as active team'
                                            "
                                            @click="activate(team.id)"
                                        >
                                            <i class="fa fa-star" />
                                        </button>

                                        <!-- Download -->
                                        <button
                                            class="p-1.5 rounded text-slate-600 hover:bg-slate-100"
                                            title="Download team data"
                                            @click="download(team.id)"
                                        >
                                            <i class="fa fa-download" />
                                        </button>

                                        <!-- Leave -->
                                        <button
                                            :disabled="team.members <= 1"
                                            class="p-1.5 rounded text-red-600 hover:bg-red-50 disabled:opacity-30 disabled:cursor-not-allowed"
                                            :title="team.members > 1 ? 'Leave team' : 'You are the only member'"
                                            @click="leave(team.id)"
                                        >
                                            <i class="fa fa-sign-out" />
                                        </button>

                                        <!-- Toggle visibility (leader only) -->
                                        <button
                                            v-if="team.leader === userId"
                                            class="p-1.5 rounded text-amber-600 hover:bg-amber-50"
                                            :title="
                                                team.leaderboards ? 'Hide from leaderboards' : 'Show on leaderboards'
                                            "
                                            @click="teamsStore.toggleLeaderboardVisibility(team.id)"
                                        >
                                            <i class="fa" :class="team.leaderboards ? 'fa-eye-slash' : 'fa-eye'" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useTeamsStore } from '@/stores/teams';
import { useUserStore } from '@/stores/user';

export default {
    name: 'MyTeams',
    setup() {
        const teamsStore = useTeamsStore();
        const userStore = useUserStore();

        const viewTeamId = ref(null);

        const teams = computed(() => teamsStore.teams);
        const hasTeams = computed(() => teamsStore.hasTeams);
        const members = computed(() => teamsStore.members);
        const activeTeam = computed(() => teamsStore.activeTeam);
        const activeTeamId = computed(() => teamsStore.activeTeamId);
        const userId = computed(() => userStore.user?.id);

        const loadMembers = () => {
            if (viewTeamId.value) {
                teamsStore.fetchMembers(viewTeamId.value);
            }
        };

        const changePage = (page) => {
            teamsStore.fetchMembers(viewTeamId.value, page);
        };

        const rank = (index) => {
            return index + 1 + (members.value.current_page - 1) * 10;
        };

        const activate = (teamId) => teamsStore.setActiveTeam(teamId);

        const deactivate = async () => {
            await teamsStore.clearActiveTeam();
            loadMembers();
        };

        const leave = async (teamId) => {
            if (!confirm('Are you sure you want to leave this team?')) return;
            await teamsStore.leaveTeam(teamId);

            // Reset viewed team if we left it
            if (viewTeamId.value === teamId) {
                viewTeamId.value = teams.value[0]?.id ?? null;
                loadMembers();
            }
        };

        const download = (teamId) => teamsStore.downloadTeamData(teamId);

        const formatDate = (date) => {
            return new Intl.DateTimeFormat('en-IE', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            }).format(new Date(date));
        };

        onMounted(async () => {
            if (!hasTeams.value) await teamsStore.fetchMyTeams();

            viewTeamId.value = activeTeamId.value || teams.value[0]?.id;

            if (viewTeamId.value) loadMembers();
        });

        return {
            teams,
            hasTeams,
            members,
            activeTeam,
            activeTeamId,
            userId,
            viewTeamId,
            loadMembers,
            changePage,
            rank,
            activate,
            deactivate,
            leave,
            download,
            formatDate,
            teamsStore,
        };
    },
};
</script>
