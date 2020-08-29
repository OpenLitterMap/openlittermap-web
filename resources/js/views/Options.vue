<template>
	<div>
		<h3 class="title is-3">Filter temporally:</h3>
		<br>
		<vue-slider
			:id="getSliderName"
			:data="this.dates"
			ref="sliderDates"
			:value="[this.dates[0], this.dates[this.dates.length-1]]"
			@drag-end="getValues"
		/>
		<br>
		<h3 class="title is-3">Choose a hex size (meters):</h3>
		<vue-slider
			:max="500"
			:min="10"
			:value="100"
			@drag-end="getHex"
		/>
	</div>
</template>

<script>
	import vueSlider from 'vue-slider-component';

	export default {
		components: {
			vueSlider
		},
		props: ['time', 'country', 'state', 'city'],
		mounted() {
			// console.log(this.time);
			// console.log(this.city);
			// console.log(this.state);
			// console.log(this.country);
			var arr = JSON.parse(this.time);
			var dates = Object.keys(arr);
			this.dates = dates;
			this.min = this.dates[0];
			this.max = this.dates[this.dates.length -1];
		},

		data() {
			return {
				dates: [],
				min: '',
				max: '',
				hexValue: 100
			};
		},

		computed: {
			getSliderName() {
				return 'slider' + this.city;
			},
			getFirstKey() {
				return Math.random(1,200);
			},
			getSecondKey() {
				return Math.random(1,200);
			}
		},

		methods: {

			getHex(slider) {
				const asd = '/world/' + this.country + '/' + this.state + '/' + this.city + '/' + 'map' + '/' + this.min + '/' + this.max + '/' + slider.val;

				var e = document.getElementById(this.city);
				e.href = asd;
			},

			getValues(slider) {

				this.min = slider.val[0];
				this.max = slider.val[1];

				const url = '/world/' + this.country + '/' + this.state + '/' + this.city + '/' + 'map' + '/' + this.min + '/' + this.max + '/' + this.hexValue;

				// console.log(url);
				var e = document.getElementById(this.city);
				e.href = url;

				// this.$emit('dateschanged', url);
			}
		}
	}
</script>
