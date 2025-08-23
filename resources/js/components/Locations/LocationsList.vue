<template>
    <div class="py-8">
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center min-h-[400px]">
            <div class="relative">
                <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-white"></div>
                <span class="text-white mt-4 block text-center">Loading locations...</span>
            </div>
        </div>

        <!-- Locations Grid -->
        <div v-else-if="locations.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <LocationCard
                v-for="(location, index) in locations"
                :key="location.id"
                :location="location"
                :index="index"
                :location-type="locationType"
                @click="$emit('location-click', location)"
            />
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-16">
            <svg class="mx-auto h-24 w-24 text-white/50 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                />
            </svg>
            <h3 class="text-xl font-semibold text-white mb-2">No locations found</h3>
            <p class="text-white/70">Try adjusting your filters or check back later</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useWorldStore } from '@/stores/world';
import LocationCard from './LocationCard.vue';

const worldStore = useWorldStore();

const props = defineProps({
    locations: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['location-click']);

const locationType = computed(() => worldStore.locationType || 'country');
</script>
