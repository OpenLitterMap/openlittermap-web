<template>
    <div>
        <!-- Desktop Filters -->
        <div class="md:flex justify-evenly max-w-3xl mx-auto pb-4">
            <p
                v-for="option in options"
                :key="option"
                class="border border-gray-300 px-4 py-2 rounded cursor-pointer text-black font-normal"
                :class="option === selected ? 'bg-green-500 ' : 'bg-white'"
                @click="changeOption(option)"
            >{{ getNameForOption(option) }}</p>
        </div>

        <!-- Mobile Filters -->
        <div class="block md:hidden">
            <select
                v-model="selected"
                class="w-full border border-gray-300 px-4 py-2 rounded mb-4"
                @change="optionChanged"
            >
                <option
                    v-for="option in options"
                    :key="option"
                    :value="option"
                >
                    {{ getNameForOption(option) }}
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useLeaderboardStore } from '../../../../stores/leaderboard/index.js';

const leaderboardStore = useLeaderboardStore();
const selected = ref('today');
const processing = ref(false);

const options = [
    'all-time',
    'today',
    'yesterday',
    'this-month',
    'last-month',
    'this-year',
];

const props = defineProps({
    locationType: {
        type: String,
        default: null,
        required: false
    },
    locationId: {
        type: Number,
        default: null,
        required: false
    },
});

/**
 * Change option logic
 */
const changeOption = async (option) => {

    console.log({ option });
    selected.value = option;

    processing.value = true;

    if (props.locationId && props.locationType) {
        await leaderboardStore.GET_USERS_FOR_LOCATION_LEADERBOARD({
            timeFilter: option,
            locationId: props.locationId,
            locationType: props.locationType,
        });
    } else {
        await leaderboardStore.GET_USERS_FOR_GLOBAL_LEADERBOARD(option);
    }

    processing.value = false;
};

/**
 * Get name for option
 *
 * Needs Translation
 */
const getNameForOption = (option) => {
    const mapping = {
        'today': 'Today',
        'yesterday': 'Yesterday',
        'this-month': 'This Month',
        'last-month': 'Last Month',
        'this-year': 'This Year',
        'all-time': 'All Time',
    };
    return mapping[option] || '';
};

/**
 * Handle mobile view option change
 */
const optionChanged = async () => {
    processing.value = true;

    // await leaderboardStore.GET_GLOBAL_LEADERBOARD(selected.value);

    processing.value = false;
};
</script>
