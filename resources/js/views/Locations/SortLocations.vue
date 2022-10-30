<template>
    <section class="inner-locations-container" :class="container">

        <!-- Location Navbar -->
		<location-navbar />

	    <!-- v-show is a temp bug fix until cities table has working total_litter column -->
        <!-- v-show="location.total_litter_redis > 0"-->
        <section
            v-for="(location, index) in orderedBy"
            :key="index"
        >
            <br>
            <h1 class="title is-1 has-text-centered world-cup-title">
                #LitterWorldCup
            </h1>

			<div class="hero-body location-container">
        		<div class="columns">

					<!-- Location Metadata -->
					<LocationMetadata
						:index="index"
						:location="location"
						:locationType="locationType"
						:category="sortedBy"
					/>

					<!-- Charts -->
					<div class="column is-half is-offset-1">

						<p class="show-mobile">Drag these across for more options</p>

						<div class="tabs is-center">

							<!-- Components within Tabs -->
							<a v-for="(tab, idx) in tabs"
								:key="idx" v-show="showTab(tab.in_location)"
								@click="loadTab(tab.component)"
								:class="tabClass(tab)">
								{{ tab.title }}
							</a>
						</div>

						<component
							:is="tab"
							:litter_data="location.litter_data"
							:brands_data="location.brands_data"
							:total_brands="location.total_brands"
							:ppm="location.ppm"
							:leaderboard="location.leaderboard"
							:time="location.time"
							@dateschanged="updateUrl"
							:index="index"
							:locationType="locationType"
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
    name: 'SortLocations',
    props: [
        'locationType'
    ],
	components: {
		LocationNavbar,
		LocationMetadata,
		ChartsContainer,
		TimeSeriesContainer,
		Leaderboard,
        Options,
        Download
	},
	data () {
		return {
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
            return (this.orderedBy.length === 0)
                ? 'vh65'
                : '';
        },

		/**
		 * Is the user authenticated?
		 */
		isAuth ()
		{
			return this.$store.state.user.auth;
		},

		/**
         * Return sorted array of locations
         *
         * Determined by this.locations.sortedByOption in LocationNavBar
		 */
		orderedBy ()
		{
			if (this.sortedBy === "alphabetical")
			{
				return this.locations;
			}
			else if (this.sortedBy === 'most-data')
			{
				return sortBy(this.locations, 'total_litter_redis').reverse();
			}
			else if (this.sortedBy === 'most-data-per-person')
			{
				return sortBy(this.locations, 'avg_litter_per_user').reverse();
			}
            else if (this.sortedBy === 'most-recently-updated')
            {
                return sortBy(this.locations, 'updated_at').reverse();
            }
            else if (this.sortedBy === 'total-contributors')
            {
                return sortBy(this.locations, 'total_contributors_redis').reverse();
            }
            else if (this.sortedBy === 'first-created')
            {
                return sortBy(this.locations, 'created_at');
            }
            else if (this.sortedBy === 'most-recently-created')
            {
                return sortBy(this.locations, 'created_at').reverse();
            }

            return [];
		},

		/**
		 * Array of Countries, States, or Cities
		 */
		locations ()
		{
			return this.$store.state.locations.locations;
		},

        /**
         * String that determines how to sort the order of locations
         *
         * Includes:
         * - alphabetical
         * - most-data
         * - most-data-per-person
         * - total-contributors
         * - most-recent
         */
        sortedBy () {
            return this.$store.state.locations.sortLocationsBy;
        }
    },
    methods: {
		/**
		 * Load a tab component: Litter, Leaderboard, Time-series
		 */
		loadTab (tab)
		{
			this.tab = tab;
		},

		/**
		 * Class to return for tab
		 */
		tabClass (tab)
		{
			return (tab === this.tab)
                ? 'l-tab is-active'
                : 'l-tab';
		},

		/**
		 * Show tab depending on location locationType
         *
         * @return boolean
		 */
		showTab (tab)
		{
			return (tab === 'all' || this.locationType === tab);
		},

		/**
		 * todo?
		 */
		updateUrl (url)
		{
            console.log({ url });
		}
	}
}
</script>

<style lang="scss" scoped>

	.inner-locations-container {
        flex: 1;
        background-color: #48c774;
        background-image: linear-gradient(to right bottom, rgba(126, 213, 111, 0.8), rgba(40, 180, 133, 0.8));
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
