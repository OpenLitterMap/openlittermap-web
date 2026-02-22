<template>
    <div class="container mx-auto text-center">
        <div class="flex justify-center space-x-4 my-4">
            <button
                :class="selectedButton === 'TOTAL' ? activeButtonClasses : inactiveButtonClasses"
                @click="toggleData('TOTAL')"
            >
                TOTAL
            </button>
            <button
                :class="selectedButton === 'MONTH' ? activeButtonClasses : inactiveButtonClasses"
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
        type: Number,
        required: true,
    },
    total_ppm: {
        type: Number,
        required: true,
    },
});

// Local state for the selected button
const selectedButton = ref('TOTAL');

// Toggle the button selection
function toggleData(selected) {
    selectedButton.value = selected;
}

// Compute the width based on screen size
const checkWidth = computed(() => (window.screen.width > 1000 ? 600 : 300));

// Compute the data to pass based on the active button
const selectedData = computed(() => (selectedButton.value === 'MONTH' ? props.ppm : props.total_ppm));

// Tailwind CSS classes for button states
const activeButtonClasses =
    'bg-green-500 text-white border-2 border-green-500 rounded px-3 py-1 text-xs transition-colors duration-300';
const inactiveButtonClasses =
    'text-green-500 border-2 border-green-500 rounded px-3 py-1 text-xs transition-colors duration-300 hover:bg-green-500 hover:text-white';
</script>
