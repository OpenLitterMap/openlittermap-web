<template>
	<div>
		<h3 class="title is-3">Filter temporally:</h3>
		<br>
		<vue-slider
            :data="this.dates"
            ref="dates"
            :value="[this.dates[0], this.dates[this.dates.length-1]]"
            @drag-end="update"
        />
		<br>
		<h3 class="title is-3">Choose a hex size (meters):</h3>
		<vue-slider
            ref="hex"
			:max="500"
			:min="10"
			:value="100"
			@drag-end="update"
		/>
	</div>
</template>

<script>
import vueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'

export default {
    name: 'Options',
    components: {
        vueSlider
    },
    props: ['time', 'index'],
    mounted ()
    {
        let time = JSON.parse(this.time);
        this.dates = Object.keys(time);
        this.min = this.dates[0];
        this.max = this.dates[this.dates.length -1];
    },
    data ()
    {
        return {
            dates: [],
            min: '',
            max: '',
            hexValue: 100
        };
    },

    computed: {

        /**
         * Not sure if we need this anymore
         */
        getSliderId ()
        {
            return 'slider_' + this.index;
        },
    },

    methods: {

        /**
         * When a slider moves, update the min-date, max-date and hex size
         */
        update ()
        {
            let dates = this.$refs.dates.getValue();
            let hex = this.$refs.hex.getValue();

            this.$store.commit('updateCitySlider', {
                dates,
                hex,
                index: this.index
            });
        }
    }
}
</script>
