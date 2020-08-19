<template>
	<div>
		<h1 class="title is=1">Free and Open Verified Citizen Science Data on Plastic Pollution.</h1>
		<h1 class="title is-3">Let's stop plastic going into the ocean.</h1>
		<button class="button is-large is-danger" @click="stageOne">Download</button>
		<br>
		<br>
		<p>&copy; OpenLitterMap & Contributors.</p>
		<br>
	</div>
</template>

<script>
	export default {

		// props: ['country'],

		// created() {
		// 	console.log(this.country)
		// },

		methods: {

			stageOne() {
				window.location.href = window.location.href + '/get';
			},
		},

		// computed: {
		// 	getSliderName() {
		// 		var slide = 'slider' + this.country;
		// 		return slide;
		// 	},
		// }

	}
</script>