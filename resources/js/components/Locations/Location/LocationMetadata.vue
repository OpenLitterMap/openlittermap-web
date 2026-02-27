<template>
    <!-- Left column (metadata) – using Tailwind width utilities -->
    <div class="w-full md:w-1/4">
        <!-- Name, Position and Flag -->
        <div class="flex pb-4 items-center">
            <!-- Flag -->
            <img
                v-if="locationType === 'country'"
                :src="getCountryFlag(location.shortcode)"
                height="15"
                class="pr-6 rounded-sm flex-none"
            />

            <!-- Rank, Location Name, and clickable link -->
            <h2 :class="category === 'A-Z' ? 'text-3xl flex-1 my-0' : 'text-3xl flex-1 my-0'" class="font-medium">
                <a
                    @click="getDataForLocation(location)"
                    :id="location[locationType]"
                    class="text-blue-500 text-center hover:underline cursor-pointer"
                >
                    <!-- Show ordinal position if not alphabetical and within range -->
                    <span v-if="category !== 'A-Z' && index < 100"> {{ positions(index) }} - </span>
                    <span>{{ getLocationName(location) }}</span>
                </a>
            </h2>
        </div>

        <!-- Data Panel -->
        <div class="bg-white shadow rounded divide-y divide-gray-200">
            <!-- Total Litter -->
            <div class="p-2 flex items-center justify-between">
                <span>{{ t('Total Litter') }}:</span>
                <strong class="text-green-500 flex-1 pl-2">
                    {{ location.total_litter_redis.toLocaleString() }}
                </strong>
                <p v-if="locationType === 'country'" class="text-green-500 font-bold">
                    {{ ((location.total_litter_redis ?? 1 / worldStore.total_litter) * 100).toFixed(2) + '% Total' }}
                </p>
            </div>

            <!-- Total Photos -->
            <div class="p-2 flex items-center justify-between">
                <span>{{ t('Total Photos') }}:</span>
                <strong class="text-green-500 flex-1 pl-2">
                    {{ location.total_photos_redis.toLocaleString() }}
                </strong>
                <p v-if="locationType === 'country'" class="text-green-500 font-bold">
                    {{ ((location.total_photos_redis / worldStore.total_photos) * 100).toFixed(2) + '% Total' }}
                </p>
            </div>

            <!-- Created at -->
            <div class="p-2">
                {{ t('Created') }}:
                <strong class="text-green-500">
                    {{ location.diffForHumans }}
                </strong>
            </div>

            <!-- Number of Contributors -->
            <div class="p-2">
                {{ t('Number of Contributors') }}:
                <strong class="text-green-500">
                    {{ location.total_contributors_redis.toLocaleString() }}
                </strong>
            </div>

            <!-- Average Image per Person -->
            <div class="p-2">
                {{ t('Average Image per Person') }}:
                <strong class="text-green-500">
                    {{ location.avg_photo_per_user.toLocaleString() }}
                </strong>
            </div>

            <!-- Average Litter per Person -->
            <div class="p-2">
                {{ t('Average Litter per Person') }}:
                <strong class="text-green-500">
                    {{ location.avg_litter_per_user.toLocaleString() }}
                </strong>
            </div>

            <!-- Created By -->
            <div class="p-2">
                {{ t('Created by') }}:
                <strong class="text-green-500">
                    {{ location.created_by_name }} {{ location.created_by_username }}
                </strong>
            </div>

            <!-- Last Updated -->
            <div class="p-2">
                Last Updated:
                <strong class="text-green-500">
                    {{ location.updatedAtDiffForHumans }}
                </strong>
                <p>
                    by
                    <strong class="text-green-500">
                        {{ location.last_uploader_name }} {{ location.last_uploader_username }}
                    </strong>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useWorldStore } from '../../../stores/world/index.js';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import moment from 'moment';

const props = defineProps({
    index: Number,
    location: Object,
    locationType: String,
    category: String,
});

const location = props.location;
const locationType = props.locationType;
const worldStore = useWorldStore();
const router = useRouter();
const { t } = useI18n();

// Flag images directory
const dir = '/assets/icons/flags/';
// Get the country flag URL (if a valid ISO code is provided)
function getCountryFlag(iso) {
    if (iso) {
        return dir + iso.toLowerCase() + '.png';
    }
    return '';
}

// Navigate based on the clicked location
function getDataForLocation(location) {
    // Clear the locations list
    worldStore.locations = [];

    if (props.locationType === 'country') {
        // For countries, update the country name and navigate to its states
        worldStore.countryName = location.country;
        router.push({ path: `/world/${location.country}` });
    } else if (props.locationType === 'state') {
        // For states, update the state name and navigate to its cities
        worldStore.stateName = location.state;
        router.push({ path: `/world/${worldStore.countryName}/${location.state}` });
    } else if (props.locationType === 'city') {
        // For cities, navigate to the map view – check for a hex property to determine the URL format
        const countryNameVal = worldStore.countryName;
        const stateNameVal = worldStore.stateName;
        const cityName = location.city;
        if (Object.prototype.hasOwnProperty.call(location, 'hex')) {
            router.push({ path: `/world/${countryNameVal}/${stateNameVal}/${cityName}/map/` });
        } else {
            router.push({ path: `/world/${countryNameVal}/${stateNameVal}/${cityName}/map` });
        }
    }
}

// Return the location's name based on the current location type (country, state, or city)
function getLocationName(location) {
    return location[props.locationType];
}

// Return the ordinal position using moment.js
function positions(i) {
    return moment.localeData().ordinal(i + 1);
}
</script>
