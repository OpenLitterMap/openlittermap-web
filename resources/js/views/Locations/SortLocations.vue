<template>
    <section :class="container" style="background-color: #23d160; min-height: 100%;">
		<!-- Location Navbar -->
		<location-navbar @selectedCategory="updateCategory($event)" />

	    <!-- v-show is a temp bug fix until cities table has working total_litter column -->
		<section v-for="location, index in orderedBy" v-show="location.total_litter_redis > 0">
			<div v-show="category !== 'A-Z'">
				<br>
				<h1 style="color: #34495e;" class="title is-1 has-text-centered">
					#LitterWorldCup
				</h1>
			</div>

			<div class="hero-body location-container">
        		<div class="columns">
		
					<!-- Location Metadata -->
					<location-metadata :index="index" :location="location" :type="type" :category="category" />

					<!-- Charts -->
					<div class="column is-half is-offset-1">

						<p class="show-mobile">Drag these across for more options</p>

						<div class="tabs is-center">

							<!-- Pie Charts -->
							<a @click="loadTab(index, 'litter')" :class="tabClass('litter')">
								{{ $t('location.litter') }}
							</a>

							<!-- Leaderboard -->
							<a @click="loadTab(index, 'leaderboard')" :class="tabClass('leaderboard')">
								{{ $t('location.leaderboard') }}
							</a>

							<!-- Time-series -->
							<a @click="loadTab(index, 'time_series')" :class="tabClass('time_series')">
								{{ $t('location.time-series') }}
							</a>

							<!-- Options (City only) -->
							<a
								v-show="type === 'city'"
								@click="loadTab(index, 'options')" :class="tabClass('options')">
								{{ $t('location.options') }}
							</a>

							<!-- Download -->
							<a
								@click="loadTab(index, 'download')" :class="tabClass('download')">
								{{ $t('common.download') }}
							</a>
						</div>

						<component
							:is="tabs[tab]"
							:litter_data="location.litter_data"
							:brands_data="location.brands_data"
							:total_brands="location.total_brands"
							:ppm="location.photos_per_month"
							:leaderboard="location.leaderboard"
							:time="location.time"
							@dateschanged="updateUrl"
							:index="index"
							:type="type"
							:locationId="location.id"
						/>

					</div>
				</div>
			</div>

		</section>

    </section>
</template>

<script>
let sortBy = require('lodash.sortby')

import LocationNavbar from '../../components/Locations/LocationNavBar'
import LocationMetadata from '../../components/Locations/LocationMetadata'
import ChartsContainer from '../../components/Locations/Charts/PieCharts/ChartsContainer'
import TimeSeriesContainer from '../../components/Locations/Charts/TimeSeries/TimeSeriesContainer'
import Leaderboard from '../../components/Locations/Charts/Leaderboard/Leaderboard'
import Options from '../../components/Locations/Charts/Options/Options'
import Download from '../../components/Locations/Charts/Download/Download'


export default {
	props: ['type'], // country, state, or city
	name: 'SortLocations',
	components: {
		LocationNavbar,
		LocationMetadata,
		ChartsContainer,
		TimeSeriesContainer,
		Leaderboard,
        Options,
        Download
	},
	data ()
	{
		return {
			'category': this.$t('location.most-data'),
			tab: '',
			tabs: {
				litter: 'ChartsContainer',
				time_series: 'TimeSeriesContainer',
				leaderboard: 'Leaderboard',
                options: 'Options',
                download: 'Download'
			}
		};
	},
    computed: {

		/**
         * Expand container to fullscreen when orderedBy is empty/loading
         */
	    container ()
        {
            return this.orderedBy.length === 0 ? 'vh65' : '';
        },
		
		/**
		 * Is the user authenticated?
		 */
		isAuth ()
		{
			return this.$store.state.user.auth;
		},

		/**
		 * We can sort all locations A-Z, Most Open Data, or Most Open Data Per Person
		 * We can add new options too, created_at, etc.
		 */
		orderedBy ()
		{
			if (this.category === "A-Z")
			{
				return this.locations;
			}
			else if (this.category === this.$t('location.most-data'))
			{   
				return sortBy(this.locations, 'total_litter_redis').reverse();
			}
			else if (this.category === this.$t('location.most-data-person'))
			{
				return sortBy(this.locations, 'avg_litter_per_user').reverse();
			}
		},

		/**
		 * Countries, States, or Cities
		 */
		locations ()
		{
			return this.$store.state.locations.locations;
		}
	}, 
	methods: {

		/**
		* Load a tab component Litter, Leaderboard, Time-series
		*/
		loadTab (index, tab)
		{
			this.tab = tab;
		},

		/**
		 * Class to return for tab
		 */
		tabClass (tab)
		{
			return tab === this.tab ? 'l-tab is-active' : 'l-tab';
		},
		
		/**
		 *
		 */
		updateUrl (url)
		{
            console.log({ url });
		},

		/**
		* Update selected category from LocationNavBar component
		*/
		updateCategory (updatedCategory) 
		{
			this.category = updatedCategory
		},
	}
}
</script>

<style lang="scss" scoped>

	.h65pc {
			height: 65%;
		}
</style>
