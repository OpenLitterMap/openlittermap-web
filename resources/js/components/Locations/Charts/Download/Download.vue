<template>
    <div>
        <h1 class="title is-3">Free and Open Verified Citizen Science Data on Plastic Pollution.</h1>
        <h1 class="title is-3">Let's stop plastic going into the ocean.</h1>

        <p class="mb1" v-show="!isAuth">Please enter an email address to which the data will be sent:</p>

        <input          
            v-show="!isAuth"
            class="input mb1em fs125"
            placeholder="you@email.com"
            type="email"
            name="email"
            required
            v-model="email"
            @keydown="textEntered"
            autocomplete="email"
        />

        <button :disabled="disableDownloadButton" class="button is-large is-danger mb1" @click="download">Download</button>

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
        },

        textEntered()
        {
            this.emailEntered = true;
        }
    },

    computed: {

        isAuth ()
        {
            return this.$store.state.user.auth;
        },

        disableDownloadButton()
        {
            if(this.isAuth)
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

