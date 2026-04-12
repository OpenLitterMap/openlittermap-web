<script setup>
import { ref, computed } from 'vue';
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
    emit('export', buildFilterParams());
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

            <!-- Export CSV Button -->
            <button
                @click="exportCsv"
                :disabled="exporting"
                class="px-4 py-1.5 bg-white/5 hover:bg-white/10 border border-white/20 text-white text-xs font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-1 focus:ring-emerald-500/50"
            >
                {{ exporting ? $t('Exporting...') : $t('Export CSV') }}
            </button>
        </div>
    </div>
</template>
