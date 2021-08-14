<template>
	<div class="container mt5">
		<div class="columns">
			<div class="column is-2">
				<aside id="panel" class="menu">
				    <p class="menu-label">
				        {{ $t('settings.common.general') }}
				    </p>
				    <ul class="menu-list">
				        <li v-for="link in Object.keys(this.links)">
				    	    <router-link :to="'/settings/' + link" @click.native="change(link)">
                                {{ translate(link) }}
				    	    </router-link>
				        </li>
				    </ul>
				</aside>
			</div>
			<div class="column is-three-quarters is-offset-1">
                <component :is="this.links[this.link]" />
			</div>
		</div>
	</div>
</template>

<script>
import Password from './Settings/Password'
import Details from './Settings/Details'
import Account from './Settings/Account'
import Payments from './Settings/Payments'
import Privacy from './Settings/Privacy'
import Littercoin from './Settings/Littercoin'
import Presence from './Settings/Presence'
import Emails from './Settings/Emails'
import GlobalFlag from './Settings/GlobalFlag'
import PublicProfile from './Settings/PublicProfile';
import SocialMediaIntegration from './Settings/SocialMediaIntegration';

export default {
    name: 'Settings',
    components: {
        Password,
        Details,
        Account,
        Payments,
        Privacy,
        Littercoin,
        Presence,
        Emails,
        GlobalFlag,
        PublicProfile,
        SocialMediaIntegration
    },
    async created ()
    {
        if (window.location.href.split('/')[4])
        {
            this.link = window.location.href.split('/')[4];
        }
    },
    data ()
    {
        return {
            link: 'password',
            // Link route name to Component
            links: {
                'password': 'Password',
                'details': 'Details',
                'account': 'Account',
                'payments': 'Payments',
                'privacy': 'Privacy',
                'littercoin': 'Littercoin',
                'presence': 'Presence',
                'emails': 'Emails',
                'show-flag': 'GlobalFlag',
                'public-profile': 'PublicProfile',
                // 'social-media': 'SocialMediaIntegration'
            }
        }
    },
    methods: {
        /**
         * Change link = view different component
         */
        change (link)
        {
            this.link = link;
        },

        /**
         * Get translated text for this link
         */
        translate (link)
        {
            return this.$t('settings.common.' + link);
        }
    }
}
</script>
