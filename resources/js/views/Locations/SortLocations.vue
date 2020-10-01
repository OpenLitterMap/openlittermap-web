<template>
    <section :class="container" style="background-color: #23d160;">
        <div class="container w100">
            <div class="control locations-control">
                <br>
                <div class="select">
                    <select v-model="category">
                        <option v-for="cat in catnames">{{ cat }}</option>
                    </select>
                </div>
            </div>
        </div>

        <section v-for="location, index in orderedBy">
            <div v-show="category !== 'A-Z'">
                <br>
                <h1 style="text-align: center; color: #34495e;" class="title is-1">
                    #LitterWorldCup
                </h1>
            </div>

            <div class="hero-body location-container">
                <div class="columns">

                    <!-- Left column (metadata) -->
                    <div class="column is-3">

                        <!-- Name, Position and Flag-->
                        <div style="display: flex; padding-bottom: 1em;">

                            <!-- Flag -->
                            <img v-if="type === 'country' && category !== 'A-Z'"
                                 height="15"
                                 style="padding-right: 1.5em; border-radius: 1px; flex: 0.1;"
                                 :src="getCountryFlag(location.shortcode)"
                            />

                            <h2 :class="textSize">
                                <router-link :to="goTo(index)" :id="location[type]" class="is-link has-text-centered location-title">
                                    <!-- Position -->
                                    <span v-show="category !== 'A-Z' && index < 30">{{ positions(index) }} -</span>
                                    <!-- Name -->
                                    <span>{{ getName(location) }}</span>
                                </router-link>
                            </h2>
                        </div>

                        <!-- Location metadata -->
                        <div class="panel">
                            <div class="panel-block">{{ $t('location.maps10') }}: <strong class="green">&nbsp; {{ location['total_litter'].toLocaleString() }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps11') }}: <strong class="green">&nbsp; {{ location['total_images'].toLocaleString() }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps12') }}: <strong class="green">&nbsp; {{ location['diffForHumans'] }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps13') }}: <strong class="green">&nbsp; {{ location['total_contributors'].toLocaleString() }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps14') }}: <strong class="green">&nbsp; {{ location['avg_photo_per_user'].toLocaleString() }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps15') }}: <strong class="green">&nbsp; {{ location['avg_litter_per_user'].toLocaleString() }}</strong></div>
                            <div class="panel-block">{{ $t('location.maps16') }}: <strong class="green">&nbsp; {{ location['created_by_name'] }} {{ location['created_by_username'] }}</strong></div>
                        </div>
                    </div>

                    <!-- Charts -->
                    <div class="column is-half is-offset-1">

                        <p class="show-mobile">Drag these across for more options</p>

                        <div class="tabs is-center">

                            <!-- Pie Charts -->
                            <a @click="loadTab(index, 'litter')" :class="tabClass('litter')">
                                {{ $t('location.maps9') }}
                            </a>

                            <!-- Leaderboard -->
                            <a @click="loadTab(index, 'leaderboard')" :class="tabClass('leaderboard')">
                                {{ $t('location.maps17') }}
                            </a>

                            <!-- Time-series -->
                            <a @click="loadTab(index, 'time_series')" :class="tabClass('time_series')">
                                {{ $t('location.maps18') }}
                            </a>

                            <!-- Options (City only) -->
                            <a
                                v-show="type === 'city'"
                                @click="loadTab(index, 'options')" :class="tabClass('options')">
                                Options
                            </a>

                            <!-- Download (Auth only) -->
                            <a
                                v-show="isAuth"
                                @click="loadTab(index, 'download')" :class="tabClass('download')">
                                {{ $t('location.maps19') }}
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
                        />

                    </div>
                </div>
            </div>
        </section>
    </section>
</template>

<script>
import moment from 'moment'
let sortBy = require('lodash.sortby')

import ChartsContainer from '../../components/Locations/Charts/PieCharts/ChartsContainer'
import TimeSeriesContainer from '../../components/Locations/Charts/TimeSeries/TimeSeriesContainer'
import Leaderboard from '../../components/Locations/Charts/Leaderboard/Leaderboard'
import Options from '../../components/Locations/Charts/Options/Options'

export default {
	props: ['type'], // country, state, or city
	name: 'SortLocations',
	components: {
		ChartsContainer,
		TimeSeriesContainer,
		Leaderboard,
        Options
	},
	data ()
	{
		return {
			'category': 'Most Open Data',
			'catnames': [
				'A-Z',
				'Most Open Data',
				'Most Open Data Per Person'
			],
			'openDataOrder': null,
			'mostLitterPP': null,
			dir: '/assets/icons/flags/',
			tab: 'litter',
			tabs: {
				litter: 'ChartsContainer',
				time_series: 'TimeSeriesContainer',
				leaderboard: 'Leaderboard',
                options: 'Options'
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
		 * Name of the country (if we are viewing States, Cities)
		 */
		country ()
		{
			return this.$store.state.locations.country;
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

			else if (this.category === "Most Open Data")
			{
				return sortBy(this.locations, 'total_litter').reverse();
			}

			else if (this.category === "Most Open Data Per Person")
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
		},

		/**
		 * Name of the state (if we are viewing cities)
		 */
		state ()
		{
			return this.$store.state.locations.state;
		},

		/**
		 * We have a smaller font-size when a flag is present
		 */
		textSize ()
		{
			return this.category === 'A-Z' ? 'title is-1 flex-1 ma' : 'title is-3 flex-1 ma';
		}
	},
	methods: {

		/**
		 * On Countries.vue, each country gets a flag when sorted by most open data
		 */
		getCountryFlag (iso)
		{
			iso = iso.toLowerCase();

			return this.dir + iso + '.png';
		},

		/**
		 * Name of a location
		 */
		getName (location)
		{
			return location[this.type];
		},

		/**
		 * When user clicks on a location name
		 */
		goTo (index)
		{
			if (this.type === 'country')
			{
			    let country = this.orderedBy[index].country;

			    this.$store.commit('setCountry', country);

				return '/world/' + country;
			}

			else if (this.type === 'state')
			{
			    let state = this.orderedBy[index].state;

			    this.$store.commit('setState', state);

				return '/world/' + this.country + '/' + state;
			}

			else if (this.type === 'city')
			{
				return '/world/' + this.country + '/' + this.state + '/' + this.orderedBy[index].city + '/map';
			}
		},

		/**
		 * Load a tab component Litter, Leaderboard, Time-series
		 */
		loadTab (index, tab)
		{
			this.tab = tab;
		},

		/**
		 *
		 */
		positions (i)
		{
			return moment.localeData().ordinal(i + 1);
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
		}
	}
}
</script>

<style lang="scss" scoped>

	.green {
		color: green !important;
	}

	.panel-block {
		color: black;
		background-color: white;
	}

    .location-container {
        padding-top: 3em;
        padding-bottom: 5em;
    }

    .locations-control {
        text-align: right;
    }

    .location-title:hover {
        color: green !important;
        border-bottom: 1px solid green;
    }

	.l-tab.is-active {
		border-bottom: 2px solid white !important;
	}

    /* Small devices */
    @media screen and (max-width: 768px)
    {

        .locations-control {
            text-align: center;
        }
    }

</style>
