<template>
    <div>
        <h1 class="title is-3">{{ $t('location.download-open-verified-data') }}</h1>
        <h1 class="title is-3">{{ $t('location.stop-plastic-ocean') }}</h1>

        <p class="mb1" v-show="!isAuth">{{ $t('location.enter-email-sent-data') }}</p>

        <input          
            v-show="!isAuth"
            class="input mb1em fs125"
            :placeholder="$t('common.your-email')"
            type="email"
            name="email"
            required
            v-model="email"
            @keydown="textEntered"
            autocomplete="email"
        />

        <button :disabled="disableDownloadButton" class="button is-large is-danger mb1" @click="download">{{ $t('common.download') }}</button>

        <p>&copy; OpenLitterMap & Contributors.</p>
    </div>
</template>

<script>
export default {
    name: 'Download',
    props: ['type', 'locationId'], // country, state or city
    data ()
    {
        return {
            email: '',
            emailEntered: false
        };
    },
    methods: {
        /**
         * Download request
         *
         * Todo - Add translation strings
         * Todo - Send csv file to email address and dispatch download event via horizon
         * Todo - add filters to download options
         * Todo - open up more options for downloads (geojson, shapefile, etc)
         */
        async download ()
        {
            await this.$store.dispatch('DOWNLOAD_DATA', {
                type: this.type,
                locationId: this.locationId,
                email: this.email
            });
            this.email = '';
            this.emailEntered = false;
        },

        textEntered()
        {
            let regexEmail = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/
            if(this.email.match(regexEmail)) 
            {
                this.emailEntered = true;
            }
            else
            {
                this.emailEntered = false;
                this.disableDownloadButton;
            }
        }
    },

    computed: {

        isAuth ()
        {
            return this.$store.state.user.auth;
        },

        disableDownloadButton()
        {
            if (this.isAuth)
            {
                return false;
            }
            else 
            {
                return !this.emailEntered;
            }
        }
    }
}
</script>

