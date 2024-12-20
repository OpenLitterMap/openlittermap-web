<template>
	<div>
		<nav class="level">
			<div class="level-item">
				<litter-chart
				  :width="300" :height="300"
		          :litter="this.litter_data"
		        />
			</div>
			<div class="level-item" v-show="this.total_brands">
				<brands-chart
				  :width="300" :height="300"
		          :brands="this.brands_data"
		        />
			</div>
		</nav>
	</div>
</template>

<script>
import LitterChart from './LitterChart.vue'
import BrandsChart from './BrandsChart.vue'

export default {
    name: 'ChartsContainer',
	components: {
		LitterChart,
		BrandsChart
	},
	props: [
		'litter_data',
		'brands_data',
		'total_brands'
	]
}
</script>
