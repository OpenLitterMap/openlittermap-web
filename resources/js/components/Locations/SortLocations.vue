<template>
    <div class="bg-white/10 backdrop-blur-sm rounded-lg p-4 mb-6">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <!-- Search Input -->
            <div class="w-full md:w-1/3">
                <div class="relative">
                    <input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="`Search ${locationType}s...`"
                        class="w-full pl-10 pr-4 py-2 rounded-lg bg-white/90 text-gray-800 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                    />
                    <svg
                        class="absolute left-3 top-2.5 h-5 w-5 text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                </div>
            </div>

            <!-- Sort Controls -->
            <div class="flex items-center gap-4">
                <select
                    v-model="sortBy"
                    class="px-4 py-2 rounded-lg bg-white/90 text-gray-800 focus:outline-none focus:ring-2 focus:ring-white"
                >
                    <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                        {{ opt.text }}
                    </option>
                </select>

                <button
                    @click="toggleSortDirection"
                    class="p-2 rounded-lg bg-white/90 hover:bg-white transition-colors"
                    :title="sortDirection === 'asc' ? 'Ascending' : 'Descending'"
                >
                    <svg
                        v-if="sortDirection === 'desc'"
                        class="w-5 h-5 text-gray-800"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-gray-800" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                </button>

                <!-- View Toggle -->
                <div class="flex bg-white/90 rounded-lg p-1">
                    <button
                        @click="viewMode = 'grid'"
                        :class="['p-2 rounded', viewMode === 'grid' ? 'bg-blue-500 text-white' : 'text-gray-800']"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    </button>
                    <button
                        @click="viewMode = 'list'"
                        :class="['p-2 rounded', viewMode === 'list' ? 'bg-blue-500 text-white' : 'text-gray-800']"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Active Filters -->
        <div v-if="searchQuery" class="mt-4 flex items-center gap-2">
            <span class="text-white/70 text-sm">Filtering:</span>
            <span class="bg-white/20 px-3 py-1 rounded-full text-white text-sm flex items-center gap-2">
                {{ searchQuery }}
                <button @click="searchQuery = ''" class="hover:text-red-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useWorldStore } from '@/stores/world';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const worldStore = useWorldStore();

const searchQuery = ref('');
const viewMode = ref('grid');

const locationType = computed(() => worldStore.locationType || 'country');

const sortOptions = ref([
    { text: 'A-Z', value: 'alphabetical' },
    { text: t('location.most-data'), value: 'most-data' },
    { text: t('location.most-data-person'), value: 'most-data-per-person' },
    { text: 'Total Contributors', value: 'total-contributors' },
    { text: 'First Created', value: 'first-created' },
    { text: 'Most Recently Created', value: 'most-recently-created' },
    { text: 'Most Recently Updated', value: 'most-recently-updated' },
]);

const sortBy = computed({
    get: () => worldStore.sortLocationsBy,
    set: (value) => worldStore.setSortLocationsBy(value),
});

const sortDirection = computed({
    get: () => worldStore.sortDirection || 'desc',
    set: (value) => worldStore.setSortDirection(value),
});

const toggleSortDirection = () => {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
};

watch(searchQuery, (newVal) => {
    worldStore.setSearchQuery(newVal);
});

watch(viewMode, (newVal) => {
    worldStore.setViewMode(newVal);
});
</script>
