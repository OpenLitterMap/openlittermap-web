<template>
    <div class="relative min-h-[calc(100vh-72px)] bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900">
        <div class="flex min-h-[calc(100vh-72px)] flex-col items-center justify-center p-4">
            <div class="w-full max-w-lg">
                <div class="rounded-xl bg-white/5 border border-white/10 backdrop-blur-xl p-6 shadow-xl sm:p-8">
                    <!-- Step Indicator — all complete -->
                    <StepIndicator :current-step="4" />

                    <!-- Success icon -->
                    <div class="mt-4 flex justify-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20 ring-4 ring-emerald-500/10">
                            <svg class="h-8 w-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Heading -->
                    <h1 class="mt-4 text-center text-2xl font-bold text-white">
                        You did it!
                    </h1>
                    <p class="mt-2 text-center text-white/60">
                        Your first contribution is now part of the global litter map.
                        Every tag helps researchers and communities understand pollution.
                    </p>

                    <!-- XP badge -->
                    <div v-if="!loading && userXp > 0" class="mt-4 flex justify-center">
                        <div class="flex items-center gap-2 rounded-lg bg-emerald-500/15 border border-emerald-500/20 px-4 py-2">
                            <span class="text-emerald-400 font-bold">{{ userXp }} XP</span>
                            <span class="text-white/40 text-sm">earned so far</span>
                        </div>
                    </div>

                    <!-- Geolink + sharing -->
                    <div v-if="hasCoords" class="mt-6 rounded-lg border border-white/10 bg-white/5 p-4">
                        <p class="mb-2 text-sm font-medium text-white/70">Your upload on the global map:</p>
                        <div class="flex items-center gap-2">
                            <code class="flex-1 truncate rounded bg-white/5 px-3 py-1.5 text-xs text-white/70">
                                {{ geolinkDisplay }}
                            </code>
                            <button
                                @click="copyGeolink"
                                class="shrink-0 rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium transition-all hover:bg-white/10"
                                :class="copied ? 'text-emerald-400' : 'text-white/60'"
                            >
                                {{ copied ? 'Copied!' : 'Copy link' }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-white/40">
                            Copy this link and share it with anyone. OpenLitterMap is a real-time, open-source global reporting tool.
                        </p>
                        <p class="mt-1 text-xs text-white/30">
                            Tip: Take a photo of bags of litter picked up and share the link with your local council!
                        </p>
                    </div>

                    <!-- CTAs -->
                    <div class="mt-8 space-y-3">
                        <router-link
                            :to="mapLink"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 py-3 font-semibold text-white transition-colors hover:bg-emerald-400"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            See your upload on the global map
                        </router-link>
                        <router-link
                            to="/upload"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/5 py-3 font-medium text-white/80 transition-colors hover:bg-white/10"
                        >
                            Upload more photos
                        </router-link>
                        <router-link
                            to="/profile"
                            class="block w-full text-center text-sm text-white/30 transition-colors hover:text-white/50"
                        >
                            Go to your profile
                        </router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useUserStore } from '@/stores/user';
import StepIndicator from '@/components/onboarding/StepIndicator.vue';

const route = useRoute();
const userStore = useUserStore();
const loading = ref(true);
const copied = ref(false);

const lat = computed(() => route.query.lat || null);
const lon = computed(() => route.query.lon || null);
const hasCoords = computed(() => lat.value && lon.value);

const geolinkPath = computed(() => {
    if (!hasCoords.value) return '/global';
    return `/global?lat=${lat.value}&lon=${lon.value}&zoom=17.89&load=true&open=true`;
});

const mapLink = computed(() => geolinkPath.value);

const geolinkDisplay = computed(() => {
    if (!hasCoords.value) return '';
    return `${window.location.host}/global?lat=${lat.value}&lon=${lon.value}&zoom=17.89`;
});

const userXp = computed(() => userStore.user?.xp || 0);

function copyGeolink() {
    if (!hasCoords.value) return;
    const url = `${window.location.origin}/global?lat=${lat.value}&lon=${lon.value}&zoom=17.89&load=true&open=true`;
    navigator.clipboard.writeText(url);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

onMounted(async () => {
    await userStore.REFRESH_USER();
    loading.value = false;
});
</script>
