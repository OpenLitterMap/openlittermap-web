<template>
    <!-- Left column (metadata) -->
    <div class="column is-3">

        <!-- Name, Position and Flag-->
        <div class="flex pb1">

            <!-- Flag -->
            <img v-if="type === 'country'"
                    height="15"
                    style="padding-right: 1.5em; border-radius: 1px; flex: 0.1;"
                    :src="getCountryFlag(location.shortcode)"
            />

            <h2 :class="textSize">
                <a @click="goTo(location)" :id="location[type]" class="is-link has-text-centered location-title">
                    <!-- Position -->
                    <span v-show="category !== 'A-Z' && index < 100">{{ positions(index) }} -</span>
                    <!-- Name -->
                    <span>{{ getName(location) }}</span>
                </a>
            </h2>
        </div>

        <!-- Location metadata -->
        <div class="panel">
            <div class="panel-block">{{ $t('location.total-verified-litter') }}:
                <strong class="green flex-1">&nbsp;
                    {{ location['total_litter_redis'].toLocaleString() }}
                </strong>

                <p v-if="type === 'country'" class="total-photos-percentage">
                    {{ (location['total_litter_redis'] / this.$store.state.locations.total_litter * 100).toFixed(2) + "% Total"  }}
                </p>
            </div>
            <div class="panel-block">
                {{ $t('location.total-verified-photos') }}:
                <strong class="green flex-1">&nbsp;
                    {{ location['total_photos_redis'].toLocaleString() }}
                </strong>

                <p v-if="type === 'country'" class="total-photos-percentage">
                    {{ (location['total_photos_redis'] / this.$store.state.locations.total_photos * 100).toFixed(2) + "% Total"  }}
                </p>
            </div>
            <div class="panel-block">{{ $t('common.created') }}: <strong class="green">&nbsp; {{ location['diffForHumans'] }}</strong></div>
            <div class="panel-block">{{ $t('location.number-of-contributors') }}: <strong class="green">&nbsp; {{ location['total_contributors_redis'].toLocaleString() }}</strong></div>
            <div class="panel-block">{{ $t('location.avg-img-per-person') }}: <strong class="green">&nbsp; {{ location['avg_photo_per_user'].toLocaleString() }}</strong></div>
            <div class="panel-block">{{ $t('location.avg-litter-per-person') }}: <strong class="green">&nbsp; {{ location['avg_litter_per_user'].toLocaleString() }}</strong></div>
            <div class="panel-block">{{ $t('common.created-by') }}: <strong class="green">&nbsp; {{ location['created_by_name'] }} {{ location['created_by_username'] }}</strong></div>
        </div>
    </div>
</template>

<script>
import moment from 'moment'

export default {
    props:['index', 'location', 'type', 'category'],
    name: 'LocationMetadata',
	data ()
	{
		return {
			dir: '/assets/icons/flags/',
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
		 * Name of a location
		 */
		getName (location)
		{
			return location[this.type];
		},

        /**
		 * When user clicks on a location name
		 */
		goTo (location)
		{
			if (this.type === 'country')
			{
			    let country = location.country;

			    this.$store.commit('setCountry', country);

				this.$router.push({ path:  '/world/' + country });
			}
			else if (this.type === 'state')
			{
			    let state = location.state;

			    this.$store.commit('setState', state);

				this.$router.push({ path:  '/world/' + this.country + '/' + state });
			}
			else if (this.type === 'city')
			{
			    // if the object has "hex" key, the slider has updated
                if (location.hasOwnProperty('hex'))
                {
                    this.$router.push({
                        path:
                            '/world/' + this.country + '/' + this.state + '/' + location.city + '/map/'
                            + location.minDate + '/' + location.maxDate + '/' + location.hex
                    });
                }

				this.$router.push({ path:  '/world/' + this.country + '/' + this.state + '/' + location.city + '/map' });
			}
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
    .location-container {
            padding-top: 3em;
            padding-bottom: 5em;
        }

    .location-title:hover {
        color: green !important;
        border-bottom: 1px solid green;
    }

    .l-tab.is-active {
		border-bottom: 2px solid white !important;
	}

    .total-photos-percentage {
        color: green;
        font-weight: 700;
    }

</style>
