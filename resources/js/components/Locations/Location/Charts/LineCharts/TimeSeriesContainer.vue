<template>
    <div class="container mx-auto text-center">
        <div class="flex justify-center space-x-4 my-4">
            <button
                :class="[
                    'border-2 rounded px-3 py-1 text-xs cursor-pointer transition-colors duration-300 ease-in-out',
                    selectedButton === 'TOTAL'
                        ? 'bg-green-500 text-white'
                        : 'text-green-500 border-green-500 hover:bg-green-500 hover:text-white',
                ]"
                @click="toggleData('TOTAL')"
            >
                TOTAL
            </button>
            <button
                :class="[
                    'border-2 rounded px-3 py-1 text-xs cursor-pointer transition-colors duration-300 ease-in-out',
                    selectedButton === 'MONTH'
                        ? 'bg-green-500 text-white'
                        : 'text-green-500 border-green-500 hover:bg-green-500 hover:text-white',
                ]"
                @click="toggleData('MONTH')"
            >
                MONTH
            </button>
        </div>

        <TimeSeries :width="checkWidth" :height="500" :ppm="selectedData" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import TimeSeries from './TimeSeries.vue';

// Define props for ppm and total_ppm
const props = defineProps({
    ppm: {
        type: Object,
        required: true,
    },
    total_ppm: {
        type: Array,
        required: true,
    },
});

// Local state for selected button
const selectedButton = ref('TOTAL');

// Toggle data selection locally
function toggleData(selected) {
    selectedButton.value = selected;
}

// Compute chart width based on screen size
const checkWidth = computed(() => (window.screen.width > 1000 ? 600 : 300));

// Compute selected data based on the active button
const selectedData = computed(() => (selectedButton.value === 'MONTH' ? props.ppm : props.total_ppm));
</script>
