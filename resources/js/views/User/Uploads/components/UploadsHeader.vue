<script setup>
import { usePhotosStore } from '@/stores/photos';
import { computed, ref, onMounted } from 'vue';

const store = usePhotosStore();

const filters = ref({
    id: '',
    idOperator: '=',
    tag: '',
    customTag: '',
    dateFrom: '',
    dateTo: '',
    perPage: '25',
    taggedState: 'all', // 'all' | 'tagged' | 'untagged'
});

// Get stats from store
const totalPhotos = computed(() => store.untaggedStats.totalPhotos);
const totalTags = computed(() => store.untaggedStats.totalTags);
const leftToTag = computed(() => store.untaggedStats.leftToTag);
const taggedPercentage = computed(() => store.untaggedStats.taggedPercentage);

// Convert taggedState to API parameter
const getTaggedParam = () => {
    if (filters.value.taggedState === 'tagged') return true;
    if (filters.value.taggedState === 'untagged') return false;
    return null; // 'all' state
};

// Build filter object for API
const buildFilterParams = () => {
    return {
        tagged: getTaggedParam(),
        id: filters.value.id || null,
        idOperator: filters.value.idOperator,
        tag: filters.value.tag || null,
        customTag: filters.value.customTag || null,
        dateFrom: filters.value.dateFrom || null,
        dateTo: filters.value.dateTo || null,
        perPage: parseInt(filters.value.perPage),
    };
};

// Load photos with filters
const loadPhotos = async (page = 1) => {
    await store.fetchPhotosOnly(page, buildFilterParams());
};

// Apply filters and reload
const applyFilters = () => {
    loadPhotos(1);
};

// Cycle through states: all -> untagged -> tagged -> all
const cycleTaggedState = () => {
    const states = ['all', 'untagged', 'tagged'];
    const currentIndex = states.indexOf(filters.value.taggedState);
    filters.value.taggedState = states[(currentIndex + 1) % states.length];
    applyFilters(); // Use applyFilters instead of loadPhotos to ensure all filters are applied
};

// Initial load: fetch both photos and stats
onMounted(async () => {
    await store.fetchUntaggedData(1, buildFilterParams());
});
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm mb-6">
        <!-- Stats Row -->
        <div class="grid grid-cols-4 px-6 py-2 border-b border-gray-100">
            <!-- Total Photos Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ totalPhotos.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Total Photos</div>
                </div>
            </div>

            <!-- Total Tags Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ totalTags.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Total Tags</div>
                </div>
            </div>

            <!-- Left to Tag Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ leftToTag.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Untagged</div>
                </div>
            </div>

            <!-- Tagged Percentage Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">{{ taggedPercentage }}%</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">Tagged</div>
                </div>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="flex items-end gap-3 px-6 py-4 flex-wrap">
            <!-- Three-state Toggle for Tagged/Untagged/All -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">Photo Status</label>
                <button
                    @click="cycleTaggedState"
                    class="px-3 py-1.5 text-xs font-medium border rounded transition-colors min-w-[90px]"
                    :class="{
                        'bg-gray-100 border-gray-300 text-gray-700': filters.taggedState === 'all',
                        'bg-red-50 border-red-300 text-red-700': filters.taggedState === 'untagged',
                        'bg-green-50 border-green-300 text-green-700': filters.taggedState === 'tagged',
                    }"
                >
                    {{
                        filters.taggedState === 'all'
                            ? 'All Photos'
                            : filters.taggedState === 'untagged'
                              ? 'Untagged'
                              : 'Tagged'
                    }}
                </button>
            </div>

            <!-- ID Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">Photo ID</label>
                <div class="flex gap-1">
                    <select
                        v-model="filters.idOperator"
                        class="w-10 px-1 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="=">=</option>
                        <option value=">">></option>
                        <option value="<"><</option>
                    </select>
                    <input
                        type="number"
                        v-model="filters.id"
                        placeholder="ID"
                        class="w-20 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
            </div>

            <!-- Tag Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">Tag</label>
                <input
                    v-model="filters.tag"
                    placeholder="Enter tag"
                    class="w-32 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Custom Tag Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">Custom Tag</label>
                <input
                    v-model="filters.customTag"
                    placeholder="Enter custom tag"
                    class="w-32 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Date From -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">From Date</label>
                <input
                    type="date"
                    v-model="filters.dateFrom"
                    class="px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Date To -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">To Date</label>
                <input
                    type="date"
                    v-model="filters.dateTo"
                    class="px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Per Page -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">Per Page</label>
                <select
                    v-model="filters.perPage"
                    class="w-16 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <!-- Apply Button -->
            <button
                @click="applyFilters"
                class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
            >
                Apply
            </button>
        </div>
    </div>
</template>
