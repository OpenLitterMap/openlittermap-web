<template>
    <div>
        <h3 class="title is-3">Filter temporally:</h3>
        <br />
        <VueSlider :data="dates" ref="datesSlider" :value="[dates[0], dates[dates.length - 1]]" @drag-end="update" />
        <br />
        <h3 class="title is-3">Choose a hex size (meters):</h3>
        <VueSlider ref="hexSlider" :max="500" :min="10" :value="100" @drag-end="update" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import VueSlider from 'vue-3-slider-component';
import { useWorldStore } from '../../../../stores/world/index.js';

// Define props
const props = defineProps({
    time: {
        type: String,
        required: true,
    },
    index: {
        type: [String, Number],
        required: true,
    },
});

// Local state
const dates = ref([]);
const min = ref('');
const max = ref('');
const hexValue = ref(100);

// Template refs for the slider components
const datesSlider = ref(null);
const hexSlider = ref(0);

// Access Vuex store
const worldStore = useWorldStore();

// When the component is mounted, parse the JSON time prop and populate the dates array
onMounted(() => {
    const timeObj = JSON.parse(props.time);
    dates.value = Object.keys(timeObj);
    min.value = dates.value[0];
    max.value = dates.value[dates.value.length - 1];
});

// Computed property (if needed) to generate a slider id
const getSliderId = computed(() => 'slider_' + props.index);

// Update function: called when either slider's drag ends
function update() {
    // Retrieve values from the slider component instances
    const datesVal = datesSlider.value.getValue();
    const hexVal = hexSlider.value.getValue();

    worldStore.updateCitySlider({
        index: props.index,
        dates: datesVal,
        hex: hexVal,
    });
}
</script>
