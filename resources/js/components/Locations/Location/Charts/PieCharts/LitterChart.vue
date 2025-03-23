<template>
    <!-- Render the Doughnut chart with reactive data and options -->
    <Doughnut :data="chartData" :options="chartOptions" />
</template>

<script setup>
import { ref, watchEffect } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart, registerables } from 'chart.js';

// Register all Chart.js components
Chart.register(...registerables);

// Define props
const props = defineProps({
    litter: {
        type: Object,
        required: true,
    },
});

// Predefined colors array
const colors = ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#BFE5A6', '#FFFFFF', '#BF00FE', '#add8e6'];

// Reactive chart data object
const chartData = ref({
    labels: [],
    datasets: [
        {
            label: 'Collected',
            data: [],
            backgroundColor: [],
        },
    ],
});

// Reactive chart options using Chart.js v3 syntax
const chartOptions = ref({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            labels: {
                // In Chart.js v3, set the label color with "color" instead of "fontColor"
                color: '#ffffff',
            },
        },
    },
});

// Update chartData whenever props.litter changes
watchEffect(() => {
    // Clear previous data
    chartData.value.labels = [];
    chartData.value.datasets[0].data = [];
    chartData.value.datasets[0].backgroundColor = [];

    let index = 0;
    for (const key in props.litter) {
        if (props.litter[key]) {
            chartData.value.labels.push(key);
            chartData.value.datasets[0].data.push(props.litter[key]);
            // Use color from the array, fallback if index exceeds available colors
            chartData.value.datasets[0].backgroundColor.push(colors[index] || '#000000');
            index++;
        }
    }
});
</script>
