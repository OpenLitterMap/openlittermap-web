<template>
    <!-- Left column (metadata) -->
    <div class="column is-3">

        <!-- Name, Position and Flag-->
        <div class="flex pb1">

            <!-- Flag -->
            <img v-if="locationType === 'country'"
                height="15"
                class="img-flag"
                :src="getCountryFlag(location.shortcode)"
            />

            <!-- Rank, Location Name, href -->
            <h2 :class="textSize">
                <a
                    @click="getDataForLocation(location)"
                    :id="location[locationType]"
                    class="is-link has-text-centered location-title"
                >
                    <!-- Position -->
                    <span v-show="category !== 'A-Z' && index < 100">{{ positions(index) }} -</span>
                    <!-- Name -->
                    <span>{{ getLocationName(location) }}</span>
                </a>
            </h2>
        </div>

        <!-- White box with data -->
        <div class="panel">
            <!-- Total Litter -->
            <div class="panel-block">{{ $t('location.total-verified-litter') }}:
                <strong class="green flex-1">&nbsp;
                    {{ location['total_litter_redis'].toLocaleString() }}
                </strong>

                <p v-if="locationType === 'country'" class="total-photos-percentage">
                    {{ (location['total_litter_redis'] / this.$store.state.locations.total_litter * 100).toFixed(2) + "% Total"  }}
                </p>
            </div>

            <!-- Total Photos -->
            <div class="panel-block">
                {{ $t('location.total-verified-photos') }}:
                <strong class="green flex-1">&nbsp;
                    {{ location['total_photos_redis'].toLocaleString() }}
                </strong>

                <p v-if="locationType === 'country'" class="total-photos-percentage">
                    {{ (location['total_photos_redis'] / this.$store.state.locations.total_photos * 100).toFixed(2) + "% Total"  }}
                </p>
            </div>

            <!-- Created at -->
            <div class="panel-block">{{ $t('common.created') }}: <strong class="green">&nbsp; {{ location['diffForHumans'] }}</strong></div>

            <!-- Number of Contributors -->
            <div class="panel-block">{{ $t('location.number-of-contributors') }}: <strong class="green">&nbsp; {{ location['total_contributors_redis'].toLocaleString() }}</strong></div>

            <!-- Average Image per person -->
            <div class="panel-block">{{ $t('location.avg-img-per-person') }}: <strong class="green">&nbsp; {{ location['avg_photo_per_user'].toLocaleString() }}</strong></div>

            <!-- Average Litter per person -->
            <div class="panel-block">{{ $t('location.avg-litter-per-person') }}: <strong class="green">&nbsp; {{ location['avg_litter_per_user'].toLocaleString() }}</strong></div>

            <!-- Created By -->
            <div class="panel-block">{{ $t('common.created-by') }}: <strong class="green">&nbsp; {{ location['created_by_name'] }} {{ location['created_by_username'] }}</strong></div>
        </div>
    </div>
</template>

<script>
import moment from 'moment'

export default {
    name: 'LocationMetadata',
    props:[
        'index',
        'location',
        'locationType',
        'category'
    ],
	data () {
		return {
			dir: '/assets/icons/flags/'
		};
	},
	computed: {
		/**
		 * Name of the country (if we are viewing States, Cities)
		 */
		country ()
		{
			return this.$store.state.locations.country;
		},

        /**
         * Name of the Country we are viewing the states of
         */
        countryName ()
        {
            return this.$store.state.locations.countryName;
        },

        /**
         * Name of the State we are viewing the cities of
         */
        stateName ()
        {
            return this.$store.state.locations.stateName;
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
		    if (iso)
            {
                iso = iso.toLowerCase();

                return this.dir + iso + '.png';
            }
		},

        /**
         * When user clicks on a location name
         *
         * @param location Location
         */
        getDataForLocation (location)
        {
            this.$store.commit('setLocations', []);

            if (this.locationType === 'country')
            {
                // Get States for this Country
                const countryName = location.country;

                this.$store.commit('countryName', countryName);

                this.$router.push({ path:  '/world/' + countryName });
            }
            else if (this.locationType === 'state')
            {
                // Get Cities for this State + Country
                const countryName = this.countryName;
                const stateName = location.state;

                this.$store.commit('stateName', stateName);

                this.$router.push({ path:  '/world/' + countryName + '/' + stateName });
            }
            else if (this.locationType === 'city')
            {
                const countryName = this.countryName;
                const stateName = this.stateName;
                const cityName = location.city;

                // if the object has "hex" key, the slider has updated
                if (location.hasOwnProperty('hex'))
                {
                    this.$router.push({
                        path: '/world/' + countryName + '/' + stateName + '/' + cityName + '/map/'
                    });
                    // + location.minDate + '/' + location.maxDate + '/' + location.hex
                }

                this.$router.push({ path:  '/world/' + countryName + '/' + stateName + '/' + cityName + '/map' });
            }
        },

		/**
		 * Name of a location
		 */
		getLocationName (location)
		{
			return location[this.locationType];
		},

        /**
		 * Get the number's ordinal from the position index
		 */
		positions (i)
		{
			return moment.localeData().ordinal(i + 1);
		}
	}
}
</script>

<style scoped>

    .green {
            color: green !important;
        }

    .panel-block {
            color: black;
            background-color: white;
        }
    /* .location-container {
            padding-top: 3em;
            padding-bottom: 5em;
        } */

    .location-title:hover {
        color: green !important;
        border-bottom: 1px solid green;
    }

    .total-photos-percentage {
        color: green;
        font-weight: 700;
    }

    .img-flag {
        padding-right: 1.5em;
        border-radius: 1px;
        flex: 0.1;
    }

</style>
