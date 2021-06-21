<template>
<!-- Left column (metadata) -->
<div class="column is-3">

    <!-- Name, Position and Flag-->
    <div class="flex pb1">

        <!-- Flag -->
        <img v-if="type === 'country' && category !== 'A-Z'"
                height="15"
                style="padding-right: 1.5em; border-radius: 1px; flex: 0.1;"
                :src="getCountryFlag(location.shortcode)"
        />

        <h2 :class="textSize">
            <a @click="goTo(index)" :id="location[type]" class="is-link has-text-centered location-title">
                <!-- Position -->
                <span v-show="category !== 'A-Z' && index < 30">{{ positions(index) }} -</span>
                <!-- Name -->
                <span>{{ getName(location) }}</span>
            </a>
        </h2>
    </div>

    <!-- Location metadata -->
    <div class="panel">
        <div class="panel-block">{{ $t('location.maps10') }}: <strong class="green">&nbsp; {{ location['total_litter_redis'].toLocaleString() }}</strong></div>
        <div class="panel-block">{{ $t('location.maps11') }}: <strong class="green">&nbsp; {{ location['total_photos_redis'].toLocaleString() }}</strong></div>
        <div class="panel-block">{{ $t('location.maps12') }}: <strong class="green">&nbsp; {{ location['diffForHumans'] }}</strong></div>
        <div class="panel-block">{{ $t('location.maps13') }}: <strong class="green">&nbsp; {{ location['total_contributors_redis'].toLocaleString() }}</strong></div>
        <div class="panel-block">{{ $t('location.maps14') }}: <strong class="green">&nbsp; {{ location['avg_photo_per_user'].toLocaleString() }}</strong></div>
        <div class="panel-block">{{ $t('location.maps15') }}: <strong class="green">&nbsp; {{ location['avg_litter_per_user'].toLocaleString() }}</strong></div>
        <div class="panel-block">{{ $t('location.maps16') }}: <strong class="green">&nbsp; {{ location['created_by_name'] }} {{ location['created_by_username'] }}</strong></div>
    </div>
</div>
</template>

<script>
export default {
    props: ['type', 'location', 'category'], // country, state, or city
    name: 'LocationMetadata',
	data ()
	{
		return {
            dir: '/assets/icons/flags/',
			
		};
	},
	computed: {

		
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
	}
    
}
</script>


<style scoped>



</style>