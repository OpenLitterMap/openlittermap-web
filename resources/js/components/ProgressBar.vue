<template>
	<progress
		class="progress is-large is-success"
		:value="currentValue"
		:max="100"
	/>
</template>

<script>
export default {
	name: 'ProgressBar',
	props: ['currentxp', 'xpneeded', 'startingxp'],
	computed: {
		currentValue ()
		{
			let range = this.xpneeded - this.startingxp;
			let startVal = this.currentxp - this.startingxp;
			return (startVal * 100) / range; // percentage
		},
	}
}
</script>
