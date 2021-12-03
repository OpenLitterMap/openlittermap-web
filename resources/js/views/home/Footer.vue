<template>
    <footer class="foot">
        <div class="container">
            <!-- Top Section -->
            <div class="inner-footer-container has-text-centered">
                <p class="top-footer-title">{{ $t('home.footer.email-you') }}?</p>

                <!-- Errors -->
                <div v-if="hasErrors" class="notification is-danger mb1em">
                    <div v-for="error in Object.keys(this.errors)" :key="error">
                        <p>{{ getError(error) }}</p>
                    </div>
                </div>

                <form method="post" @submit.prevent="subscribe">

                    <input
                        class="input f-input"
                        :placeholder="$t('home.footer.enter-email')"
                        required
                        type="email"
                        v-model="email"
                        @input="clearErrors"
                    />

                    <br>

                    <button class="button is-medium is-primary hov mb2">{{ $t('home.footer.subscribe') }}</button>

                    <p v-show="subscribed" class="footer-success">
                        {{ $t('home.footer.subscribed-success-msg') }}.
                    </p>
                </form>
            </div>

            <!-- Bottom Section -->
            <div class="columns">
                <div class="column is-half foot-container-left">
                    <p class="olm-title">#OpenLitterMap</p>

                    <p class="footer-text mb1">{{ $t('home.footer.need-your-help') }}.</p>

                    <img
                        v-for="s in socials"
                        :src="icon(s.icon)"
                        @click="open(s.url)"
                        class="footer-icon"
                    />
                    <br>
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">{{ $t('home.footer.read') }}</p>

                    <p class="footer-link" @click="open('https://openlittermap.medium.com/')">{{ $t('home.footer.blog') }}</p>
                    <p class="footer-link" @click="open('https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-018-0050-y')">{{ $t('home.footer.research-paper') }}</p>
                    <router-link tag="p" to="/references" class="footer-link">{{ $t('home.footer.references') }}</router-link>
                    <router-link tag="p" to="/credits" class="footer-link">{{ $t('home.footer.credits') }}</router-link>
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">{{ $t('home.footer.watch') }}</p>

                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=my7Cx-kZhT4')">TEDx 2017</p>
                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=E_qhEhHwUGM')">State of the Map 2019</p>
                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=T8rGf1ScR1I')">Datapub 2020</p>
                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=5HuaQNeHuZ8')">ESA PhiWeek 2020</p>
                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=QhLsA0WIfTA')">Geneva Form, UN 2020</p>
                    <p class="footer-link" @click="open('https://www.youtube.com/watch?v=Pe4nHdoAlu4')">Cardano4Climate Meetup 2021</p>
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">{{ $t('home.footer.help') }}</p>

                    <router-link to="/contact-us">
                        <p class="footer-link">{{ $t('home.footer.contact-us') }}</p>
                    </router-link>
                    <p class="footer-link">{{ $t('home.footer.create-account') }}</p>
                    <p class="footer-link" @click="open('https://angel.co/openlittermap/jobs')">{{ $t('home.footer.join-the-team') }}</p>
                    <p class="footer-link" @click="open('https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw')">{{ $t('home.footer.join-slack') }}</p>
                    <p class="footer-link" @click="open('https://github.com/openlittermap')">GitHub</p>
                    <p class="footer-link" @click="open('https://www.facebook.com/pg/openlittermap/groups/')">{{ $t('home.footer.fb-group') }}</p>
                    <router-link to="/donate" class="footer-link">{{ $t('home.footer.single-donation') }}</router-link>
                    <router-link to="/signup" class="footer-link">{{ $t('home.footer.crowdfunding') }}</router-link>
                </div>
            </div>
        </div>

        <!-- Very bottom section -->
        <div class="footer-bottom">
            <p class="footer-text">{{ $t('home.footer.olm-is-flagship') }}</p>
        </div>
    </footer>
</template>

<script>
export default {
    name: 'Footer',
    data ()
    {
        return {
            email: '',
            socials: [
                { icon: 'facebook2.png', url: 'https://facebook.com/openlittermap' }, // 0
                { icon: 'ig2.png', url: 'https://instagram.com/openlittermap' }, // 1
                { icon: 'twitter2.png', url: 'https://twitter.com/openlittermap' }, // 2
                { icon: 'reddit.png', url: 'https://reddit.com/r/openlittermap' }, // 3
                { icon: 'tumblr.png', url: 'https://tumblr.com/openlittermap' }, // 4
            ]
        };
    },
    computed: {

        /**
         * Errors object
         */
        errors ()
        {
            return this.$store.state.subscriber.errors;
        },

        /**
         * Return true if any errors exist
         */
        hasErrors ()
        {
            return Object.keys(this.errors).length > 0;
        },

        /**
         * Returns true when the user has just subscribed
         */
        subscribed ()
        {
            return this.$store.state.subscriber.just_subscribed;
        }
    },
    methods: {

        /**
         * Clear all subscriber errors
         */
        clearErrors ()
        {
            this.$store.commit('clearSubscriberErrors');
        },

        /**
         * The first error, if any
         */
        getError (key)
        {
            return this.errors[key][0];
        },

        /**
         * Get full path for icon
         */
        icon (path)
        {
            return '/assets/icons/' + path;
        },

        /**
         * Open in a new tab
         */
        open (url)
        {
            window.open(url, '_blank');
        },

        /**
         * Post request to save email to the subscribers table
         */
        async subscribe ()
        {
            await this.$store.dispatch('SUBSCRIBE', this.email)
        },
    }
}
</script>

<style scoped lang="scss">
@import "../../styles/variables.scss";

    .foot {
        padding: 5em;
        background-image: radial-gradient(circle at 1% 1%,#328bf2,#1644ad);
        height: 42em;
        position: relative;
    }

    .footer-bottom {
        position: absolute;
        bottom: 0;
        left: 25%;
        border-top: 1px solid #3c6fcd;
        padding: 1em 0;
    }

    .foot-container-left {
        padding-right: 10em;
    }

    .footer-icon {
        max-height: 2em;
        margin-right: 1em;
        cursor: pointer;
    }

    .footer-success {
        font-size: 1.5em;
        color: $white;
    }

    .footer-text {
        color: #94afe3;
    }

    .footer-link {
        color: #94afe3;
        cursor: pointer;
    }

    .footer-link:hover {
        color: $white;
    }

    .f-input {
        height: 3em;
        border-radius: 1em;
        margin-bottom: 1.5em;
        width: 50%;
        border: none;
    }

    .inner-footer-container {
        padding-left: 10em;
        padding-right: 10em;
    }

    .olm-title {
        font-size: 2em;
        font-weight: 700;
        color: $white;
        margin-bottom: 1em;
    }

    .olm-subtitle {
        font-size: 1.5em;
        font-weight: 700;
        color: $white;
        margin-bottom: 1em;
    }

    .top-footer-title {
        color: $white;
        font-size: 2.5em;
        margin-bottom: 1.25em;
    }


    /* Mobile view */
    @media (max-width: 768px)
    {

        .foot {
            padding: 2em;
            background-image: radial-gradient(circle at 1% 1%,#328bf2,#1644ad);
            height: 220vh;
            position: relative;
        }

        .f-input {
            width: 80%;
        }

        .footer-bottom {
            left: 10%;
            right: 10%;
        }

        .foot-container-left {
            padding-right: 0;
        }

        .inner-footer-container {
            padding-left: 0;
            padding-right: 0;
        }
    }
</style>
