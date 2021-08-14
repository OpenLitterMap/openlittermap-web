<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.common.public-profile') }}</h1>

        <hr>
        <br>

        <div class="columns">
            <div class="column is-offset-1">
                <div class="row">
                    <p class="subtitle is-4 mb1">Link your apps</p>

                    <p class="mb1">
                        You use this to promote some social media handles on OpenLitterMap and create links on the maps and leaderboards.
                    </p>

                    <!-- Link Twitter -->
                    <div class="public-profile-icon-container mb1">

                        <img src="/assets/icons/twitter2.png" class="public-profile-icon" />

                        <input
                            id="twitter"
                            name="twitter"
                            type="input"
                            class="input w-15"
                            v-model="twitter"
                            placeholder="openlittermap"
                        />
                    </div>

                    <div v-if="twitter" class="social-media-options">

                        <!-- Show map with all my data -->
                        <div class="control mb1">
                            <input
                                id="twitter_leaderboards"
                                name="twitter_leaderboards"
                                type="checkbox"
                                v-model="twitter_leaderboards"
                            />
                            <label for="twitter_leaderboards">Link My Username In Leaderboards To My Twitter Link</label>
                        </div>

                        <!-- Show map with all my data -->
                        <div class="control mb1">
                            <input
                                id="twitter_global_map"
                                name="twitter_global_map"
                                type="checkbox"
                                v-model="twitter_global_map"
                            />
                            <label for="twitter_global_map">Link </label>
                        </div>
                    </div>

                    <!-- Link Instagram -->
                    <div class="public-profile-icon-container mb1">

                        <img src="/assets/icons/ig2.png" class="public-profile-icon" />

                        <input
                            id="instagram"
                            name="instagram"
                            type="input"
                            class="input w-15"
                            v-model="instagram"
                            placeholder="openlittermap"
                        />
                    </div>

                    <div v-if="instagram" class="social-media-options">
                        <input
                            id="instagram_leaderboards"
                            name="instagram_leaderboards"
                            type="checkbox"
                            v-model="instagram_leaderboards"
                        />
                        <label for="instagram_leaderboards">Link instagram to my username on the Leaderboards</label>

                        <!-- Show map with all my data -->
                        <div class="control mb1">
                            <input
                                id="instagram_global_map"
                                name="instagram_global_map"
                                type="checkbox"
                                v-model="instagram_global_map"
                            />
                            <label for="instagram_global_map">Link instagram to my username on the Global Map</label>
                        </div>
                    </div>

                    <button
                        class="button is-medium is-info mt1"
                        :class="processing ? 'is-loading' : ''"
                        @click="update"
                        :disabled="processing"
                    >Save</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SocialMediaIntegration',
    data () {
        return {
            processing: false
        };
    },
    computed: {

        instagram: {
            get () {
                return this.settings.instagram;
            },
            set (v) {
                this.$store.commit('publicProfileSetting', {
                    key: 'instagram',
                    v
                });
            }
        },

        instagram_leaderboards : {
            get () {
                return true;
            },
            set (v) {

            }
        },

        instagram_global_map: {
            get () {
                return true;
            },
            set (v) {

            }
        },

        twitter: {
            get () {
                return this.settings.twitter;
            },
            set (v) {
                this.$store.commit('publicProfileSetting', {
                    key: 'twitter',
                    v
                });
            }
        },

        twitter_leaderboards : {
            get () {
                return true;
            },
            set (v) {

            }
        },

        twitter_global_map: {
            get () {
                return true;
            },
            set (v) {

            }
        },

        /**
         * Shortcut to UserSettings state
         */
        settings ()
        {
            return this.$store.state.user.user.settings;
        }
    },
    methods: {
        /**
         * Change what components are visible on a Public Profile
         */
        async update ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_SOCIAL_MEDIA_LINKS');

            this.processing = false;
        }
    }
};
</script>

<style scoped>

    .public-profile-icon-container {
        display: flex;
        margin: auto 0;
        align-items: center;
    }

    .public-profile-icon {
        width: 3em;
        margin-right: 1em;
    }

    .social-media-options {
        margin-bottom: 1em;
        padding-left: 4em;
    }

</style>
