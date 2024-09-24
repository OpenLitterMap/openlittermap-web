<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">
            {{ $t('settings.privacy.change-privacy') }}
        </h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column one-third is-offset-1">

                <div class="field">
                    <MapsPrivacy/>

                    <LeaderboardsPrivacy />

                    <CreatedByPrivacy />

                    <PreventOthersTaggingMyPhotos />
                </div>

                <button
                    class="button is-medium is-info"
                    :class="processing ? 'is-loading' : ''"
                    :disabled="processing"
                    @click="submit"
                >
                    {{ $t('settings.privacy.update') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import MapsPrivacy from '../../components/User/Settings/Privacy/MapsPrivacy.vue';
import LeaderboardsPrivacy from '../../components/User/Settings/Privacy/LeaderboardsPrivacy.vue';
import CreatedByPrivacy from '../../components/User/Settings/Privacy/CreatedByPrivacy.vue';
import PreventOthersTaggingMyPhotos from '../../components/User/Settings/Privacy/PreventOthersTaggingMyPhotos.vue';

export default {
    name: 'Privacy',
    components: {
        PreventOthersTaggingMyPhotos,
        MapsPrivacy,
        LeaderboardsPrivacy,
        CreatedByPrivacy
    },
    data () {
        return {
            processing: false
        };
    },
    methods: {
        /**
         * Dispatch request to save all settings
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('SAVE_PRIVACY_SETTINGS');

            this.processing = false;
        }
    }
};
</script>
