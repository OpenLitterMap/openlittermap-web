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
import Details from './settings/Details.vue'
import Social from './settings/Social.vue'
import Account from './settings/Account.vue'
import Password from './settings/Password.vue'
import Payments from './settings/Payments.vue'
import Privacy from './settings/Privacy.vue'
import Littercoin from './settings/Littercoin.vue'
import PickedUp from './settings/PickedUp.vue'
import Emails from './settings/Emails.vue'
import GlobalFlag from './settings/GlobalFlag.vue'

export default {
    name: 'Settings',
    components: {
        Details,
        Social,
        Account,
        Password,
        Payments,
        Privacy,
        Littercoin,
        PickedUp,
        Emails,
        GlobalFlag,
    },
    async created () {
        if (window.location.href.split('/')[4])
        {
            this.link = window.location.href.split('/')[4];
        }
    },
    data () {
        return {
            links: [
                'details',
                'social',
                'account',
                'password',
                'payments',
                'privacy',
                'littercoin',
                'picked-up',
                'emails',
                'show-flag',
            ],
            link: 'password',
            types: {
                'details': 'Details',
                'social': 'Social',
                'account': 'Account',
                'password': 'Password',
                'payments': 'Payments',
                'privacy': 'Privacy',
                'littercoin': 'Littercoin',
                'picked-up': 'PickedUp',
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
