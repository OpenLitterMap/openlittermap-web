<template name="date-slider">
	<div>
		<vue-slider v-model="value"></vue-slider>
	</div>
</template>
<script>
import vueSlider from 'vue-slider-component';

export default {
	components: {
		vueSlider
	},

	data: function() {
		return {
			value: 0
		}
	}
}
</script>