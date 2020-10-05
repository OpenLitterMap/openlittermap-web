<template>
	<div class="global-info-box" :style="checkSession">

		<div class="flex">
			<p class="welcome-title is-dark">Welcome to OpenLitterMap!</p>
			<p class="welcome-close" @click="close">x</p>
		</div>

		<p class="welcome-subtitle pb1em">OpenLitterMap is a map of the world's litter, created by people like you.</p>

		<p class="welcome-subtitle pb1em">Our data is free and open for anyone to download and use for any purpose, without restriction.</p>

		<p class="welcome-subtitle">Download our app for <a href="https://play.google.com/store/apps/details?id=com.geotech.openlittermap" target="_blank">Android</a> or <a href="https://apps.apple.com/us/app/openlittermap/id1475982147" target="_blank">iPhone</a> and help us bring transparency & democracy to legal and scientific progress on permanent global pollution.</p>
	</div>
</template>

<script>
export default {
	name: 'GlobalInfo',
	data ()
	{
		return {
			hide: false
		};
	},
	computed: {

		/**
		 *
		 */
		checkSession ()
		{
			return this.$localStorage.get('hide') || this.hide ? 'display: none' : '';
		}
	},
	methods: {

		/**
		 *
		 */
		close ()
		{
			this.hide = true;
	  		this.$localStorage.set('hide', true);
		}
	}
}
</script>

<style lang="scss">

	.global-info-box {
		position: absolute;
		top: 3em;;
		left: 4em;
		background-color: #2ecc71;
		padding: 1em;
		width: 420px;
		z-index: 999;
	}

	.is-dark {
		font-color: #2d2b2b;
	}

	.pb1em {
		padding-bottom: 1em;
	}

	.welcome-title {
		flex: 1;
		font-weight: 600;
		margin: auto;
		padding-bottom: 1em;
	}

	.welcome-subtitle {
		font-color: #2d2b2b;
		font-weight: 400;
	}

	.welcome-close {
		cursor: pointer;
		padding: 1em 2em;
		position: absolute;
		top: 0;
		right: 0;
	}

	@media screen and (max-width: 500px)
    {
		.global-info-box {
			width: 75%;
		}

		.welcome-close {
			padding: 1em;
		}
	}
</style>
