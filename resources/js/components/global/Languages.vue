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
	      		<div v-for="lang in langs" @click="language(lang.url)" class="dropdown-item hoverable flex p1em">
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
			button: 'dropdown navbar-item pointer',
			dir: '/assets/icons/flags/',
			langs: [
				{ url: 'en' }, // We have these languages mostly done but they are in php code with the old keys
				// { url: 'de' },
				{ url: 'es' },
				// { url: 'fr' },
				// { url: 'it' },
				{ url: 'nl' },
				// { url: 'ms' },
				// { url: 'tk' }
			]
		};
	},
	computed: {

		/**
		 * Todo - change where langsOpen lives
         * We need it on vuex to close it whenever we click outside of this component
         * Todo - close when click outside of this component
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
		 * Return filepath for country flag
		 */
		getFlag (lang)
		{
			if (lang === 'en') return this.dir + 'gb.png'; // english
			if (lang === 'ms') return this.dir + 'my.png'; // malaysian
			if (lang === 'tk')  return this.dir + 'tr.png'; // turkish

			return this.dir + lang.toLowerCase() + '.png';
		},

		/**
		 * Return translated country string
		 */
		getLang (lang)
		{
			return this.$t('locations.countries.' + lang + '.lang');
		},

        /**
         * Change the currently active language
         */
        language (lang)
        {
            this.$i18n.locale = lang;
            this.$store.commit('closeLangsButton');
        },

		/**
		 *
		 */
		toggleOpen ()
		{
		    this.$store.commit('closeDatesButton');
		    this.$store.commit('toggleLangsButton');
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
