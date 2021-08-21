<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.common.public-profile') }}</h1>

        <hr>
        <br>

        <div class="columns">
            <div class="column is-offset-1">
                <div class="row">
                    <p class="subtitle is-4 mb1">Do you want to make your User Dashboard public or private?</p>


                    <strong class="mb1">
                        Public Profile Status:

                        <span :style="getColor">{{ this.getStatusText }}</span>
                    </strong>

                    <p>{{ this.getInfoText }}</p>

                    <br>

                    <button
                        class="button is-medium"
                        :class="getButtonClass"
                        @click="toggle"
                        :disabled="processing"
                    >{{ this.getButtonText }}</button>

                    <div v-if="show_public_profile" class="pt2">

                        <p class="subtitle is-4 mb1">You can control what data you want to display</p>

                        <!-- Download my data -->
                        <div class="control mb1">
                            <input
                                id="download"
                                name="download"
                                type="checkbox"
                                v-model="download"
                            />
                            <label for="download">Show button to download my data</label>
                        </div>

                        <!-- Show map with all my data -->
                        <div class="control mb1">
                            <input
                                id="map"
                                name="map"
                                type="checkbox"
                                v-model="map"
                            />
                            <label for="map">Show map with all my data</label>
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
    </div>
</template>

<script>
export default {
    name: 'PublicProfile',
    data () {
        return {
            processing: false
        };
    },
    computed: {
        /**
         * CSS class to show to style button
         */
        getButtonClass ()
        {
            const color = (this.show_public_profile)
                ? 'is-danger'
                : 'is-primary';

            return (this.processing)
                ? color + ' is-loading'
                : color;
        },

        /**
         * Text to show when toggling public status
         */
        getButtonText ()
        {
            return (this.show_public_profile)
                ? 'Make My Profile Private'
                : 'Make My Profile Public';
        },

        /**
         * Color to show for public/private status
         */
        getColor ()
        {
            return (this.show_public_profile)
                ? 'color: green;'
                : 'color: red;';
        },

        /**
         * More information to show about the current privacy status
         */
        getInfoText ()
        {
            return (this.show_public_profile)
                ? 'Your profile is public. Anyone can visit it and see the data you allow.'
                : 'Your profile is private. Only you can access it.';
        },

        /**
         * Text to show for public/private status
         */
        getStatusText ()
        {
            return (this.show_public_profile)
                ? 'Public'
                : 'Private';
        },

        download: {
            get () {
                return this.settings.download;
            },
            set (v) {
                this.$store.commit('publicProfileSetting', {
                    key: 'download',
                    v
                });
            }
        },

        map: {
            get () {
                return this.settings.map;
            },
            set (v) {
                this.$store.commit('publicProfileSetting', {
                    key: 'map',
                    v
                });
            }
        },

        /**
         * Shortcut to user.settings
         */
        settings ()
        {
            return this.$store.state.user.user.settings;
        },

        /**
         * Return True to make the Profile Public
         *
         * Return False if UserSettings does not exist yet
         */
        show_public_profile ()
        {
            return this.$store.state.user.user.settings?.show_public_profile;
        }
    },
    methods: {
        /**
         * Change the privacy value of the users public profile
         */
        async toggle ()
        {
            this.processing = true;

            await this.$store.dispatch('TOGGLE_PUBLIC_PROFILE');

            this.processing = false;
        },

        /**
         * Change what components are visible on a Public Profile
         */
        async update ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_PUBLIC_PROFILE_SETTINGS');

            this.processing = false;
        }
    }
};
</script>

<style scoped>



</style>
