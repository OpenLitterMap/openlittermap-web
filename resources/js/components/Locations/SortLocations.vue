<template>
    <section :class="['flex-1 bg-green-500 bg-gradient-to-br from-green-400 to-green-600', container]">
        <LocationNavbar />

        <Location
            v-for="(location, index) in orderedBy"
            :key="index"
            class="mb-8"
            :location="location"
            :locationType="locationType"
            :index="index"
        ></Location>
    </section>
</template>

<script setup>
import { ref, computed } from 'vue';
const { sortBy } = window._;
import LocationNavbar from './Location/LocationNavBar.vue';
import Location from './Location/Location.vue';

import { useWorldStore } from '@/stores/world';
const worldStore = useWorldStore();

const locationType = ref('country');

// Compute a container class to adjust height if there are no locations
const container = computed(() => (orderedBy.value.length === 0 ? 'min-h-[65vh]' : ''));

// Compute sorted locations based on the current sort option in the store
const orderedBy = computed(() => {
    const sortedBy = worldStore.sortLocationsBy;
    const locations = worldStore.locations;
    if (sortedBy === 'alphabetical') {
        return locations;
    } else if (sortedBy === 'most-data') {
        return sortBy(locations, 'total_litter_redis').reverse();
    } else if (sortedBy === 'most-data-per-person') {
        return sortBy(locations, 'avg_litter_per_user').reverse();
    } else if (sortedBy === 'most-recently-updated') {
        return sortBy(locations, 'updated_at').reverse();
    } else if (sortedBy === 'total-contributors') {
        return sortBy(locations, 'total_contributors_redis').reverse();
    } else if (sortedBy === 'first-created') {
        return sortBy(locations, 'created_at');
    } else if (sortedBy === 'most-recently-created') {
        return sortBy(locations, 'created_at').reverse();
    }
    return [];
});
</script>
