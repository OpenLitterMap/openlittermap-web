<template>
    <div class="container has-text-center">

        <div style="text-align: center;">
            <button class="outline-button" @click="toggleData('MONTH')">MONTH</button>
            <button class="outline-button" @click="toggleData('TOTAL')">TOTAL</button>
        </div>

        <time-series
          :width="this.checkWidth"
          :height="500"
          :ppm="selectedData"
        />
	  </div>
</template>

<script>
import TimeSeries from './TimeSeries'

export default {
    name: 'TimeSeriesContainer',
    data ()
    {
        return {
            selectedDataType: 'TOTAL', // Default value for the toggle
        }
    },
    props: [
        'ppm',
        'total_ppm'
    ],
    methods : {
        toggleData(dataType) {
            this.selectedDataType = dataType;
        },
    },
	components: {
        TimeSeries
    },
	computed: {
        /**
         * This component has a different width depending on screen width
         */
        checkWidth ()
        {
          return window.screen.width > 1000 ? 600 : 300;
        },

        /**
         *
         */
        selectedData() {
            return this.selectedDataType === 'TOTAL' ? this.total_ppm : this.ppm;
        },
    }
}
</script>

<style>
.outline-button {
    color: #4CAF50;
    border: 2px solid #4CAF50;
    padding: 6px 13px;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.outline-button:hover {
    background-color: #4CAF50;
    color: #fff;
}

</style>
