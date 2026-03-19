<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 relative overflow-hidden">
        <!-- Ambient blobs -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl"></div>
        </div>

        <div class="relative container mx-auto px-4 py-8 max-w-4xl">
            <!-- Tabs -->
            <div class="flex gap-1 mb-6 bg-white/5 rounded-lg p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex-1 px-4 py-2.5 rounded-md text-sm font-medium transition-colors"
                    :class="activeTab === tab.key
                        ? 'bg-white/10 text-white'
                        : 'text-white/50 hover:text-white/70'"
                    @click="switchTab(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Loading -->
            <div v-if="profileStore.loading" class="flex justify-center items-center py-32">
                <div class="animate-spin rounded-full h-10 w-10 border-2 border-white/20 border-t-emerald-400"></div>
            </div>

            <!-- Error -->
            <div v-else-if="profileStore.error" class="text-center py-16">
                <p class="text-red-300 text-lg">{{ profileStore.error }}</p>
                <button
                    @click="profileStore.FETCH_PROFILE()"
                    class="mt-4 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white transition"
                >
                    Retry
                </button>
            </div>

            <!-- Tab Content -->
            <template v-else>
                <ProfileDashboard v-if="activeTab === 'dashboard'" />
                <ProfilePhotos v-else-if="activeTab === 'photos'" />
                <ProfileSettings v-else-if="activeTab === 'settings'" />
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProfileStore } from '@stores/profile.js';
import ProfileDashboard from './components/ProfileDashboard.vue';
import ProfilePhotos from './components/ProfilePhotos.vue';
import ProfileSettings from './components/ProfileSettings.vue';

const route = useRoute();
const router = useRouter();
const profileStore = useProfileStore();

const tabs = [
    { key: 'dashboard', label: 'Dashboard' },
    { key: 'photos', label: 'Photos' },
    { key: 'settings', label: 'Settings' },
];

const activeTab = ref(route.query.tab || 'dashboard');

const switchTab = (key) => {
    activeTab.value = key;
    router.replace({ query: { ...route.query, tab: key } });
};

watch(() => route.query.tab, (val) => {
    if (val && tabs.some((t) => t.key === val)) {
        activeTab.value = val;
    }
});

onMounted(() => {
    profileStore.FETCH_PROFILE();
});
</script>
