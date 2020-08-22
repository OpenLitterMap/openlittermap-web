<template>
    <footer class="foot">
        <div class="container">
            <!-- Top Section -->
            <div class="inner-footer-container has-text-centered">
                <p class="top-footer-title">Want us to email you occasionally with good news?</p>

                <!-- Errors -->
                <div v-if="hasErrors" class="notification is-danger mb1em">
                    <div v-for="error in Object.keys(this.errors)" :key="error">
                        <p>{{ getError(error) }}</p>
                    </div>
                </div>

                <form method="post" @submit.prevent="subscribe">

                    <input
                        class="input f-input"
                        placeholder="Enter your email address"
                        required
                        type="email"
                        v-model="email"
                        @input="clearErrors"
                    />

                    <br>

                    <button class="button is-medium is-primary hov mb2">Subscribe</button>

                    <p v-show="subscribed" class="footer-success">
                        You have been subscribed to the good news! You can unsubscribe at any time.
                    </p>
                </form>
            </div>

            <!-- Bottom Section -->
            <div class="columns">
                <div class="column is-half foot-container-left">
                    <p class="olm-title">#OpenLitterMap</p>

                    <p class="footer-text mb1">We need your help to create the world's most advanced and accessible database on pollution.</p>

                    <img
                        v-for="s in socials"
                        :src="icon(s.icon)"
                        @click="open(s.url)"
                        class="footer-icon"
                    />
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">READ</p>

                    <p class="footer-text" @click="open('https://medium.com/@littercoin')">Blog</p>
                    <p class="footer-text" @click="open('https://opengeospatialdata.springeropen.com/articles/10.1186/s40965-018-0050-y')">Research Paper</p>
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">WATCH</p>

                    <p class="footer-text" @click="open('https://www.youtube.com/watch?v=my7Cx-kZhT4')">TEDx 2017</p>
                    <p class="footer-text" @click="open('https://www.youtube.com/watch?v=E_qhEhHwUGM')">State of the Map 2019</p>
                    <p class="footer-text" @click="open('https://www.youtube.com/watch?v=T8rGf1ScR1I')">Datapub 2020</p>
                </div>

                <div class="column is-2">
                    <p class="olm-subtitle">HELP</p>

                    <p class="footer-text">Create Account</p>
                    <p class="footer-text" @click="open('https://angel.co/openlittermap/jobs')">Apply For Position</p>
                    <p class="footer-text" @click="open('https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw')">Join Slack</p>
                    <p class="footer-text" @click="open('https://www.facebook.com/pg/openlittermap/groups/')">Facebook Group</p>
                    <router-link to="/donate" class="footer-text">Single Donation</router-link>
                    <router-link to="/join" class="footer-text">Weekly Crowdfunding</router-link>
                </div>
            </div>
        </div>

        <!-- Very bottom section -->
        <div class="footer-bottom">
            <p class="footer-text">OpenLitterMap is a product of GeoTech Innovations Ltd., a startup in Ireland #650323</p>
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
                { icon: 'facebook2.png', url: 'https://facebook.com/openlittermap' },
                { icon: 'ig2.png', url: 'https://instagram.com/openlittermap' },
                { icon: 'twitter2.png', url: 'https://twitter.com/openlittermap' },
                { icon: 'reddit.png', url: 'https://reddit.com/r/openlittermap' },
                { icon: 'tumblr.png', url: 'https://tumblr.com/openlittermap' },
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

<style scoped>

    .foot {
        padding: 5em;
        background-image: radial-gradient(circle at 1% 1%,#328bf2,#1644ad);
        height: 40em;
        position: relative;
    }

    .footer-bottom {
        position: absolute;
        bottom: 0;
        left: 33%;
        border-top: 1px solid #3c6fcd;
        padding: 1em 0;
    }

    .foot-container-left {
        padding-right: 10em;
    }

    .footer-icon {
        max-height: 2em;
        margin-right: 1em;
    }

    .footer-success {
        font-size: 1.5em;
        color: white;
    }

    .footer-text {
        color: #94afe3;
        cursor: pointer;
    }

    .footer-text:hover {
        color: white;
    }

    .f-input {
        height: 3em;
        border-radius: 1em;
        margin-bottom: 1.5em;
        width: 50%;
    }

    .inner-footer-container {
        padding-left: 10em;
        padding-right: 10em;
    }

    .olm-title {
        font-size: 2em;
        font-weight: 700;
        color: white;
        margin-bottom: 1em;
    }

    .olm-subtitle {
        font-size: 1.5em;
        font-weight: 700;
        color: white;
        margin-bottom: 1em;
    }

    .top-footer-title {
        color: white;
        font-size: 2.5em;
        margin-bottom: 1.25em;
    }
</style>
