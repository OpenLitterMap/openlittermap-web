<template>
    <section class="locations-main" :class="container">
		<!-- Location Navbar -->
		<location-navbar @selectedCategory="updateCategory($event)" />

	    <!-- v-show is a temp bug fix until cities table has working total_litter column -->
		<section v-for="(location, index) in orderedBy" :key="index"  v-show="location.total_litter_redis > 0">
			<div v-show="category !== 'A-Z'">
				<br>
				<h1 class="title is-1 has-text-centered world-cup-title">
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

							<!-- Components within Tabs -->
							<a v-for="(tab, idx) in tabs" :key="idx" v-show="showTab(tab.in_location)" @click="loadTab(index, tab.component)" :class="tabClass(tab)">
								{{ tab.title }}
							</a>
						</div>

						<component
							:is="tab"
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
			tabs: [
				{ title: this.$t('location.litter'), component: 'ChartsContainer', in_location: 'all' },
				{ title: this.$t('location.time-series'), component: 'TimeSeriesContainer', in_location: 'all'},
				{ title: this.$t('location.leaderboard'), component: 'Leaderboard', in_location: 'all'},
				{ title: this.$t('location.options'), component: 'Options', in_location: 'city'},
				{ title: this.$t('common.download'), component: 'Download', in_location: 'all'}
			]
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
		 * Show tab dependeing on location type
		 */
		showTab (tab) 
		{
			if ( tab === 'all' || this.type === tab) 
			{
				return true
			} 
			else 
			{
				return false
			}
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

	.locations-main {
		background-color: #23d160;
		min-height: 100%;background-color: #23d160; 
		min-height: 100%;
	}

	.l-tab.is-active {
		border-bottom: 2px solid white !important;
	}

	.h65pc {
			height: 65%;
		}

	.world-cup-title {
		color: #34495e;
		
	}
</style>
