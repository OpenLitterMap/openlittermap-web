<template name="verification-bar">
	<progress class="progress is-info" :value="isValue" max="1">isValue</progress>
</template>

<script>
	export default {

		props: ['verification'],
		
		// data() {
		// 	return {
		// 		// verification: ''
		// 	}
		// },

		computed: {
			isValue() {
				return this.verification;
			}
		},

		events: {
			updateVerificationProgress() {
				console.log('updating with event');
				// this.verification += 0.1;
				// console.log(this.verification);
			}
		}

	}
</script>