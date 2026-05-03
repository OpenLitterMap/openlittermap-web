<script setup>
import { usePhotosStore } from '@/stores/photos';
import axios from 'axios';
import { computed, ref, onMounted } from 'vue';

const store = usePhotosStore();

const exporting = ref(false);
const exportMessage = ref('');

const exportSuccess = ref(false);

const layout = ref('wide');
const formatSplit = ref(true);
const formatJoined = ref(false);

const exportDisabled = computed(() =>
    exporting.value || (layout.value === 'wide' && !formatSplit.value && !formatJoined.value)
);

const buildFormatParam = () => {
    if (layout.value === 'long') return '';
    const parts = [];
    if (formatSplit.value) parts.push('split');
    if (formatJoined.value) parts.push('joined');
    return parts.join(',');
};

const exportCsv = async () => {
    exporting.value = true;
    exportMessage.value = '';

    try {
        const params = {
            dateField: 'datetime',
            layout: layout.value,
            format: buildFormatParam(),
        };
        if (filters.value.dateFrom) params.fromDate = filters.value.dateFrom;
        if (filters.value.dateTo) params.toDate = filters.value.dateTo;

        await axios.get('/api/user/profile/download', { params });
        exportSuccess.value = true;
        exportMessage.value = 'Export started — check your email for the download link.';
    } catch {
        exportSuccess.value = false;
        exportMessage.value = 'Export failed. Please try again.';
    } finally {
        exporting.value = false;
    }
};

const filters = ref({
    id: '',
    idOperator: '=',
    tag: '',
    customTag: '',
    dateFrom: '',
    dateTo: '',
    perPage: '25',
    taggedState: 'all', // 'all' | 'tagged' | 'untagged'
    pickedUp: 'all', // 'all' | 'true' | 'false'
});

// Get stats from store (updates when filters are applied)
const totalPhotos = computed(() => store.untaggedStats.totalPhotos);
const totalTags = computed(() => store.untaggedStats.totalTags);
const leftToTag = computed(() => store.untaggedStats.leftToTag);
const taggedPercentage = computed(() => store.untaggedStats.taggedPercentage);

// Show header once data has loaded (even if filtered to 0)
const hasEverLoaded = computed(() => store.pagination.total !== undefined);

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
        pickedUp: filters.value.pickedUp,
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
    applyFilters();
};

// Cycle picked up: all -> true -> false -> all
const cyclePickedUp = () => {
    const states = ['all', 'true', 'false'];
    const currentIndex = states.indexOf(filters.value.pickedUp);
    filters.value.pickedUp = states[(currentIndex + 1) % states.length];
    applyFilters();
};

// Initial load: fetch both photos and stats
onMounted(async () => {
    await store.fetchUntaggedData(1, buildFilterParams());
});
</script>

<template>
    <div v-if="hasEverLoaded" class="bg-white rounded-lg shadow-sm mb-6">
        <!-- Stats Row -->
        <div v-if="totalPhotos > 0" class="grid grid-cols-4 px-6 py-2 border-b border-gray-100">
            <!-- Total Photos Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ totalPhotos.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">{{ $t('Total Photos') }}</div>
                </div>
            </div>

            <!-- Total Tags Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ totalTags.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">{{ $t('Total Tags') }}</div>
                </div>
            </div>

            <!-- Left to Tag Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">
                        {{ leftToTag.toLocaleString() }}
                    </div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">{{ $t('Untagged') }}</div>
                </div>
            </div>

            <!-- Tagged Percentage Column -->
            <div class="flex items-center justify-center">
                <div class="text-center">
                    <div class="text-xl font-semibold text-gray-900">{{ taggedPercentage }}%</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide mt-0.5">{{ $t('Tagged') }}</div>
                </div>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="flex items-end gap-3 px-6 py-4 flex-wrap justify-center">
            <!-- Three-state Toggle for Tagged/Untagged/All -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Tagged') }}</label>
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
                            ? $t('All Photos')
                            : filters.taggedState === 'untagged'
                              ? $t('Untagged')
                              : $t('Tagged')
                    }}
                </button>
            </div>

            <!-- Picked Up Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Picked Up') }}</label>
                <button
                    @click="cyclePickedUp"
                    class="px-3 py-1.5 text-xs font-medium border rounded transition-colors min-w-[90px]"
                    :class="{
                        'bg-gray-100 border-gray-300 text-gray-700': filters.pickedUp === 'all',
                        'bg-green-50 border-green-300 text-green-700': filters.pickedUp === 'true',
                        'bg-amber-50 border-amber-300 text-amber-700': filters.pickedUp === 'false',
                    }"
                >
                    {{
                        filters.pickedUp === 'all'
                            ? $t('All')
                            : filters.pickedUp === 'true'
                              ? $t('Picked Up')
                              : $t('Not Picked Up')
                    }}
                </button>
            </div>

            <!-- ID Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Photo ID') }}</label>
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
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Tag') }}</label>
                <input
                    v-model="filters.tag"
                    placeholder="Enter tag"
                    class="w-32 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Custom Tag Filter -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Custom Tag') }}</label>
                <input
                    v-model="filters.customTag"
                    placeholder="Enter custom tag"
                    class="w-32 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Date From -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('From Date') }}</label>
                <input
                    type="date"
                    v-model="filters.dateFrom"
                    class="px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Date To -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('To Date') }}</label>
                <input
                    type="date"
                    v-model="filters.dateTo"
                    class="px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                />
            </div>

            <!-- Per Page -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Per Page') }}</label>
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
                {{ $t('Apply') }}
            </button>

            <!-- CSV Format Selector -->
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $t('Format') }}</label>
                <div class="flex flex-col gap-1">
                    <label class="flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" value="wide" v-model="layout" class="h-3.5 w-3.5" />
                        {{ $t('Wide format') }}
                        <span
                            v-tooltip="$t('One row per photo with a column for every possible tag. Easy to scan in Excel. Most cells will be empty.')"
                            :title="$t('One row per photo with a column for every possible tag. Easy to scan in Excel. Most cells will be empty.')"
                            class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                            aria-label="Wide format help"
                        >ⓘ</span>
                    </label>
                    <div class="ml-5 flex items-center gap-3" :class="layout === 'long' ? 'opacity-50' : ''">
                        <label class="flex items-center gap-1 text-xs text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                            <input type="checkbox" v-model="formatSplit" :disabled="layout === 'long'" class="h-3.5 w-3.5" />
                            {{ $t('Separate columns') }}
                            <span
                                v-tooltip="$t('One column each for object, type, and material. Recommended for new analyses.')"
                                :title="$t('One column each for object, type, and material. Recommended for new analyses.')"
                                class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                                aria-label="Separate columns help"
                            >ⓘ</span>
                        </label>
                        <label class="flex items-center gap-1 text-xs text-gray-700" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                            <input type="checkbox" v-model="formatJoined" :disabled="layout === 'long'" class="h-3.5 w-3.5" />
                            {{ $t('Combined columns') }}
                            <span
                                v-tooltip="$t('Object and type joined into one column (v4-style). Use this if your existing scripts expect columns like spirits_bottle.')"
                                :title="$t('Object and type joined into one column (v4-style). Use this if your existing scripts expect columns like spirits_bottle.')"
                                class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                                aria-label="Combined columns help"
                            >ⓘ</span>
                        </label>
                    </div>
                    <label class="flex items-center gap-1 text-xs text-gray-700 cursor-pointer">
                        <input type="radio" value="long" v-model="layout" class="h-3.5 w-3.5" />
                        {{ $t('Long format') }}
                        <span
                            v-tooltip="$t('One row per tag, with photo details repeated. Each tag gets its own row. Best for analysis tools like pandas, SQL, or Tableau.')"
                            :title="$t('One row per tag, with photo details repeated. Each tag gets its own row. Best for analysis tools like pandas, SQL, or Tableau.')"
                            class="text-gray-400 hover:text-gray-600 cursor-help select-none"
                            aria-label="Long format help"
                        >ⓘ</span>
                    </label>
                </div>
            </div>

            <!-- Export CSV Button -->
            <button
                @click="exportCsv"
                :disabled="exportDisabled"
                class="px-4 py-1.5 bg-green-500 hover:bg-green-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-medium rounded transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1"
            >
                {{ exporting ? $t('Exporting...') : $t('Export CSV') }}
            </button>
        </div>

        <!-- Export message -->
        <div v-if="exportMessage" class="px-6 pb-3">
            <p class="text-xs" :class="exportSuccess ? 'text-green-600' : 'text-red-600'">{{ exportMessage }}</p>
        </div>
    </div>
</template>
