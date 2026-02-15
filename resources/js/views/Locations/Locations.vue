<template>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900 relative overflow-hidden">
        <!-- Ambient background blobs -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-teal-500/[0.07] blur-3xl"></div>
            <div class="absolute top-1/3 -right-32 w-[400px] h-[400px] rounded-full bg-blue-500/[0.08] blur-3xl"></div>
            <div
                class="absolute bottom-0 left-1/3 w-[350px] h-[350px] rounded-full bg-purple-500/[0.05] blur-3xl"
            ></div>
        </div>

        <div class="relative container mx-auto px-4 py-8 max-w-6xl">
            <!-- Breadcrumbs -->
            <LocationBreadcrumb :items="store.breadcrumbs" />

            <!-- Stats Bar -->
            <LocationStatsBar :stats="store.stats" :activity="store.activity" :loading="store.loading" />

            <!-- Location Meta (detail pages only) -->
            <div
                v-if="store.meta && !store.loading"
                class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-2 text-sm bg-white/5 border border-white/10 rounded-xl px-5 py-4"
            >
                <div v-if="store.meta.pct_tags > 0">
                    <span class="text-white/40">% of global tags</span>
                    <span class="ml-2 text-white">{{ store.meta.pct_tags }}%</span>
                </div>
                <div v-if="store.meta.pct_photos > 0">
                    <span class="text-white/40">% of global photos</span>
                    <span class="ml-2 text-white">{{ store.meta.pct_photos }}%</span>
                </div>
                <div v-if="store.meta.avg_tags_per_person > 0">
                    <span class="text-white/40">Avg tags / person</span>
                    <span class="ml-2 text-white">{{ store.meta.avg_tags_per_person }}</span>
                </div>
                <div v-if="store.meta.avg_photos_per_person > 0">
                    <span class="text-white/40">Avg photos / person</span>
                    <span class="ml-2 text-white">{{ store.meta.avg_photos_per_person }}</span>
                </div>
                <div v-if="store.meta.created_by">
                    <span class="text-white/40">Created by</span>
                    <span class="ml-2 text-white">{{ store.meta.created_by.name }}</span>
                </div>
                <div v-if="store.meta.created_at">
                    <span class="text-white/40">Created</span>
                    <span class="ml-2 text-white">{{ timeAgo(store.meta.created_at) }}</span>
                </div>
                <div v-if="store.meta.last_updated_by">
                    <span class="text-white/40">Last updated by</span>
                    <span class="ml-2 text-white">{{ store.meta.last_updated_by.name }}</span>
                </div>
                <div v-if="store.meta.last_updated_at">
                    <span class="text-white/40">Last updated</span>
                    <span class="ml-2 text-white">{{ timeAgo(store.meta.last_updated_at) }}</span>
                </div>
            </div>

            <!-- Toolbar -->
            <LocationTimeFilter
                v-if="!store.loading"
                v-model="searchInput"
                :show-search="store.hasChildren"
                :children-type="store.childrenType"
                class="mt-6"
                @change="loadData"
            />

            <!-- Children Table -->
            <LocationTable
                v-if="!store.loading && store.hasChildren"
                :locations="store.sortedChildren"
                :type="store.childrenType"
                :sort-field="store.sortField"
                :sort-dir="store.sortDir"
                class="mt-4"
                @sort="store.toggleSort"
                @navigate="navigateTo"
            />

            <!-- Loading -->
            <div v-if="store.loading" class="flex justify-center items-center py-32">
                <div class="animate-spin rounded-full h-10 w-10 border-2 border-white/20 border-t-emerald-400"></div>
            </div>

            <!-- City detail (leaf node) -->
            <div v-if="!store.loading && !store.hasChildren && store.location" class="mt-8 text-center text-white/60">
                <p>Photo map and tag breakdown coming soon.</p>
            </div>

            <!-- Error -->
            <div v-if="store.error" class="mt-8 text-center">
                <p class="text-red-300 text-lg">{{ store.error }}</p>
                <button
                    @click="loadData"
                    class="mt-4 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-white transition"
                >
                    Retry
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { watch, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useLocationsStore } from '@/stores/locations';
import LocationBreadcrumb from '@/components/Locations/LocationBreadcrumb.vue';
import LocationStatsBar from '@/components/Locations/LocationStatsBar.vue';
import LocationTable from '@/components/Locations/LocationTable.vue';
import LocationTimeFilter from '@/components/Locations/LocationTimeFilter.vue';

const route = useRoute();
const router = useRouter();
const store = useLocationsStore();

const searchInput = ref('');

function timeAgo(dateStr) {
    if (!dateStr) return '';
    const seconds = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    const intervals = [
        [31536000, 'year'],
        [2592000, 'month'],
        [604800, 'week'],
        [86400, 'day'],
        [3600, 'hour'],
        [60, 'minute'],
    ];
    for (const [secs, label] of intervals) {
        const count = Math.floor(seconds / secs);
        if (count >= 1) return `${count} ${label}${count > 1 ? 's' : ''} ago`;
    }
    return 'just now';
}

let searchTimeout;
watch(searchInput, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => store.setSearch(val), 200);
});

async function loadData() {
    const { id } = route.params;
    searchInput.value = '';
    store.setSearch('');

    if (!id) {
        await store.fetchGlobal();
    } else {
        const type = route.name.replace('locations.', '');
        await store.fetchLocation(type, parseInt(id));
    }
}

function navigateTo(child) {
    const type = store.childrenType;
    router.push({ name: `locations.${type}`, params: { type, id: child.id } });
}

watch(() => route.fullPath, loadData);
onMounted(loadData);
</script>
