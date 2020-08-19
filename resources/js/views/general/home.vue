<template>
	<div>
		<!-- First -->
		<section class="hero is-medium">
			<div class="hero-body">
			  <div class="container">
			    <div class="columns">
						<div class="column is-one-third is-offset-1">
								<p class="title main-title">You become the litter warden.</p>
						</div>
						<div class="column">
							<img src="/assets/global-pollution.jpeg" style="max-width: 75%;" />
						</div>
					</div>

					<img src="/assets/icons/ios-1.png" style="max-width: 25%;" />
					<img src="/assets/icons/android-1.png" style="max-width: 25%;" />

			  </div>
			</div>
		</section>

		<!-- Second -->
		<section class="hero is-medium is-info">
			<div class="hero-body">
			  <div class="container">
			    <div class="columns">
						<div class="column is-half is-offset-2">
							<p class="title is-3">OpenLitterMap empowers you to map and share data on litter & plastic pollution anywhere.</p>
						</div>
						<div class="column">
						</div>
					</div>
			  </div>
			</div>
		</section>
	</div>
</template>

<script>
import GlobalMap from '../global/GlobalMap'

export default {
	name: 'home',
	components: { GlobalMap },
	data() {
		return {

		};
	},
	computed: {

	},
	methods: {

	}
}
</script>

<style lang="scss">
.main-title {
	color: black;
	font-size: 64px;
	font-weight: 700;
}
</style>