<template>
    <div>
        <h1 class="text-2xl font-bold text-slate-800 mb-6">{{ $t('Teams Dashboard') }}</h1>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl p-6 text-center shadow-sm">
                <span class="text-3xl font-bold text-slate-600">{{ dashboard.photos_count }}</span>
                <p class="text-sm text-slate-500 mt-1">{{ $t('Photos uploaded') }} {{ periodLabel }}</p>
            </div>
            <div class="bg-white rounded-xl p-6 text-center shadow-sm">
                <span class="text-3xl font-bold text-slate-600">{{ dashboard.litter_count }}</span>
                <p class="text-sm text-slate-500 mt-1">{{ $t('Litter tagged') }} {{ periodLabel }}</p>
            </div>
            <div class="bg-white rounded-xl p-6 text-center shadow-sm">
                <span class="text-3xl font-bold text-slate-600">{{ dashboard.members_count }}</span>
                <p class="text-sm text-slate-500 mt-1">{{ $t('Members active') }} {{ periodLabel }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-6">
            <select
                v-model="period"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                @change="refresh"
            >
                <option value="today">{{ $t('Today') }}</option>
                <option value="week">{{ $t('This week') }}</option>
                <option value="month">{{ $t('This month') }}</option>
                <option value="year">{{ $t('This year') }}</option>
                <option value="all">{{ $t('All time') }}</option>
            </select>

            <select
                v-model="viewTeamId"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white"
                @change="refresh"
            >
                <option :value="0">{{ $t('All teams') }}</option>
                <option v-for="team in teams" :key="team.id" :value="team.id">
                    {{ team.name }}
                </option>
            </select>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useTeamsStore } from '@/stores/teams';

export default {
    name: 'TeamsDashboard',
    setup() {
        const { t } = useI18n();
        const teamsStore = useTeamsStore();

        const period = ref('all');
        const viewTeamId = ref(0);

        const periodLabels = {
            today: t('today'),
            week: t('this week'),
            month: t('this month'),
            year: t('this year'),
            all: '',
        };

        const dashboard = computed(() => teamsStore.dashboard);
        const teams = computed(() => teamsStore.teams);
        const periodLabel = computed(() => periodLabels[period.value] || '');

        const refresh = () => {
            teamsStore.fetchDashboard({
                teamId: viewTeamId.value,
                period: period.value,
            });
        };

        onMounted(refresh);

        return { period, viewTeamId, dashboard, teams, periodLabel, refresh };
    },
};
</script>
