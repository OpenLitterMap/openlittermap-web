<template>
    <div class="profile-card">
        <p class="profile-dl-title">Download My Data</p>

        <p class="profile-dl-subtitle">An email will be sent to the address you use to login.</p>

        <button :class="button" @click="download" :disabled="processing">Download</button>
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
