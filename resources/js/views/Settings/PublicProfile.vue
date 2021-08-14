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

                    <SocialMediaIntegration
                        v-if="show_public_profile"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import SocialMediaIntegration from '../../components/User/Settings/PublicProfile/SocialMediaIntegration';

export default {
    name: 'PublicProfile',
    components: {
        SocialMediaIntegration
    },
    data () {
        return {
            processing: false,
            // download: true,
            // map: true,
            // twitter: '',
            // instagram: '',
            // socialMediaLink: null
        };
    },
    computed: {
        /**
         * CSS class to show to style button
         */
        getButtonClass ()
        {
            const c = (this.show_public_profile)
                ? 'is-danger'
                : 'is-primary';

            return (this.processing)
                ? c + ' is-loading'
                : c;
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

</style>
