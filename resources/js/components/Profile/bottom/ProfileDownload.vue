<template>
    <div class="profile-card">
        <p class="profile-dl-title">{{ $t('profile_dashboard.bottom.download-data') }}</p>

        <p class="profile-dl-subtitle">{{ $t('profile_dashboard.bottom.email-send-msg') }}</p>

        <button :class="button" @click="download" :disabled="processing">{{ $t('common.download') }}</button>
    </div>
</template>

<script>
export default {
    name: 'ProfileDownload',
    data ()
    {
        return {
            btn: 'button is-medium is-purp',
            processing: false
        };
    },
    computed: {

        /**
         * Add spinner when processing
         */
        button () {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        }
    },
    methods: {

        /**
         * Dispatch a request to download a users data
         */
        async download ()
        {
            this.processing = true;

            await this.$store.dispatch('DOWNLOAD_MY_DATA');

            this.processing = false;
        }
    }
};
</script>

<style scoped>

    .profile-dl-title {
        color: #8e7fd6;
        margin-bottom: 1em;
        font-weight: 600;
    }

    .profile-dl-subtitle {
        color: #8e7fd6;
        margin-bottom: 1em;
    }

    .is-purp {
        background-color: #8e7fd6;
    }
</style>
