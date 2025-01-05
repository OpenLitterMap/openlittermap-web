<template>
    <section class="bg-blue-bg">
        <div class="font-bold px-4 py-2 sm:px-6 md:px-8">

            <!-- Go Back to the World Cup -->
            <div
                class="flex items-center justify-center gap-4 mt-8 mb-2 cursor-pointer"
                @click="openWorldCup"
            >
                <i class="fa fa-arrow-left text-white text-xl transition-transform transform hover:-translate-x-4"></i>
                <h3 class="text-4xl text-center text-white">
                    {{ t('location.global-leaderboard') }}
                </h3>
            </div>

            <Loading
                :active="isLoading"
                :can-cancel="false"
                color="#ffffff"
                background="rgba(0, 0, 0, 0.8)"
            />

            <div v-if="!isLoading">
                <LeaderboardList
                    :leaders="users"
                />

                <!-- Pagination Buttons -->
                <div v-if="users.length" class="flex justify-center mt-4">
                    <button
                        v-show="leaderboardStore.currentPage > 1"
                        class="btn btn-primary mr-2"
                        @click="loadPreviousPage"
                    >
                        {{ t('common.previous') }}
                    </button>
                    <button
                        v-show="leaderboardStore.hasNextPage"
                        class="btn btn-primary"
                        @click="loadNextPage"
                    >
                        {{ t('common.next') }}
                    </button>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useLeaderboardStore } from "../../../stores/leaderboard/index.js";
import router from "../../../router/index.js";
import { useI18n } from 'vue-i18n';

// Load Components
import Loading from "../../../components/Loading/Loading.vue";
import LeaderboardList from './components/LeaderboardList.vue';

// Reactive variables
const leaderboardStore = useLeaderboardStore();
const { t } = useI18n();
const isLoading = ref(true);

onMounted(async () => {
    isLoading.value = true;

    await leaderboardStore.GET_USERS_FOR_GLOBAL_LEADERBOARD('today');

    isLoading.value = false;
});

// Computed property for leaderboard state
const users = computed(() => leaderboardStore.leaderboard || []);

// Methods
const loadPreviousPage = async () => {
    isLoading.value = true;

    window.scrollTo({ top: 0, behavior: 'smooth' });
    await leaderboardStore.GET_PREVIOUS_LEADERBOARD_PAGE();

    isLoading.value = false;
};

const loadNextPage = async () => {
    isLoading.value = true;

    window.scrollTo({ top: 0, behavior: 'smooth' });
    await leaderboardStore.GET_NEXT_LEADERBOARD_PAGE();

    isLoading.value = false;
};

const openWorldCup = () => {
    router.push({ path: '/world' });
};
</script>
