<template>
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">{{ team?.name }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    {{ team?.type_name === 'school' ? 'School Team' : 'Community Team' }}
                    <span v-if="team?.class_group"> — {{ team.class_group }}</span>
                    <span v-if="team?.academic_year"> ({{ team.academic_year }})</span>
                </p>
            </div>

            <div class="flex gap-2 mt-4 sm:mt-0">
                <select
                    v-model="selectedTeamId"
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    @change="switchTeam"
                >
                    <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>

                <select
                    v-model="period"
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                    @change="loadDashboard"
                >
                    <option value="today">Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-sm text-slate-500">Photos</p>
                <p class="text-2xl font-bold text-slate-800">{{ dashboard.photos_count }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-sm text-slate-500">Litter Tagged</p>
                <p class="text-2xl font-bold text-slate-800">{{ dashboard.litter_count }}</p>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-sm text-slate-500">Active Members</p>
                <p class="text-2xl font-bold text-slate-800">{{ dashboard.members_count }}</p>
            </div>
            <div v-if="isSchoolTeam" class="bg-white rounded-xl p-4 shadow-sm">
                <p class="text-sm text-slate-500">Pending Approval</p>
                <p class="text-2xl font-bold" :class="photoStats.pending > 0 ? 'text-amber-600' : 'text-green-600'">
                    {{ photoStats.pending }}
                </p>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="flex border-b border-slate-200 mb-6">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
                :class="activeTab === tab.id
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-slate-500 hover:text-slate-700'"
                @click="activeTab = tab.id"
            >
                {{ tab.label }}
                <span
                    v-if="tab.badge"
                    class="ml-1.5 inline-flex items-center px-1.5 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700"
                >
                    {{ tab.badge }}
                </span>
            </button>
        </div>

        <!-- Tab content -->
        <component :is="currentTabComponent" v-bind="tabProps" />
    </div>
</template>

<script>
import { ref, computed, onMounted, watch, markRaw } from 'vue';
import { useTeamsStore } from '@/stores/teams';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import { useUserStore } from '@/stores/user';
import TeamPhotoList from './TeamPhotoList.vue';
import TeamPhotoMap from './TeamPhotoMap.vue';
import TeamApprovalQueue from './TeamApprovalQueue.vue';

export default {
    name: 'TeamDashboard',
    setup() {
        const teamsStore = useTeamsStore();
        const photosStore = useTeamPhotosStore();
        const userStore = useUserStore();

        const selectedTeamId = ref(null);
        const period = ref('all');
        const activeTab = ref('photos');

        const teams = computed(() => teamsStore.teams);
        const team = computed(() => teams.value.find((t) => t.id === selectedTeamId.value));
        const dashboard = computed(() => teamsStore.dashboard);
        const photoStats = computed(() => photosStore.stats);
        const isSchoolTeam = computed(() => team.value?.type_name === 'school');
        const isLeader = computed(() => team.value?.leader === userStore.user?.id);

        const tabs = computed(() => {
            const list = [
                { id: 'photos', label: 'Photos', badge: null },
                { id: 'map', label: 'Map', badge: null },
            ];

            if (isSchoolTeam.value && isLeader.value) {
                list.splice(1, 0, {
                    id: 'approval',
                    label: 'Approval Queue',
                    badge: photoStats.value.pending > 0 ? photoStats.value.pending : null,
                });
            }

            return list;
        });

        const tabComponents = {
            photos: markRaw(TeamPhotoList),
            map: markRaw(TeamPhotoMap),
            approval: markRaw(TeamApprovalQueue),
        };

        const currentTabComponent = computed(() => tabComponents[activeTab.value] || tabComponents.photos);

        const tabProps = computed(() => ({
            teamId: selectedTeamId.value,
            isLeader: isLeader.value,
            isSchoolTeam: isSchoolTeam.value,
        }));

        const loadDashboard = () => {
            if (selectedTeamId.value) {
                teamsStore.fetchDashboard({
                    teamId: selectedTeamId.value,
                    period: period.value,
                });
            }
        };

        const switchTeam = () => {
            loadDashboard();
            photosStore.fetchPhotos(selectedTeamId.value);
        };

        onMounted(async () => {
            if (!teamsStore.hasTeams) await teamsStore.fetchMyTeams();

            selectedTeamId.value = teamsStore.activeTeamId || teams.value[0]?.id;

            if (selectedTeamId.value) {
                loadDashboard();
                photosStore.fetchPhotos(selectedTeamId.value);
            }
        });

        watch(activeTab, (tab) => {
            if (tab === 'map' && selectedTeamId.value) {
                photosStore.fetchMapPoints(selectedTeamId.value);
            }
            if (tab === 'approval' && selectedTeamId.value) {
                photosStore.setFilter('pending');
                photosStore.fetchPhotos(selectedTeamId.value);
            }
        });

        return {
            teams, team, dashboard, photoStats, isSchoolTeam, isLeader,
            selectedTeamId, period, activeTab, tabs,
            currentTabComponent, tabProps,
            loadDashboard, switchTeam,
        };
    },
};
</script>
