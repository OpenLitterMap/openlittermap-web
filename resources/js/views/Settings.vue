<template>
	<div class="container mt5">
		<div class="columns">
			<div class="column is-2">
				<aside id="panel" class="menu">
				    <p class="menu-label">
				        {{ $t('settings.common.general') }}
				    </p>
				    <ul class="menu-list">
				        <li v-for="link in links">
				    	    <router-link :to="'/settings/' + link" @click.native="change(link)">
                                {{ translate(link) }}
				    	    </router-link>
				        </li>
				    </ul>
				</aside>
			</div>
			<div class="column is-three-quarters is-offset-1">
                <component :is="this.types[this.link]" />
			</div>
		</div>
	</div>
</template>

<script>
import Password from './settings/Password'
import Details from './settings/Details'
import Account from './settings/Account'
import Privacy from './settings/Privacy'
import Presence from './settings/Presence'
import Emails from './settings/Emails'
import GlobalFlag from './settings/GlobalFlag'

export default {
    name: 'Settings',
    components: {
        Password,
        Details,
        Account,
        Privacy,
        Presence,
        Emails,
        GlobalFlag,
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
            links: [
                'password',
                'details',
                'account',
                'privacy',
                'presence',
                'emails',
                'show-flag',
            ],
            link: 'password',
            types: {
                'password': 'Password',
                'details': 'Details',
                'account': 'Account',
                'privacy': 'Privacy',
                'presence': 'Presence',
                'emails': 'Emails',
                'show-flag': 'GlobalFlag',
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
