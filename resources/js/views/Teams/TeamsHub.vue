<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 relative overflow-hidden">
        <!-- Ambient backdrop -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 w-[350px] h-[350px] rounded-full bg-purple-500/[0.05] blur-3xl"></div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="relative flex items-center justify-center min-h-[60vh]">
            <div class="flex flex-col items-center gap-3">
                <div class="w-24 h-6 bg-white/10 rounded animate-pulse"></div>
                <div class="w-40 h-4 bg-white/10 rounded animate-pulse"></div>
            </div>
        </div>

        <!-- State 1: No teams -->
        <div v-else-if="!hasTeams" class="relative max-w-xl mx-auto py-16 px-4">
            <h1 class="text-3xl font-bold text-white mb-2">{{ $t('Teams') }}</h1>
            <p class="text-white/50 mb-8">
                {{ $t('Join a team to contribute together, or create your own.') }}
            </p>

            <!-- Facilitator onboarding banner -->
            <div
                v-if="showFacilitatorOnboarding"
                class="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-5 mb-6"
            >
                <h3 class="text-white font-semibold text-sm mb-1">{{ $t('Before you create your team, try OpenLitterMap yourself.') }}</h3>
                <p class="text-white/40 text-sm mb-3">
                    {{ $t('Go for a short walk, photograph some litter, upload it, and tag it. This takes about 30 minutes and you\'ll understand exactly what your students will do.') }}
                </p>
                <router-link
                    to="/upload"
                    class="inline-flex items-center gap-1 text-sm font-medium text-emerald-400 hover:text-emerald-300 transition-colors"
                >
                    {{ $t('Upload Your First Photos') }} &rarr;
                </router-link>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Join -->
                <div class="bg-white/5 border border-white/10 rounded-xl p-6">
                    <h2 class="text-lg font-semibold text-white mb-1">{{ $t('Join a Team') }}</h2>
                    <p class="text-sm text-white/40 mb-4">
                        {{ $t('Enter the team identifier shared by your team leader.') }}
                    </p>
                    <form class="space-y-3" @submit.prevent="submitJoin">
                        <input
                            v-model="joinIdentifier"
                            type="text"
                            required
                            :placeholder="$t('e.g. Awesome2026')"
                            class="w-full bg-white/5 border border-white/20 rounded-lg px-3 py-2 text-sm text-white placeholder-white/30 focus:border-emerald-500/50 focus:outline-none"
                            :class="joinErrors.identifier ? 'border-red-500/50' : ''"
                            @input="teamsStore.clearError('identifier')"
                        />
                        <p v-if="joinErrors.identifier" class="text-red-400 text-xs">
                            {{ joinErrors.identifier[0] }}
                        </p>
                        <button
                            type="submit"
                            :disabled="joinProcessing"
                            class="w-full py-2 rounded-lg font-medium text-sm transition-colors"
                            :class="joinProcessing
                                ? 'bg-white/10 text-white/50 cursor-not-allowed'
                                : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30'"
                        >
                            {{ joinProcessing ? $t('Joining...') : $t('Join Team') }}
                        </button>
                    </form>
                </div>

                <!-- Create -->
                <div class="bg-white/5 border border-white/10 rounded-xl p-6 flex flex-col">
                    <h2 class="text-lg font-semibold text-white mb-1">{{ $t('Create a Team') }}</h2>
                    <p class="text-sm text-white/40 mb-4 flex-1">
                        {{ $t('Start a new team for your school or community.') }}
                    </p>
                    <router-link
                        to="/teams/create"
                        class="block w-full py-2 rounded-lg bg-white/10 border border-white/10 text-white font-medium text-sm text-center hover:bg-white/20 transition-colors"
                    >
                        {{ $t('Create Team') }}
                    </router-link>
                </div>
            </div>
        </div>

        <!-- State 2: Has teams — sidebar + content -->
        <section v-else class="relative flex min-h-screen">
            <!-- Sidebar (desktop) -->
            <nav class="w-56 shrink-0 bg-white/5 border-r border-white/10 p-4 hidden md:flex md:flex-col overflow-y-auto">
                <!-- Team switcher -->
                <select
                    v-if="teams.length > 1"
                    v-model="selectedTeamId"
                    class="w-full mb-4 bg-white/5 border border-white/20 rounded-lg px-2 py-1.5 text-sm text-white focus:border-emerald-500/50 focus:outline-none"
                    @change="switchTeam"
                >
                    <option v-for="t in sortedSidebarTeams" :key="t.id" :value="t.id" class="bg-slate-800">{{ t.name }}</option>
                </select>

                <!-- Team name (single team) -->
                <p v-else class="text-white font-semibold text-sm mb-4 truncate">{{ team?.name }}</p>

                <!-- Nav groups -->
                <div class="space-y-0.5 flex-1">
                    <template v-for="group in navGroups" :key="group.id">
                        <!-- Top-level item (no children) -->
                        <button
                            v-if="!group.children"
                            class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left text-sm transition-colors"
                            :class="activeTab === group.id
                                ? 'bg-white/10 text-white'
                                : 'text-white/50 hover:bg-white/[0.08] hover:text-white/70'"
                            @click="setTab(group.id)"
                        >
                            <i :class="group.icon" class="w-4 text-center text-xs" />
                            <span>{{ group.label }}</span>
                            <span
                                v-if="group.badge"
                                class="ml-auto inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30"
                            >
                                {{ group.badge }}
                            </span>
                        </button>

                        <!-- Collapsible group -->
                        <div v-else>
                            <button
                                class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left text-sm transition-colors"
                                :class="isGroupActive(group)
                                    ? 'text-white'
                                    : 'text-white/50 hover:bg-white/[0.08] hover:text-white/70'"
                                @click="toggleGroup(group.id)"
                            >
                                <i :class="group.icon" class="w-4 text-center text-xs" />
                                <span>{{ group.label }}</span>
                                <svg
                                    class="w-3 h-3 ml-auto transition-transform"
                                    :class="expandedGroups[group.id] ? 'rotate-180' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div v-show="expandedGroups[group.id]" class="ml-4 mt-0.5 space-y-0.5">
                                <button
                                    v-for="child in group.children"
                                    :key="child.id"
                                    class="flex items-center gap-3 w-full px-3 py-1.5 rounded-lg text-left text-sm transition-colors"
                                    :class="activeTab === child.id
                                        ? 'bg-white/10 text-white'
                                        : 'text-white/40 hover:bg-white/[0.08] hover:text-white/60'"
                                    @click="setTab(child.id)"
                                >
                                    <i :class="child.icon" class="w-4 text-center text-xs" />
                                    <span>{{ child.label }}</span>
                                    <span
                                        v-if="child.badge"
                                        class="ml-auto inline-flex items-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-amber-500/20 text-amber-400 border border-amber-500/30"
                                    >
                                        {{ child.badge }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Bottom actions -->
                <div class="mt-4 pt-4 border-t border-white/10 space-y-0.5">
                    <router-link
                        to="/teams/create"
                        class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left text-sm text-white/50 hover:bg-white/[0.08] hover:text-white/70 transition-colors"
                    >
                        <i class="fa fa-plus w-4 text-center text-xs" />
                        <span>{{ $t('Create Team') }}</span>
                    </router-link>
                    <button
                        class="flex items-center gap-3 w-full px-3 py-2 rounded-lg text-left text-sm text-white/50 hover:bg-white/[0.08] hover:text-white/70 transition-colors"
                        @click="showJoinForm = !showJoinForm"
                    >
                        <i class="fa fa-sign-in w-4 text-center text-xs" />
                        <span>{{ $t('Join Team') }}</span>
                    </button>
                </div>
            </nav>

            <!-- Mobile nav (bottom bar) -->
            <div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 border-t border-white/10 px-2 py-2 backdrop-blur-sm">
                <div class="flex overflow-x-auto gap-1">
                    <button
                        v-for="item in flatNavItems"
                        :key="item.id"
                        class="relative shrink-0 flex flex-col items-center px-3 py-1.5 rounded-lg text-xs transition-colors"
                        :class="activeTab === item.id
                            ? 'bg-white/10 text-white'
                            : 'text-white/40'"
                        @click="setTab(item.id)"
                    >
                        <i :class="item.icon" class="text-sm mb-0.5" />
                        <span>{{ item.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Main content -->
            <main class="flex-1 overflow-y-auto md:pb-0 pb-16">
                <!-- Join form banner -->
                <div v-if="showJoinForm" class="bg-white/5 border-b border-white/10 px-6 py-3">
                    <div class="flex gap-2 max-w-md">
                        <input
                            v-model="joinIdentifier"
                            type="text"
                            :placeholder="$t('Team identifier...')"
                            class="flex-1 bg-white/5 border border-white/20 rounded-lg px-3 py-2 text-sm text-white placeholder-white/30 focus:border-emerald-500/50 focus:outline-none"
                            @input="teamsStore.clearError('identifier')"
                            @keyup.enter="submitJoin"
                        />
                        <button
                            :disabled="joinProcessing || !joinIdentifier"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                            :class="joinProcessing
                                ? 'bg-white/10 text-white/50'
                                : 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/30'"
                            @click="submitJoin"
                        >
                            {{ joinProcessing ? $t('Joining...') : $t('Join') }}
                        </button>
                        <button class="px-2 py-2 text-white/30 hover:text-white/50" @click="showJoinForm = false">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p v-if="joinErrors.identifier" class="text-red-400 text-xs mt-1">{{ joinErrors.identifier[0] }}</p>
                </div>

                <!-- Approval notification -->
                <div v-if="approvalNotice" class="mx-6 mt-4">
                    <div class="px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-sm flex items-center justify-between">
                        <span>{{ approvalNotice }}</span>
                        <button class="text-emerald-400/60 hover:text-emerald-400 ml-4" @click="approvalNotice = ''">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Header -->
                <div class="px-6 pt-6 pb-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-white truncate">{{ team?.name }}</h1>
                                <span
                                    class="shrink-0 inline-flex items-center px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider rounded-full"
                                    :class="isSchoolTeam
                                        ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                                        : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'"
                                >
                                    {{ isSchoolTeam ? $t('School') : $t('Community') }}
                                </span>
                            </div>
                            <p v-if="isSchoolTeam && (team?.class_group || team?.academic_year)" class="text-sm text-white/40 mt-0.5">
                                <span v-if="team?.class_group">{{ team.class_group }}</span>
                                <span v-if="team?.class_group && team?.academic_year"> — </span>
                                <span v-if="team?.academic_year">{{ team.academic_year }}</span>
                            </p>
                        </div>

                        <select
                            v-model="period"
                            class="bg-white/5 border border-white/20 rounded-lg px-3 py-2 text-sm text-white focus:border-emerald-500/50 focus:outline-none w-auto"
                            @change="loadDashboard"
                        >
                            <option value="all" class="bg-slate-800">{{ $t('All Time') }}</option>
                            <option value="today" class="bg-slate-800">{{ $t('Today') }}</option>
                            <option value="week" class="bg-slate-800">{{ $t('This Week') }}</option>
                            <option value="month" class="bg-slate-800">{{ $t('This Month') }}</option>
                            <option value="year" class="bg-slate-800">{{ $t('This Year') }}</option>
                        </select>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                        <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Photos') }}</div>
                            <div class="text-white text-2xl font-bold tabular-nums tracking-tight">{{ dashboard.photos_count }}</div>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Litter Tagged') }}</div>
                            <div class="text-white text-2xl font-bold tabular-nums tracking-tight">{{ dashboard.litter_count }}</div>
                        </div>
                        <div class="bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">{{ $t('Members') }}</div>
                            <div class="text-white text-2xl font-bold tabular-nums tracking-tight">{{ dashboard.members_count }}</div>
                        </div>
                        <div
                            class="bg-white/5 border rounded-xl px-5 py-4"
                            :class="isSchoolTeam && photoStats.pending > 0
                                ? 'border-amber-500/30 cursor-pointer hover:bg-amber-500/5 transition-colors'
                                : 'border-white/10'"
                            @click="isSchoolTeam && photoStats.pending > 0 && setTab('approval')"
                        >
                            <div class="text-white/50 text-[11px] font-semibold uppercase tracking-widest mb-1">
                                {{ isSchoolTeam ? $t('Pending') : $t('Approved') }}
                            </div>
                            <div
                                class="text-2xl font-bold tabular-nums tracking-tight"
                                :class="isSchoolTeam && photoStats.pending > 0 ? 'text-amber-400' : 'text-emerald-400'"
                            >
                                {{ isSchoolTeam ? photoStats.pending : photoStats.approved }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab content -->
                <div v-if="activeTab !== 'approval'" class="px-6 pb-6">
                    <component :is="currentTabComponent" v-bind="tabProps" @switch-team="handleSwitchTeam" @switch-tab="setTab" />
                </div>

                <!-- Approval queue renders full-width -->
                <component
                    v-if="activeTab === 'approval'"
                    :is="currentTabComponent"
                    v-bind="tabProps"
                />
            </main>
        </section>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, markRaw } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'vue-toastification';
import { useTeamsStore } from '@/stores/teams';
import { useTeamPhotosStore } from '@/stores/teamPhotos';
import { useUserStore } from '@/stores/user';
import TeamOverview from './TeamOverview.vue';
import TeamPhotoList from './TeamPhotoList.vue';
import TeamPhotoMap from './TeamPhotoMap.vue';
import TeamSettingsTab from './TeamSettingsTab.vue';
import TeamsLeaderboard from './TeamsLeaderboard.vue';
import FacilitatorQueue from './FacilitatorQueue.vue';
import TeamMembersList from './components/TeamMembersList.vue';
import ParticipantGrid from './components/ParticipantGrid.vue';

const router = useRouter();
const route = useRoute();
const toast = useToast();
const teamsStore = useTeamsStore();
const photosStore = useTeamPhotosStore();
const userStore = useUserStore();

const loading = ref(true);
const selectedTeamId = ref(null);
const period = ref('all');
const activeTab = ref('overview');
const approvalNotice = ref('');
const showJoinForm = ref(false);
const joinIdentifier = ref('');
const joinProcessing = ref(false);
const expandedGroups = reactive({});
let echoChannel = null;

const teams = computed(() => teamsStore.teams);
const hasTeams = computed(() => teamsStore.hasTeams);
const team = computed(() => teams.value.find((t) => t.id === selectedTeamId.value));

// Active team first in sidebar dropdown, rest sorted by total photos desc
const sortedSidebarTeams = computed(() => {
    const active = teams.value.filter((t) => t.id === selectedTeamId.value);
    const rest = teams.value
        .filter((t) => t.id !== selectedTeamId.value)
        .sort((a, b) => (b.total_photos || 0) - (a.total_photos || 0));
    return [...active, ...rest];
});
const dashboard = computed(() => teamsStore.dashboard);
const photoStats = computed(() => photosStore.stats);
const isSchoolTeam = computed(() => team.value?.type_name === 'school');
const isLeader = computed(() => team.value?.leader === userStore.user?.id);
const isSchoolManager = computed(() => {
    const roles = userStore.user?.roles || [];
    return roles.some((r) => r.name === 'school_manager');
});
const joinErrors = computed(() => teamsStore.errors);

// Show "try OLM yourself first" banner for school managers who haven't uploaded yet
const showFacilitatorOnboarding = computed(() => {
    if (!isSchoolManager.value) return false;
    return (userStore.user?.total_images ?? 0) === 0;
});

// ── Tab query param sync ──

const setTab = (tab) => {
    activeTab.value = tab;
    router.replace({ query: { ...route.query, tab } });
};

// ── Navigation structure ──

const navGroups = computed(() => {
    const groups = [
        { id: 'overview', label: 'Overview', icon: 'fa fa-home' },
    ];

    const teamChildren = [
        { id: 'photos', label: 'Photos', icon: 'fa fa-camera' },
        { id: 'map', label: 'Map', icon: 'fa fa-map' },
        { id: 'members', label: 'Members', icon: 'fa fa-users' },
    ];

    groups.push({
        id: 'team-group',
        label: 'Team',
        icon: 'fa fa-th-large',
        children: teamChildren,
    });

    if (isSchoolTeam.value && isLeader.value) {
        const schoolChildren = [
            {
                id: 'approval',
                label: 'Approval Queue',
                icon: 'fa fa-check-circle',
                badge: photoStats.value.pending > 0 ? photoStats.value.pending : null,
            },
        ];

        if (team.value?.participant_sessions_enabled) {
            schoolChildren.push({
                id: 'participants',
                label: 'Participants',
                icon: 'fa fa-id-badge',
            });
        }

        groups.push({
            id: 'school-group',
            label: 'School',
            icon: 'fa fa-graduation-cap',
            children: schoolChildren,
        });
    }

    if (isLeader.value || isSchoolManager.value) {
        groups.push({ id: 'settings', label: 'Settings', icon: 'fa fa-gear' });
    }

    groups.push({ id: 'leaderboard', label: 'Leaderboard', icon: 'fa fa-trophy' });

    return groups;
});

const flatNavItems = computed(() => {
    const items = [];
    for (const group of navGroups.value) {
        if (group.children) {
            for (const child of group.children) {
                items.push(child);
            }
        } else {
            items.push(group);
        }
    }
    return items;
});

const isGroupActive = (group) => {
    return group.children?.some((c) => c.id === activeTab.value);
};

const toggleGroup = (groupId) => {
    expandedGroups[groupId] = !expandedGroups[groupId];
};

watch(activeTab, (tab) => {
    for (const group of navGroups.value) {
        if (group.children?.some((c) => c.id === tab)) {
            expandedGroups[group.id] = true;
        }
    }
}, { immediate: true });

// ── Tab components ──

const tabComponents = {
    overview: markRaw(TeamOverview),
    photos: markRaw(TeamPhotoList),
    map: markRaw(TeamPhotoMap),
    approval: markRaw(FacilitatorQueue),
    members: markRaw(TeamMembersList),
    participants: markRaw(ParticipantGrid),
    settings: markRaw(TeamSettingsTab),
    leaderboard: markRaw(TeamsLeaderboard),
};

const currentTabComponent = computed(() => tabComponents[activeTab.value] || tabComponents.overview);

const tabProps = computed(() => ({
    teamId: selectedTeamId.value,
    team: team.value,
    isLeader: isLeader.value,
    isSchoolTeam: isSchoolTeam.value,
    teams: teams.value,
}));

// ── Data loading ──

const loadDashboard = () => {
    if (selectedTeamId.value) {
        teamsStore.fetchDashboard({
            teamId: selectedTeamId.value,
            period: period.value,
        });
    }
};

const switchTeam = () => {
    teamsStore.setActiveTeam(selectedTeamId.value);
    loadDashboard();
    photosStore.fetchPhotos(selectedTeamId.value);
    subscribeToTeamChannel(selectedTeamId.value);
    setTab('overview');
};

const handleSwitchTeam = (teamId) => {
    selectedTeamId.value = teamId;
    switchTeam();
};

const submitJoin = async () => {
    if (!joinIdentifier.value) return;

    joinProcessing.value = true;
    const joined = await teamsStore.joinTeam(joinIdentifier.value);
    joinProcessing.value = false;

    if (joined) {
        toast.success(`Joined "${joined.name}" successfully!`);
        joinIdentifier.value = '';
        showJoinForm.value = false;
        selectedTeamId.value = joined.id;
        loadDashboard();
        photosStore.fetchPhotos(joined.id);
        subscribeToTeamChannel(joined.id);
    }
};

// ── Real-time ──

const subscribeToTeamChannel = (teamId) => {
    if (echoChannel) {
        window.Echo?.leave(`team.${echoChannel}`);
    }
    if (!teamId || !window.Echo) return;
    echoChannel = teamId;

    window.Echo.private(`team.${teamId}`).listen('.school.data.approved', (event) => {
        const count = event.photo_count || 0;
        approvalNotice.value = `${count} photo${count !== 1 ? 's' : ''} approved and published to the global map.`;
        loadDashboard();
        photosStore.fetchPhotos(teamId, photosStore.photos.current_page);
        setTimeout(() => { approvalNotice.value = ''; }, 8000);
    });
};

watch(activeTab, (tab) => {
    if (tab === 'map' && selectedTeamId.value) {
        photosStore.fetchMapPoints(selectedTeamId.value);
    }
    if (tab === 'members' && selectedTeamId.value) {
        photosStore.fetchMemberStats(selectedTeamId.value);
    }
});

// ── Lifecycle ──

onMounted(async () => {
    await Promise.all([
        teamsStore.fetchTeamTypes(),
        teamsStore.hasTeams ? Promise.resolve() : teamsStore.fetchMyTeams(),
    ]);

    selectedTeamId.value = teamsStore.activeTeamId || teams.value[0]?.id;

    if (selectedTeamId.value) {
        loadDashboard();
        photosStore.fetchPhotos(selectedTeamId.value);
        subscribeToTeamChannel(selectedTeamId.value);
    }

    // Restore tab from query param
    const queryTab = route.query.tab;
    if (queryTab && tabComponents[queryTab]) {
        activeTab.value = queryTab;
    }

    // Post-creation toast
    if (route.query.created === 'true') {
        toast.success("Team created! Here's how to get started.");
        router.replace({ query: { ...route.query, created: undefined } });
    }

    expandedGroups['team-group'] = true;
    loading.value = false;
});

onUnmounted(() => {
    if (echoChannel && window.Echo) {
        window.Echo.leave(`team.${echoChannel}`);
        echoChannel = null;
    }
});
</script>
