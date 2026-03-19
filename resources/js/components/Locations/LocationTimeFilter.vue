<template>
    <div class="flex flex-wrap items-center gap-2">
        <!-- Period presets -->
        <button
            v-for="preset in periodPresets"
            :key="preset.value"
            class="px-2.5 py-1 text-xs rounded-md transition-colors"
            :class="
                !store.year && store.period === preset.value
                    ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                    : 'text-white/50 hover:text-white/80 hover:bg-white/5 border border-transparent'
            "
            @click="onPeriodChange(preset.value)"
        >
            {{ preset.label }}
        </button>

        <!-- Divider -->
        <div class="w-px h-5 bg-white/10 mx-1 hidden sm:block"></div>

        <!-- Year dropdown -->
        <div class="relative">
            <select
                :value="store.year ?? ''"
                class="bg-white/5 border border-white/10 rounded-md pl-2.5 pr-7 py-1 text-xs text-white/70 focus:outline-none focus:border-emerald-500/50 appearance-none cursor-pointer"
                @change="onYearChange($event.target.value)"
            >
                <option value="">{{ $t('Year') }}</option>
                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
            </select>
            <svg
                class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-white/30"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Sort dropdown -->
        <div class="relative ml-auto">
            <select
                :value="store.sortKey"
                class="bg-white/5 border border-white/10 rounded-md pl-2.5 pr-7 py-1 text-xs text-white/70 focus:outline-none focus:border-emerald-500/50 appearance-none cursor-pointer"
                @change="onSortChange($event.target.value)"
            >
                <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>
            <svg
                class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3 h-3 text-white/30"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Search -->
        <div v-if="showSearch" class="relative">
            <input
                :value="modelValue"
                type="text"
                :placeholder="searchPlaceholder"
                class="w-40 sm:w-48 pl-7 pr-3 py-1 text-xs rounded-md bg-white/5 border border-white/10 text-white placeholder-white/30 focus:outline-none focus:border-emerald-500/50 transition"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <svg
                class="absolute left-2 top-1/2 -translate-y-1/2 h-3 w-3 text-white/30"
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
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLocationsStore } from '@/stores/locations';

const props = defineProps({
    modelValue: { type: String, default: '' },
    showSearch: { type: Boolean, default: false },
    childrenType: { type: String, default: null },
});

const emit = defineEmits(['change', 'update:modelValue']);
const { t } = useI18n();
const store = useLocationsStore();

const periodPresets = [
    { value: 'all', label: t('All Time') },
    { value: 'today', label: t('Today') },
    { value: 'yesterday', label: t('Yesterday') },
    { value: 'this_month', label: t('This Month') },
    { value: 'last_month', label: t('Last Month') },
    { value: 'this_year', label: t('This Year') },
];

const sortOptions = [
    { value: 'tags:desc', label: t('Most Tags') },
    { value: 'avg_tags_per_person:desc', label: t('Most Tags / Person') },
    { value: 'photos:desc', label: t('Most Photos') },
    { value: 'contributors:desc', label: t('Most Contributors') },
    { value: 'name:asc', label: t('A–Z') },
    { value: 'created_at:asc', label: t('First Created') },
    { value: 'created_at:desc', label: t('Recently Created') },
    { value: 'last_updated_at:desc', label: t('Recently Updated') },
];

const currentYear = new Date().getFullYear();
const yearOptions = Array.from({ length: currentYear - 2015 + 1 }, (_, i) => currentYear - i);

const pluralMap = {
    country: 'countries',
    state: 'states',
    city: 'cities',
};

const searchPlaceholder = computed(() => {
    const type = props.childrenType;
    const plural = pluralMap[type] ?? `${type}s`;
    return `Search ${plural}…`;
});

function onPeriodChange(value) {
    store.setPeriod(value);
    emit('change');
}

function onYearChange(value) {
    store.setYear(value ? parseInt(value) : null);
    emit('change');
}

function onSortChange(key) {
    store.setSortFromKey(key);
}
</script>
