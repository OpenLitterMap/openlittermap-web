<template>
	<div class="container has-text-center">
		<time-series
			:width="this.checkWidth"
			:height="500"
			:ppm="this.ppm"
		/>
	</div>
</template>

<script>
import TimeSeries from './TimeSeries'

export default {
    name: 'TimeSeriesContainer',
    props: [
        'ppm'
    ],
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
		}
	}
}
</script>
