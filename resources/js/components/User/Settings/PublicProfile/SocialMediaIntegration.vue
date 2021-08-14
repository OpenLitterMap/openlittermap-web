<template>
    <div class="pt1">
        <p class="subtitle is-4 mb1">You can control what data you want to display</p>

        <!-- Download my data -->
        <div class="control mb1">
            <input
                id="download"
                name="download"
                type="checkbox"
                v-model="download"
            />
            <label for="download">Download My Data</label>
        </div>

        <!-- Show map with all my data -->
        <div class="control mb1">
            <input
                id="map"
                name="map"
                type="checkbox"
                v-model="map"
            />
            <label for="map">Show Map with all my data</label>
        </div>

        <!-- Link Twitter -->
        <p class="mb1">Link my Twitter</p>

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

        <!-- Link Instagram -->
        <p class="mb1">Link my Instagram</p>

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

        <div v-if="canLinkSocialMediaToUsername">
            <p>Link my Username to a social media:</p>

            <select v-model="socialMediaLink" class="input">

                <option v-for="socialMediaLink in availableSocialMediaLinks">
                    {{ socialMediaLink }}
                </option>
            </select>
        </div>

        <button
            class="button is-medium is-info"
            :class="processing ? 'is-loading' : ''"
            @click="update"
            :disabled="processing"
        >Save Settings</button>
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
        /**
         * Array of available social medias that can be linked to a Username
         */
        availableSocialMediaLinks ()
        {
            let x = [];

            if (this.twitter) x.push('twitter');
            if (this.instagram) x.push('instagram');

            return x;
        },

        /**
         * If there are any social media links,
         *
         * The user can link their Username to one of the social media platforms.
         */
        canLinkSocialMediaToUsername ()
        {
            return (this.twitter || this.instagram);
        },

        download: {
            get () {
                return true;
            },
            set (v) {

            }
        },

        map: {
            get () {
                return true;
            },
            set (v) {

            }
        },

        instagram: {
            get () {
                return '';
            },
            set (v) {

            }
        },

        twitter: {
            get () {
                return '';
            },
            set (v) {

            }
        }
    },
    methods: {
        /**
         * Change what components are visible on a Public Profile
         */
        async update ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_PUBLIC_PROFILE_SETTINGS', {
                map: this.map,
                download: this.download,
                twitter: this.twitter,
                instagram: this.instagram
            });

            this.processing = false;
        }
    }
};
</script>

<style scoped>

</style>
