<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-500 via-green-500 to-green-600">
        <GlobalMetaData :loading="loading" />

        <div class="container mx-auto px-4 py-8">
            <!-- Breadcrumbs -->
            <nav v-if="breadcrumbs.length > 1" class="mb-6">
                <ol class="flex items-center space-x-2 text-white">
                    <li v-for="(crumb, index) in breadcrumbs" :key="index">
                        <router-link
                            :to="crumb.path"
                            class="hover:text-white/80 transition-colors"
                            :class="index === breadcrumbs.length - 1 ? 'font-bold' : ''"
                        >
                            {{ crumb.name }}
                        </router-link>
                        <span v-if="index < breadcrumbs.length - 1" class="mx-2">/</span>
                    </li>
                </ol>
            </nav>

            <SortLocations />

            <LocationsList :locations="locations" :loading="loading" @location-click="handleLocationClick" />

            <Pagination
                v-if="!loading && pagination.total > pagination.per_page"
                :pagination="pagination"
                @page-change="loadPage"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useLoading } from 'vue-loading-overlay';
import { useLocationsStore } from '@/stores/locations';

import GlobalMetaData from '@/components/Locations/GlobalMetaData.vue';
import SortLocations from '@/components/Locations/SortLocations.vue';
import LocationsList from '@/components/Locations/LocationsList.vue';
import Pagination from '@/components/Locations/Pagination.vue';

const router = useRouter();
const route = useRoute();
const $loading = useLoading();
const locationsStore = useLocationsStore();

const loading = ref(false);

const locations = computed(() => locationsStore.sortedLocations);
const pagination = computed(() => locationsStore.pagination);
const breadcrumbs = computed(() => locationsStore.breadcrumbs);
const locationType = computed(() => locationsStore.locationType);

const loadPage = async (page) => {
    loading.value = true;
    const loader = $loading.show({ container: null });

    try {
        await locationsStore.fetchLocations(
            locationsStore.locationType,
            locationsStore.stateId || locationsStore.countryId,
            page
        );
    } finally {
        loader.hide();
        loading.value = false;
        window.scrollTo(0, 0);
    }
};

const handleLocationClick = async (location) => {
    locationsStore.setSelectedLocation(location);

    if (locationType.value === 'country') {
        await locationsStore.navigateToCountry(location);
        router.push(`/world/${location.country}`);
    } else if (locationType.value === 'state') {
        await locationsStore.navigateToState(location);
        router.push(`/world/${locationsStore.countryName}/${location.state}`);
    } else if (locationType.value === 'city') {
        locationsStore.navigateToCity(location);
        router.push(`/world/${locationsStore.countryName}/${locationsStore.stateName}/${location.city}/map`);
    }
};

const initializeFromRoute = async () => {
    const { country, state, city } = route.params;

    if (city) {
        // Load city view
        locationsStore.countryName = country;
        locationsStore.stateName = state;
        locationsStore.cityName = city;
        // You might want to fetch the city details here
    } else if (state) {
        // Load cities in state
        locationsStore.countryName = country;
        locationsStore.stateName = state;
        // Fetch state ID first, then cities
        await locationsStore.fetchLocations('city', locationsStore.stateId);
    } else if (country) {
        // Load states in country
        locationsStore.countryName = country;
        // Fetch country ID first, then states
        await locationsStore.fetchLocations('state', locationsStore.countryId);
    } else {
        // Load countries (world cup view)
        await locationsStore.fetchWorldCupData();
    }
};

onMounted(async () => {
    const loader = $loading.show({ container: null });
    loading.value = true;

    try {
        await initializeFromRoute();
    } finally {
        loader.hide();
        loading.value = false;
    }
});
</script>
