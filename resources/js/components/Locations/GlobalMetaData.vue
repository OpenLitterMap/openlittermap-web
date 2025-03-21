<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useWorldStore } from '../../stores/world/index.js';
import Typed from 'typed.js';

import TotalGlobalCounts from './TotalGlobalCounts.vue';
// import Progress from '../General/Progress.vue';
import LeaderboardList from '../../../js/views/General/Leaderboards/components/LeaderboardList.vue';

const worldStore = useWorldStore();

// Define props
const props = defineProps({
    loading: Boolean,
});

const router = useRouter();
const { t } = useI18n();
const typing = ref(null);
let typedInstance = null;

// Compute the top-10 global leaders from the store state
const leaders = computed(() => worldStore.globalLeaders);

// Method to navigate to the leaderboard page
function openLeaderboard() {
    router.push({ path: '/leaderboard' });
}

// Set up Echo event listeners (assuming a global Echo instance is available)
let channel;

onMounted(() => {
    typedInstance = new Typed(typing.value, {
        strings: ['Community ^2000', 'Impact ^3000', 'Progress ^4000'],
        typeSpeed: 100,
        backSpeed: 60,
        loop: true,
    });

    if (window.Echo) {
        channel = window.Echo.channel('main')
            .listen('ImageUploaded', (payload) => {
                if (payload.isUserVerified) {
                    store.commit('incrementTotalPhotos');
                }
            })
            .listen('ImageDeleted', (payload) => {
                if (payload.isUserVerified) {
                    store.commit('decrementTotalPhotos');
                }
            })
            .listen('TagsVerifiedByAdmin', (payload) => {
                store.commit('incrementTotalLitter', payload.total_litter_all_categories);
                // For non-verified users, totalPhotos has not been incremented yet
                if (!payload.isUserVerified) {
                    store.commit('incrementTotalPhotos');
                }
            });
    }
});

// Clean up Echo listeners when the component is unmounted
onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('main');
    }
});
</script>

<template>
    <!-- Outer section: background color, padding, font styling -->
    <section class="bg-blue-500 py-12 font-bold text-white">
        <!-- Inner wrapper with responsive padding -->
        <section class="px-4 py-6 md:px-12 md:py-12">
            <!-- Container centers the content and sets a max width -->
            <div class="max-w-7xl mx-auto">
                <!-- Typed text section centered using flex -->
                <div class="flex justify-center">
                    <h1 class="text-4xl md:text-[75px] mb-4 text-center font-extrabold">
                        Our Global <span ref="typing" class="typing"></span>
                    </h1>
                </div>

                <!-- Total counts component -->
                <TotalGlobalCounts :loading="loading" />

                <!-- Leaderboard heading with hover effects -->
                <div
                    class="flex justify-center items-center gap-4 mb-8 cursor-pointer transition-all duration-300 hover:underline hover:translate-x-4"
                    @click="openLeaderboard"
                >
                    <h3 class="text-3xl text-center">
                        {{ t('location.global-leaderboard') }}
                    </h3>
                    <i class="fa fa-arrow-right text-white text-lg"></i>
                </div>

                <!-- Leaderboard list -->
                <LeaderboardList :leaders="leaders" />
            </div>

            <!-- Progress component below container -->
            <!--            <Progress :loading="loading" />-->
        </section>
    </section>
</template>
