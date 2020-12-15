<template>
    <div class="container mt-5">
        <div class="columns is-mobile is-multiline">
            <div class="column is-2-widescreen is-3-desktop is-3-tablet is-full-mobile is-offset-2-widescreen">
                <aside id="panel" class="menu">
                    <p class="menu-label">
                        {{ $t('settings.common.general') }}
                    </p>
                    <div :class="[isActive? 'is-active': '','dropdown dropdown-mobile']">
                        <div class="dropdown-trigger">
                            <button class="button" aria-haspopup="true" @click="toggleDropdown">
                                <span>{{ translate(link) }}</span>
                                <span class="icon is-small">
                                    <i class="fa fa-angle-down" aria-hidden="true" />
                                </span>
                            </button>
                        </div>
                        <div class="dropdown-menu" role="menu">
                            <div class="dropdown-content">
                                <div v-for="linkItem in links" :key="linkItem + 'mobile'" class="dropdown-item">
                                    <router-link :to="'/settings/' + linkItem" @click.native="change(linkItem)">
                                        {{ translate(linkItem) }}
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ul class="menu-list dropdown-desktop">
                        <li v-for="linkItem in links" :key="linkItem + 'desktop'">
                            <router-link :to="'/settings/' + linkItem" @click.native="change(linkItem)">
                                {{ translate(linkItem) }}
                            </router-link>
                        </li>
                    </ul>
                </aside>
            </div>
            <div class="column is-6-widescreen is9-desktop is-9-tablet">
                <div class="user-setting-container">
                    <component :is="types[link]" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Password from './settings/Password';
import Details from './settings/Details';
import Account from './settings/Account';
import Payments from './settings/Payments';
import Privacy from './settings/Privacy';
import Littercoin from './settings/Littercoin';
import GlobalFlag from './settings/GlobalFlag';

export default {
    name: 'Settings',
    components: {
        Password,
        Details,
        Account,
        Payments,
        Privacy,
        Littercoin,
        GlobalFlag,
    },
    data ()
    {
        return {
            links: [
                'details',
                'password',
                'account',
                'payments',
                'privacy',
                'littercoin',
                'show-flag',
            ],
            link: 'details',
            types: {
                'details': 'Details',
                'password': 'Password',
                'account': 'Account',
                'payments': 'Payments',
                'privacy': 'Privacy',
                'littercoin': 'Littercoin',
                'show-flag': 'GlobalFlag',
            },
            isActive: false
        };
    },
    async created ()
    {
        if (window.location.href.split('/')[4])
        {
            this.link = window.location.href.split('/')[4];
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
        },
        toggleDropdown ()
        {
            this.isActive = !this.isActive;
        }
    }
};
</script>

<style lang="scss" scoped>
@import "../styles/variables.scss";

.columns{
    margin: unset;
}

.dropdown {
    width: 100%;

  .dropdown-menu {
        width: 100%;

        a {
            color: $black;
            font-size: 1rem;
        }
  }
}

.dropdown-trigger {
    width: 100%;

    button > span {
        margin-right: auto;
    }
}

button {
    width: 100%;
}

.user-setting-container {
    margin: 0 1.5rem;
}

@include media-breakpoint-up(md){
    .dropdown-mobile{
        display: none;
    }
}

@include media-breakpoint-down(md){
    .dropdown-desktop{
        display: none;
    }

    .user-setting-container {
        margin: 0 0.5rem;
    }
}
</style>
