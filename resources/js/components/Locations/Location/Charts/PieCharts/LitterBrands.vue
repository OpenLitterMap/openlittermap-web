<template>
    <Doughnut :data="chartData" :options="chartOptions" />
</template>

<script setup>
import { ref, computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart, registerables } from 'chart.js';

// Register all Chart.js components
Chart.register(...registerables);

// Define props
const props = defineProps({
    brands: {
        type: Object,
        required: true,
    },
});

// Compute a sorted array of brand objects from the input,
// then slice out the top 9 entries (only if value > 0)
const sortedBrands = computed(() => {
    const arr = [];
    Object.keys(props.brands).forEach((key) => {
        if (props.brands[key]) {
            arr.push({ key, value: props.brands[key] });
        }
    });
    // Sort in descending order by value
    arr.sort((a, b) => b.value - a.value);
    return arr.slice(0, 9);
});

// Derive separate arrays for keys and values, filtering out non-positive values
const topBrands = computed(() => {
    const keys = [];
    const values = [];
    sortedBrands.value.forEach((item) => {
        if (item.value > 0) {
            keys.push(item.key);
            values.push(item.value);
        }
    });
    return { keys, values };
});

// Compute background colors based on the number of top brands.
// (The returned array length is one more than the number of items as per the original logic.)
const myComputedBackgrounds = computed(() => {
    const len = topBrands.value.keys.length;
    switch (len) {
        case 0:
            return ['#C28535'];
        case 1:
            return ['#C28535', '#8AAE56'];
        case 2:
            return ['#C28535', '#8AAE56', '#B66C46'];
        case 3:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741'];
        case 4:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000'];
        case 5:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6'];
        case 6:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF'];
        case 7:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE'];
        case 8:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE', '#ccc'];
        case 9:
            return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE', '#000000'];
        default:
            return ['#C28535'];
    }
});

// Build the chart data object for the Doughnut component
const chartData = computed(() => {
    return {
        labels: topBrands.value.keys,
        datasets: [
            {
                label: 'Collected',
                data: topBrands.value.values,
                backgroundColor: myComputedBackgrounds.value,
            },
        ],
    };
});

// Define chart options using Chart.js v3 syntax
const chartOptions = ref({
    responsive: false,
    maintainAspectRatio: true,
    plugins: {
        legend: {
            labels: {
                color: '#ffffff', // In Chart.js v3, use "color" instead of "fontColor"
            },
        },
    },
});
</script>
