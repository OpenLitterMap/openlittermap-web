<template>
	<div>
		<nav class="level">
			<div class="level-item">
				<litter-chart
				  :width="300" :height="300"
		          :litter="this.litterData"
		        />
			</div>
			<div class="level-item" v-show="this.totalbrands">
				<brands-chart
				  :width="300" :height="300"
		          :brands="this.brandsData"
		        />
			</div>
		</nav>
	</div>
</template>

<script>
import LitterChart from './LitterChart'
import BrandsChart from './BrandsChart'

export default {
	components: {
		LitterChart,
		BrandsChart
	},
	props: [
		'litterData',
		'brandsData',
		'totalbrands'
	]
}
</script>