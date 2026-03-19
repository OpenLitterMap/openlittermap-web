<template>
    <div class="space-y-3">
        <!-- Time Filter Row -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Desktop pills (hidden on mobile) -->
            <button
                v-for="option in timeOptions"
                :key="option.value"
                class="hidden sm:inline-block px-3 py-1.5 rounded-full text-xs font-semibold border transition cursor-pointer"
                :class="
                    option.value === selectedTime
                        ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30'
                        : 'bg-white/5 text-white/60 border-white/10 hover:bg-white/[0.08] hover:text-white/80'
                "
                @click="changeTimeFilter(option.value)"
            >
                {{ option.label }}
            </button>

            <!-- Mobile select (hidden on desktop) -->
            <select
                v-model="selectedTime"
                class="sm:hidden w-full bg-white/5 border border-white/10 rounded-md px-3 py-2 text-sm text-white appearance-none cursor-pointer focus:outline-none focus:border-emerald-500/50"
                @change="changeTimeFilter(selectedTime)"
            >
                <option v-for="option in timeOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </div>

        <!-- Location Filter Row -->
        <div class="flex flex-wrap items-center gap-2">
            <!-- Location type selector -->
            <select
                v-model="selectedLocationType"
                class="bg-white/5 border border-white/10 rounded-md px-3 py-2 text-sm text-white appearance-none cursor-pointer focus:outline-none focus:border-emerald-500/50"
                @change="changeLocationType"
            >
                <option value="global">{{ $t('Global') }}</option>
                <option value="country">{{ $t('Country') }}</option>
                <option value="state">{{ $t('State') }}</option>
                <option value="city">{{ $t('City') }}</option>
            </select>

            <!-- Country dropdown -->
            <select
                v-if="selectedLocationType !== 'global'"
                v-model="selectedCountryId"
                class="bg-white/5 border border-white/10 rounded-md px-3 py-2 text-sm text-white appearance-none cursor-pointer focus:outline-none focus:border-emerald-500/50"
                @change="changeCountry"
            >
                <option :value="null" disabled>{{ $t('Select country') }}</option>
                <option v-for="country in store.countries" :key="country.id" :value="country.id">
                    {{ country.name }}
                </option>
            </select>

            <!-- State dropdown -->
            <select
                v-if="showStateDropdown"
                v-model="selectedStateId"
                class="bg-white/5 border border-white/10 rounded-md px-3 py-2 text-sm text-white appearance-none cursor-pointer focus:outline-none focus:border-emerald-500/50"
                @change="changeState"
            >
                <option :value="null" disabled>{{ $t('Select state') }}</option>
                <option v-for="state in store.states" :key="state.id" :value="state.id">
                    {{ state.name }}
                </option>
            </select>

            <!-- City dropdown -->
            <select
                v-if="showCityDropdown"
                v-model="selectedCityId"
                class="bg-white/5 border border-white/10 rounded-md px-3 py-2 text-sm text-white appearance-none cursor-pointer focus:outline-none focus:border-emerald-500/50"
                @change="changeCity"
            >
                <option :value="null" disabled>{{ $t('Select city') }}</option>
                <option v-for="city in store.cities" :key="city.id" :value="city.id">
                    {{ city.name }}
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLeaderboardStore } from '../../../../stores/leaderboard/index.js';

const emit = defineEmits(['change']);
const { t } = useI18n();
const store = useLeaderboardStore();

const selectedTime = ref('all-time');
const selectedLocationType = ref('global');
const selectedCountryId = ref(null);
const selectedStateId = ref(null);
const selectedCityId = ref(null);

const timeOptions = [
    { value: 'all-time', label: t('All Time') },
    { value: 'today', label: t('Today') },
    { value: 'yesterday', label: t('Yesterday') },
    { value: 'this-month', label: t('This Month') },
    { value: 'last-month', label: t('Last Month') },
    { value: 'this-year', label: t('This Year') },
    { value: 'last-year', label: t('Last Year') },
];

const showStateDropdown = computed(() => {
    return (selectedLocationType.value === 'state' || selectedLocationType.value === 'city') &&
        selectedCountryId.value !== null;
});

const showCityDropdown = computed(() => {
    return selectedLocationType.value === 'city' && selectedStateId.value !== null;
});

onMounted(() => {
    store.FETCH_COUNTRIES();
});

const changeTimeFilter = (value) => {
    selectedTime.value = value;
    emitChange();
};

const changeLocationType = () => {
    selectedCountryId.value = null;
    selectedStateId.value = null;
    selectedCityId.value = null;
    store.states = [];
    store.cities = [];

    if (selectedLocationType.value === 'global') {
        emitChange();
    }
};

const changeCountry = () => {
    selectedStateId.value = null;
    selectedCityId.value = null;
    store.states = [];
    store.cities = [];

    if (selectedLocationType.value === 'country') {
        emitChange();
    } else if (selectedCountryId.value) {
        store.FETCH_STATES(selectedCountryId.value);
    }
};

const changeState = () => {
    selectedCityId.value = null;
    store.cities = [];

    if (selectedLocationType.value === 'state') {
        emitChange();
    } else if (selectedStateId.value) {
        store.FETCH_CITIES(selectedStateId.value);
    }
};

const changeCity = () => {
    emitChange();
};

const emitChange = () => {
    let locationType = null;
    let locationId = null;

    if (selectedLocationType.value === 'country' && selectedCountryId.value) {
        locationType = 'country';
        locationId = selectedCountryId.value;
    } else if (selectedLocationType.value === 'state' && selectedStateId.value) {
        locationType = 'state';
        locationId = selectedStateId.value;
    } else if (selectedLocationType.value === 'city' && selectedCityId.value) {
        locationType = 'city';
        locationId = selectedCityId.value;
    }

    emit('change', {
        timeFilter: selectedTime.value,
        locationType,
        locationId,
    });
};
</script>
