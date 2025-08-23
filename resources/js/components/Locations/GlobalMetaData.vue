<template>
    <section class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-8">
            <!-- Title -->
            <h1 class="text-4xl md:text-5xl font-bold text-center mb-8">#LitterWorldCup</h1>

            <!-- Global Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <h2 class="text-lg font-semibold mb-2">{{ t('Tags') }}</h2>
                    <div class="text-3xl md:text-4xl font-bold">
                        <span v-if="loading">...</span>
                        <NumberAnimation
                            v-else
                            :from="previousTotalLitter"
                            :to="totalLitter"
                            :duration="2"
                            :delay="0.5"
                            easing="easeOutExpo"
                            :format="formatNumber"
                        />
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <h2 class="text-lg font-semibold mb-2">{{ t('Photos') }}</h2>
                    <div class="text-3xl md:text-4xl font-bold">
                        <span v-if="loading">...</span>
                        <NumberAnimation
                            v-else
                            :from="previousTotalPhotos"
                            :to="totalPhotos"
                            :duration="2"
                            :delay="0.7"
                            easing="easeOutExpo"
                            :format="formatNumber"
                        />
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur-sm rounded-lg p-6 text-center">
                    <h2 class="text-lg font-semibold mb-2">{{ t('Contributors') }}</h2>
                    <div class="text-3xl md:text-4xl font-bold">
                        <span v-if="loading">...</span>
                        <NumberAnimation
                            v-else
                            :from="previousContributors"
                            :to="totalContributors"
                            :duration="2"
                            :delay="0.9"
                            easing="easeOutExpo"
                            :format="formatNumber"
                        />
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <Progress :loading="loading" />
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useWorldStore } from '@/stores/world';
import NumberAnimation from 'vue-number-animation';
import Progress from './Progress.vue';

const { t } = useI18n();
const worldStore = useWorldStore();

const props = defineProps({
    loading: Boolean,
});

const totalLitter = computed(() => worldStore.total_litter || 0);
const totalPhotos = computed(() => worldStore.total_photos || 0);
const totalContributors = computed(() => worldStore.total_contributors || 0);

const previousTotalLitter = computed(() => {
    const stored = localStorage.getItem('total_litter');
    const prev = stored ? parseInt(stored, 10) : 0;
    localStorage.setItem('total_litter', totalLitter.value.toString());
    return prev;
});

const previousTotalPhotos = computed(() => {
    const stored = localStorage.getItem('total_photos');
    const prev = stored ? parseInt(stored, 10) : 0;
    localStorage.setItem('total_photos', totalPhotos.value.toString());
    return prev;
});

const previousContributors = computed(() => {
    const stored = localStorage.getItem('total_contributors');
    const prev = stored ? parseInt(stored, 10) : 0;
    localStorage.setItem('total_contributors', totalContributors.value.toString());
    return prev;
});

const formatNumber = (n) => {
    return parseInt(n, 10).toLocaleString();
};

let channel;

onMounted(() => {
    if (window.Echo) {
        channel = window.Echo.channel('main')
            .listen('ImageUploaded', (payload) => {
                if (payload.isUserVerified) {
                    worldStore.incrementTotalPhotos();
                }
            })
            .listen('TagsVerifiedByAdmin', (payload) => {
                worldStore.incrementTotalLitter(payload.total_litter_all_categories);
                if (!payload.isUserVerified) {
                    worldStore.incrementTotalPhotos();
                }
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('main');
    }
});
</script>
