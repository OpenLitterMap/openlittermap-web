<template>
	<div :class="checkOpen">
	  	<div class="dropdown-trigger" @click.stop="toggleOpen">
	    	<button class="button" aria-haspopup="true">
	    		<i class="fa fa-list" style="margin-right: 1em; font-size: 12px;" />
	      		<span>More data</span>
	    	</button>
	    </div>
	  	<div class="dropdown-menu">
	    	<div class="dropdown-content" style="padding: 0;">
	      		<div v-for="date in dates" @click="reload(date.url)" class="dropdown-item hoverable flex">
	      			<p style="flex: 0.9;">{{ date.text }}</p>
	      			<i class="fa fa-check-circle mauto green" v-show="currentDate == date.url" />
				</div>
	    	</div>
	  	</div>
	</div>
</template>

<script>
export default {
	name: 'GlobalDates',
	data ()
    {
		return {
			button: 'dropdown navbar-item pointer global-dates',
			currentDate: 'today',
			dates: [
				{
					text: 'Today',
					url: 'today'
				},
				{
					text: 'One week',
					url: 'one-week'
				},
				{
					text: 'One month',
					url: 'one-month'
				},
				{
					text: 'One year',
					url: 'one-year'
				},
				{
					text: 'All time',
					url: 'all-time'
				}
			]
		};
	},
	computed: {
		/**
		 *
		 */
		checkOpen ()
		{
			return this.$store.state.globalmap.datesOpen ? this.button + ' is-active' : this.button;
		}
	},
	methods: {
		/**
		 *
		 */
		reload (url)
		{
			this.currentDate = url;
			this.$store.dispatch('GLOBAL_MAP_DATA', url);
		},

		/**
		 *
		 */
		toggleOpen ()
		{
			this.$store.commit('closeLangsButton');
			this.$store.commit('toggleGlobalDates');
		}
	}
}
</script>

<style lang="scss">

	.dropdown-item {
		border-bottom: 1px solid #dbdbdb;
		color: black !important;
		padding: 1em;
	}

	.global-dates {
		position: absolute;
		left: 11em;
		top:0;
		z-index: 999;
	}

	.mauto {
		margin-top: auto;
		margin-bottom: auto;
	}

	.green {
		color: #2ecc71;
	}
</style>
