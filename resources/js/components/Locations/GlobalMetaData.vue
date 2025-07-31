<template>
    <section class="bg-blue-500 font-bold text-white">
        <section class="px-4 py-6 md:px-12 md:py-12">
            <div class="max-w-7xl mx-auto">
                <TotalGlobalCounts :loading="loading" />
            </div>

            <Progress :loading="loading" />
        </section>
    </section>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';

import TotalGlobalCounts from './TotalGlobalCounts.vue';
import Progress from './Progress.vue';

const props = defineProps({
    loading: Boolean,
});

onMounted(() => {
    if (window.Echo) {
        channel = window.Echo.channel('main')
            .listen('ImageUploaded', (payload) => {
                if (payload.isUserVerified) {
                    // store.commit('incrementTotalPhotos');
                }
            })
            .listen('ImageDeleted', (payload) => {
                if (payload.isUserVerified) {
                    // store.commit('decrementTotalPhotos');
                }
            })
            .listen('TagsVerifiedByAdmin', (payload) => {
                // store.commit('incrementTotalLitter', payload.total_litter_all_categories);
                // For non-verified users, totalPhotos has not been incremented yet
                if (!payload.isUserVerified) {
                    // store.commit('incrementTotalPhotos');
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
