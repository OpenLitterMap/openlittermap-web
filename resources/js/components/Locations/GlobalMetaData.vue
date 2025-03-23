<template>
    <!-- Outer section: background color, padding, font styling -->
    <section class="bg-blue-500 font-bold text-white">
        <!-- Inner wrapper with responsive padding -->
        <section class="px-4 py-6 md:px-12 md:py-12">
            <!-- Container centers the content and sets a max width -->
            <div class="max-w-7xl mx-auto">
                <!-- Total counts component -->
                <TotalGlobalCounts :loading="loading" />
            </div>

            <Progress :loading="loading" />
        </section>
    </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';

import TotalGlobalCounts from './TotalGlobalCounts.vue';
import Progress from './Progress.vue';

const props = defineProps({
    loading: Boolean,
});

// Set up Echo event listeners (assuming a global Echo instance is available)
let channel;

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
