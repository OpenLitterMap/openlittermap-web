<template>
	<div :class="checkOpen">
	  	<div class="dropdown-trigger" @click.stop="toggleOpen" >
	    	<button class="button" aria-haspopup="true">
	      		<!-- Current Language -->
      			<img :src="getFlag('en')" class="lang-flag-small" />
				<span>English</span>
			</button>
	  	</div>

		<div class="dropdown-menu">
	    	<div class="dropdown-content" style="padding: 0;">
	      		<div v-for="lang in langs" @click="reload(lang.url)" class="dropdown-item hoverable flex p1em">
	      			<img :src="getFlag(lang.url)" class="lang-flag" />
	      			<p>{{ getLang(lang.url) }}</p>
	      		</div>
			</div>
	  	</div>
	</div>
</template>

<script>
export default {
	name: 'Languages',
	data ()
	{
		return {
			button: 'dropdown navbar-item pointer global-langs',
			dir: '/assets/icons/flags/',
			langs: [
				{
					url: 'en'
				},
				{
					url: 'de'
				},
				{
					url: 'es'
				},
				{
					url: 'fr'
				},
				{
					url: 'it'
				},
				{
					url: 'ms'
				},
				{
					url: 'tk'
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
			return this.$store.state.globalmap.langsOpen ? this.button + ' is-active' : this.button;
		},

		/**
		 * Current locale @en
		 */
		locale ()
		{
			return this.$i18n.locale;
		}
	},
	methods: {

		/**
		 *
		 */
		getFlag (lang)
		{
			if (lang === 'en') return this.dir + 'gb.png'; // english
			if (lang === 'ms') return this.dir + 'my.png'; // malaysian
			if (lang === 'tk')  return this.dir + 'tr.png'; // turkish

			return this.dir + lang.toLowerCase() + '.png';
		},

		/**
		 *
		 */
		getLang (lang)
		{
			return this[lang];
		},

		/**
		 *
		 */
		toggleOpen ()
		{
		  this.$store.commit('closeDatesButton');
		  this.$store.commit('toggleLangsButton');
		},

		/**
		 *
		 */
		reload (lang)
		{
			window.location.href = window.location.origin + '/' + lang;
		}
	}
}
</script>

<style lang="scss">

	.flex {
		display: flex;
	}

	.hoverable {
		cursor: pointer;
	}

	.hoverable:hover {
		background-color: whitesmoke;
	}

	.global-langs {
		position: absolute;
		z-index: 999;
		left: 3em;
		top: 0;
	}

	.p1em {
		padding: 1em;
	}

	.lang-flag {
		max-height: 1.25em !important;
		margin-right: 1em;
	}

	.lang-flag-small {
		max-height: 1em !important;
		margin-right: 0.5em;
	}
</style>
