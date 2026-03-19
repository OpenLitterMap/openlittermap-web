<template>
    <section class="flex flex-col md:flex-row min-h-[calc(100vh-70px)]">
        <!-- Sidebar (desktop) -->
        <nav class="w-56 shrink-0 bg-slate-800 text-slate-300 p-6 hidden md:block">
            <h2 class="text-xl font-semibold mb-6">{{ $t('OpenLitterMap Teams') }}</h2>

            <button
                v-for="item in navItems"
                :key="item.key"
                class="flex items-center gap-3 w-full px-3 py-2 rounded-lg mb-1 text-left transition-colors"
                :class="activeView === item.key
                    ? 'bg-slate-700 text-white'
                    : 'hover:bg-slate-700/50'"
                @click="activeView = item.key"
            >
                <i :class="item.icon" class="w-5 text-center" />
                <span class="text-sm">{{ item.label }}</span>
            </button>
        </nav>

        <!-- Mobile nav -->
        <div class="md:hidden">
            <select
                v-model="activeView"
                class="w-full border-b border-slate-200 px-4 py-3 text-sm bg-slate-800 text-white"
            >
                <option
                    v-for="item in navItems"
                    :key="item.key"
                    :value="item.key"
                >
                    {{ item.label }}
                </option>
            </select>
        </div>

        <!-- Content -->
        <main class="flex-1 bg-slate-50 p-6 md:p-8 overflow-y-auto">
            <p v-if="loading" class="text-slate-500">{{ $t('Loading...') }}</p>

            <component v-else :is="currentComponent" @navigate="(view) => activeView = view" />
        </main>
    </section>
</template>

<script>
import { ref, computed, onMounted, markRaw } from 'vue';
import { useTeamsStore } from '@/stores/teams';
import TeamsDashboard from './TeamsDashboard.vue';
import CreateTeam from './CreateTeam.vue';
import JoinTeam from './JoinTeam.vue';
import MyTeams from './MyTeams.vue';
import TeamSettings from './TeamSettings.vue';
import TeamsLeaderboard from './TeamsLeaderboard.vue';

const componentMap = {
    dashboard: markRaw(TeamsDashboard),
    join: markRaw(JoinTeam),
    create: markRaw(CreateTeam),
    myteams: markRaw(MyTeams),
    leaderboard: markRaw(TeamsLeaderboard),
    settings: markRaw(TeamSettings),
};

export default {
    name: 'TeamsLayout',
    setup() {
        const teamsStore = useTeamsStore();
        const loading = ref(true);
        const activeView = ref('dashboard');

        const navItems = computed(() => [
            { key: 'dashboard', icon: 'fa fa-home', label: 'Dashboard' },
            { key: 'join', icon: 'fa fa-sign-in', label: 'Join a Team' },
            { key: 'create', icon: 'fa fa-plus', label: 'Create a Team' },
            { key: 'myteams', icon: 'fa fa-users', label: 'My Teams' },
            { key: 'leaderboard', icon: 'fa fa-trophy', label: 'Leaderboard' },
            { key: 'settings', icon: 'fa fa-gear', label: 'Settings' },
        ]);

        const currentComponent = computed(() => componentMap[activeView.value]);

        onMounted(async () => {
            await Promise.all([
                teamsStore.fetchTeamTypes(),
                teamsStore.hasTeams ? Promise.resolve() : teamsStore.fetchMyTeams(),
            ]);
            loading.value = false;
        });

        return { loading, activeView, navItems, currentComponent };
    },
};
</script>
