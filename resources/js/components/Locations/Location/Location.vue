<template>
    <section>
        <h1 class="text-4xl font-bold text-center text-gray-800">#LitterWorldCup</h1>

        <div class="py-8 px-8 flex">
            <!-- Location Metadata -->
            <LocationMetadata :index="index" :location="location" :locationType="locationType" :category="sortedBy" />

            <!-- Charts/Tab area -->
            <div class="w-full md:w-1/2 md:ml-4">
                <p class="block md:hidden mb-2">Drag these across for more options</p>
                <div class="flex justify-center space-x-2 mb-4">
                    <p
                        v-for="(tab, idx) in tabs"
                        :key="idx"
                        v-show="showTab(tab.in_location)"
                        @click="changeTab(tab.component, location.id)"
                        :class="[
                            'cursor-pointer px-4 py-2 rounded',
                            tab.component === selectedTab ? 'bg-blue-600 text-white' : 'bg-white text-gray-800',
                        ]"
                    >
                        {{ tab.title }}
                    </p>
                </div>

                <component
                    :is="componentsMap[selectedTab]"
                    :litter_data="location.litter_data"
                    :brands_data="location.brands_data"
                    :total_brands="location.total_brands"
                    :ppm="location.ppm"
                    :time="location.time"
                    @dateschanged="updateUrl"
                    :index="index"
                    :locationType="locationType"
                    :locationId="location.id"
                    :leaders="getUsersForLocationLeaderboard"
                    :total_ppm="location.total_ppm"
                    v-show="showOnlySelectedComponent(location.id)"
                />
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, defineProps } from 'vue';
import { useI18n } from 'vue-i18n';
import LocationMetadata from './LocationMetadata.vue';
import LitterPieCharts from './Charts/PieCharts/LitterPieCharts.vue';
import TimeSeriesContainer from './Charts/LineCharts/TimeSeriesContainer.vue';
import LeaderboardList from '../../../views/General/Leaderboards/components/LeaderboardList.vue';
import Options from './Controls/Options.vue';
import Download from './Controls/Download.vue';

const { t } = useI18n();
import { useWorldStore } from '@/stores/world';
import { useLeaderboardStore } from '@/stores/leaderboard';
import { useUserStore } from '../../../stores/user/index.js';
const worldStore = useWorldStore();
const leaderboardStore = useLeaderboardStore();
const userStore = useUserStore();

const componentsMap = {
    LitterPieCharts,
    TimeSeriesContainer,
    LeaderboardList,
    Options,
    Download,
};

const props = defineProps({
    location: {
        type: Object,
        required: true,
    },
    locationType: {
        type: String,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
});

const locationType = props.locationType;
const index = props.index;

// Local state and tabs definition using the i18n function
const selectedTab = ref('LeaderboardList');

const tabs = ref([
    { title: t('Litter'), component: 'ChartsContainer', in_location: 'all' },
    { title: t('Time-series'), component: 'TimeSeriesContainer', in_location: 'all' },
    { title: t('Leaderboard'), component: 'LeaderboardList', in_location: 'all' },
    { title: t('Options'), component: 'Options', in_location: 'city' },
    { title: t('Download'), component: 'Download', in_location: 'all' },
]);

// Expose the current sort option (for passing to child components)
const sortedBy = computed(() => worldStore.sortLocationsBy);

// Compute the leaderboard users from the leaderboard store
const getUsersForLocationLeaderboard = computed(() => {
    const selectedLocationId = worldStore.selectedLocationId;
    return leaderboardStore.leaderboard[locationType]?.[selectedLocationId] || [];
});

// Check for user authentication (from your user store)
const isAuth = computed(() => userStore.auth);

// Load a tab and (if needed) dispatch an action to update leaderboard data
function changeTab(tab, locationId) {
    // if (['TimeSeriesContainer', 'ChartsContainer', 'LeaderboardList', 'Download'].includes(tab)) {
    //     leaderboardStore.getUsersForLocationLeaderboard({
    //         timeFilter: 'today',
    //         locationType: locationType,
    //         locationId,
    //     });
    // }

    worldStore.selectedLocationId = locationId;
    selectedTab.value = tab;
}

// Determine whether to show the component for the currently selected location
function showOnlySelectedComponent(locationId) {
    return worldStore.selectedLocationId === locationId;
}

// Show a tab if its type matches the location or if it is set to "all"
const showTab = (tabType) => {
    return tabType === 'all' || locationType === tabType;
};

// Update the URL (or perform other side effects)
const updateUrl = (url) => {
    console.log({ url });
};
</script>

<style scoped></style>
