<template>
    <div class="container has-text-center">

        <div style="text-align: center;">
            <button
                :class="{'outline-button': true, 'selected-button': selectedButton === 'TOTAL'}"
                @click="toggleData('TOTAL')"
            >
                TOTAL
            </button>
            <button
                :class="{'outline-button': true, 'selected-button': selectedButton === 'MONTH'}"
                @click="toggleData('MONTH')"
            >
                MONTH
            </button>
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
            selectedButton: 'TOTAL'
        }
    },
    props: [
        'ppm',
        'total_ppm'
    ],
    methods : {
        toggleData(selected) {
            this.selectedButton = selected;
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
            return this.selectedButton === 'MONTH' ? this.ppm : this.total_ppm;
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

.selected-button {
    background-color: #4CAF50;
    color: white;
}

</style>
