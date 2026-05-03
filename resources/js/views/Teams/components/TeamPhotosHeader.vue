<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    members: { type: Array, default: () => [] },
    exporting: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'export']);

const filters = ref({
    status: 'all', // 'all' | 'pending' | 'approved'
    taggedState: 'all', // 'all' | 'tagged' | 'untagged'
    pickedUp: 'all', // 'all' | 'true' | 'false'
    id: '',
    idOperator: '=',
    tag: '',
    customTag: '',
    dateFrom: '',
    dateTo: '',
    perPage: '20',
    memberId: '',
});

const layout = ref('wide');
const formatSplit = ref(true);
const formatJoined = ref(false);

watch(layout, (next) => {
    if (next === 'wide' && !formatSplit.value && !formatJoined.value) {
        formatSplit.value = true;
    }
});

const exportDisabled = computed(() =>
    props.exporting || (layout.value === 'wide' && !formatSplit.value && !formatJoined.value)
);

const buildFormatParam = () => {
    if (layout.value === 'long') return '';
    const parts = [];
    if (formatSplit.value) parts.push('split');
    if (formatJoined.value) parts.push('joined');
    return parts.join(',');
};

const buildFilterParams = () => {
    const taggedParam =
        filters.value.taggedState === 'tagged'
            ? true
            : filters.value.taggedState === 'untagged'
              ? false
              : null;

    return {
        status: filters.value.status,
        tagged: taggedParam,
        picked_up: filters.value.pickedUp === 'all' ? null : filters.value.pickedUp,
        id: filters.value.id || null,
        id_operator: filters.value.idOperator,
        tag: filters.value.tag || null,
        custom_tag: filters.value.customTag || null,
        date_from: filters.value.dateFrom || null,
        date_to: filters.value.dateTo || null,
        per_page: parseInt(filters.value.perPage),
        member_id: filters.value.memberId || null,
    };
};

const applyFilters = () => {
    emit('apply', buildFilterParams());
};

const exportCsv = () => {
    emit('export', { ...buildFilterParams(), format: buildFormatParam(), layout: layout.value });
};

// Cycle through states: all -> pending -> approved -> all
const cycleStatus = () => {
    const states = ['all', 'pending', 'approved'];
    const currentIndex = states.indexOf(filters.value.status);
    filters.value.status = states[(currentIndex + 1) % states.length];
    applyFilters();
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

const statusLabel = computed(() => {
    if (filters.value.status === 'pending') return t('Pending');
    if (filters.value.status === 'approved') return t('Approved');
    return t('All');
});

const taggedLabel = computed(() => {
    if (filters.value.taggedState === 'tagged') return t('Tagged');
    if (filters.value.taggedState === 'untagged') return t('Untagged');
    return t('All Photos');
});

const pickedUpLabel = computed(() => {
    if (filters.value.pickedUp === 'true') return t('Picked Up');
    if (filters.value.pickedUp === 'false') return t('Not Picked Up');
    return t('All');
});
</script>

<template>
    <div class="bg-white/5 border border-white/10 rounded-xl mb-4">
        <!-- Filters Row -->
        <div class="flex items-end gap-3 px-5 py-4 flex-wrap">
            <!-- Status toggle (All / Pending / Approved) -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Status') }}</label>
                <button
                    @click="cycleStatus"
                    class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors min-w-[90px]"
                    :class="{
                        'bg-white/5 border-white/20 text-white/70': filters.status === 'all',
                        'bg-amber-500/10 border-amber-500/30 text-amber-400': filters.status === 'pending',
                        'bg-emerald-500/10 border-emerald-500/30 text-emerald-400': filters.status === 'approved',
                    }"
                >
                    {{ statusLabel }}
                </button>
            </div>

            <!-- Tagged toggle -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Tagged') }}</label>
                <button
                    @click="cycleTaggedState"
                    class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors min-w-[90px]"
                    :class="{
                        'bg-white/5 border-white/20 text-white/70': filters.taggedState === 'all',
                        'bg-red-500/10 border-red-500/30 text-red-400': filters.taggedState === 'untagged',
                        'bg-emerald-500/10 border-emerald-500/30 text-emerald-400': filters.taggedState === 'tagged',
                    }"
                >
                    {{ taggedLabel }}
                </button>
            </div>

            <!-- Picked Up toggle -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Picked Up') }}</label>
                <button
                    @click="cyclePickedUp"
                    class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors min-w-[90px]"
                    :class="{
                        'bg-white/5 border-white/20 text-white/70': filters.pickedUp === 'all',
                        'bg-emerald-500/10 border-emerald-500/30 text-emerald-400': filters.pickedUp === 'true',
                        'bg-amber-500/10 border-amber-500/30 text-amber-400': filters.pickedUp === 'false',
                    }"
                >
                    {{ pickedUpLabel }}
                </button>
            </div>

            <!-- Photo ID -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Photo ID') }}</label>
                <div class="flex gap-1">
                    <select
                        v-model="filters.idOperator"
                        class="w-10 px-1 py-1.5 text-xs bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:border-emerald-500/50"
                    >
                        <option value="=" class="bg-slate-800">=</option>
                        <option value=">" class="bg-slate-800">></option>
                        <option value="<" class="bg-slate-800">&lt;</option>
                    </select>
                    <input
                        type="number"
                        v-model="filters.id"
                        placeholder="ID"
                        class="w-20 px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg focus:outline-none focus:border-emerald-500/50"
                    />
                </div>
            </div>

            <!-- Tag search -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Tag') }}</label>
                <input
                    v-model="filters.tag"
                    :placeholder="$t('Enter tag')"
                    class="w-28 px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg focus:outline-none focus:border-emerald-500/50"
                />
            </div>

            <!-- Custom Tag search -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Custom Tag') }}</label>
                <input
                    v-model="filters.customTag"
                    :placeholder="$t('Enter custom tag')"
                    class="w-28 px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white placeholder-white/30 rounded-lg focus:outline-none focus:border-emerald-500/50"
                />
            </div>

            <!-- Member dropdown -->
            <div v-if="members.length > 0" class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Member') }}</label>
                <select
                    v-model="filters.memberId"
                    class="w-32 px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:border-emerald-500/50"
                >
                    <option value="" class="bg-slate-800">{{ $t('All Members') }}</option>
                    <option
                        v-for="member in members"
                        :key="member.user_id"
                        :value="member.user_id"
                        class="bg-slate-800"
                    >
                        {{ member.name }}
                    </option>
                </select>
            </div>

            <!-- Date From -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('From Date') }}</label>
                <input
                    type="date"
                    v-model="filters.dateFrom"
                    class="px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:border-emerald-500/50"
                />
            </div>

            <!-- Date To -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('To Date') }}</label>
                <input
                    type="date"
                    v-model="filters.dateTo"
                    class="px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:border-emerald-500/50"
                />
            </div>

            <!-- Per Page -->
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-semibold text-white/40 uppercase tracking-widest">{{ $t('Per Page') }}</label>
                <select
                    v-model="filters.perPage"
                    class="w-16 px-2 py-1.5 text-xs bg-white/5 border border-white/20 text-white rounded-lg focus:outline-none focus:border-emerald-500/50"
                >
                    <option value="20" class="bg-slate-800">20</option>
                    <option value="50" class="bg-slate-800">50</option>
                    <option value="100" class="bg-slate-800">100</option>
                </select>
            </div>

            <!-- Apply Button -->
            <button
                @click="applyFilters"
                class="px-4 py-1.5 bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-emerald-400 text-xs font-medium rounded-lg transition-colors focus:outline-none focus:ring-1 focus:ring-emerald-500/50"
            >
                {{ $t('Apply') }}
            </button>

            <!-- CSV Format Selector -->
            <div class="flex flex-col gap-2 max-w-md">
                <label class="text-[11px] font-semibold text-white/60 uppercase tracking-wider">{{ $t('Format') }}</label>
                <div class="flex flex-col gap-2">
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-white/90 cursor-pointer">
                            <input type="radio" value="wide" v-model="layout" class="h-3.5 w-3.5 accent-emerald-500" />
                            {{ $t('Number-based') }}
                            <span
                                v-tooltip="$t('Each photo is one row. Tags are counted in columns — for example, the ALCOHOL.bottle column shows how many alcohol bottles were in the photo. Most cells will be empty since most tags don\'t apply to every photo.')"
                                :title="$t('Each photo is one row. Tags are counted in columns — for example, the ALCOHOL.bottle column shows how many alcohol bottles were in the photo. Most cells will be empty since most tags don\'t apply to every photo.')"
                                class="text-white/40 hover:text-white/70 cursor-help select-none"
                                aria-label="Number-based help"
                            >ⓘ</span>
                        </label>
                        <p class="ml-5 mt-0.5 text-[11px] text-white/50 leading-snug">{{ $t('One row per photo. Counts how many of each tag appear in columns. Best for browsing in Excel or Google Sheets.') }}</p>
                    </div>
                    <div class="ml-5 flex flex-col gap-1" :class="layout === 'long' ? 'opacity-50' : ''">
                        <label class="flex items-center gap-1 text-xs text-white/80" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                            <input type="checkbox" v-model="formatSplit" :disabled="layout === 'long'" class="h-3.5 w-3.5 accent-emerald-500" />
                            {{ $t('Separate columns') }}
                            <span
                                v-tooltip="$t('Object, type, and material each get their own column. Recommended for new analyses.')"
                                :title="$t('Object, type, and material each get their own column. Recommended for new analyses.')"
                                class="text-white/40 hover:text-white/70 cursor-help select-none"
                                aria-label="Separate columns help"
                            >ⓘ</span>
                        </label>
                        <p class="ml-5 text-[11px] text-white/50 leading-snug">{{ $t('Object, type, and material in their own columns.') }}</p>
                        <label class="flex items-center gap-1 text-xs text-white/80 mt-1" :class="layout === 'long' ? 'cursor-not-allowed' : 'cursor-pointer'">
                            <input type="checkbox" v-model="formatJoined" :disabled="layout === 'long'" class="h-3.5 w-3.5 accent-emerald-500" />
                            {{ $t('Combined columns') }}
                            <span
                                v-tooltip="$t('Object and type are joined into a single column name like spirits_bottle. Use this if your existing scripts expect the v4 column layout.')"
                                :title="$t('Object and type are joined into a single column name like spirits_bottle. Use this if your existing scripts expect the v4 column layout.')"
                                class="text-white/40 hover:text-white/70 cursor-help select-none"
                                aria-label="Combined columns help"
                            >ⓘ</span>
                        </label>
                        <p class="ml-5 text-[11px] text-white/50 leading-snug">{{ $t('Object and type joined (e.g. spirits_bottle). Use this if your existing scripts expect the v4 layout.') }}</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-1 text-xs font-medium text-white/90 cursor-pointer">
                            <input type="radio" value="long" v-model="layout" class="h-3.5 w-3.5 accent-emerald-500" />
                            {{ $t('Full-detail (one row per tag)') }}
                            <span
                                v-tooltip="$t('Each tag becomes a row. A photo with 3 different tags produces 3 rows, with photo details repeated. The photo_tag_id column lets you group rows back together. Best for analysis tools.')"
                                :title="$t('Each tag becomes a row. A photo with 3 different tags produces 3 rows, with photo details repeated. The photo_tag_id column lets you group rows back together. Best for analysis tools.')"
                                class="text-white/40 hover:text-white/70 cursor-help select-none"
                                aria-label="Full-detail help"
                            >ⓘ</span>
                        </label>
                        <p class="ml-5 mt-0.5 text-[11px] text-white/50 leading-snug">{{ $t('One row per tag, with photo details repeated. Each material, brand, and custom tag becomes its own row. Best for pandas, SQL, Tableau, or any analysis tool.') }}</p>
                    </div>
                </div>
            </div>

            <!-- Export CSV Button -->
            <button
                @click="exportCsv"
                :disabled="exportDisabled"
                class="px-4 py-1.5 bg-white/5 hover:bg-white/10 border border-white/20 text-white text-xs font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-emerald-500/50"
            >
                {{ exporting ? $t('Exporting...') : $t('Export CSV') }}
            </button>
        </div>
    </div>
</template>
