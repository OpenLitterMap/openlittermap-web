<template>
    <!-- Pass reactive chartData and chartOptions to the Bar component -->
    <Bar :data="chartData" :options="chartOptions" />
</template>

<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart, registerables } from 'chart.js';

// Register all necessary Chart.js components including scales
Chart.register(...registerables);

// Define the ppm prop
const props = defineProps({
    ppm: {
        type: Object,
        required: true,
    },
});

// Define the months array (you can localize or translate this as needed)
const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Create a computed chart data object that recalculates whenever props.ppm changes
const chartData = computed(() => {
    if (!props.ppm) {
        return { labels: [], datasets: [] };
    }

    const dates = [];
    const values = [];

    // Iterate over each key in ppm and convert the date label
    for (const date in props.ppm) {
        // Assume the date string begins with a two-digit month (e.g., "01", "02", etc.)
        dates.push(months[parseInt(date.substring(0, 2)) - 1] + date.substring(2, 5));
        values.push(props.ppm[date]);
    }

    return {
        labels: dates,
        datasets: [
            {
                label: 'Verified Photos',
                backgroundColor: '#FF0000',
                data: values,
                fill: false,
                borderColor: 'red',
                maxBarThickness: 50,
            },
        ],
    };
});

// Define chart options using the Chart.js v3 configuration format
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#000000',
            },
        },
    },
    scales: {
        x: {
            grid: {
                color: 'rgba(255,255,255,0.5)',
                display: true,
                drawBorder: true,
                drawOnChartArea: false,
            },
            ticks: {
                color: '#000000',
            },
        },
        y: {
            grid: {
                color: 'rgba(255,255,255,0.5)',
                display: true,
                drawBorder: true,
                drawOnChartArea: false,
            },
            ticks: {
                color: '#000000',
            },
        },
    },
};
</script>
