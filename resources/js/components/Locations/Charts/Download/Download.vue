<template>
    <div>
        <h1 class="title is-3">Free and Open Verified Citizen Science Data on Plastic Pollution.</h1>
        <h1 class="title is-3">Let's stop plastic going into the ocean.</h1>
        <button class="button is-large is-danger mb1" @click="download">Download</button>
        <p>&copy; OpenLitterMap & Contributors.</p>
    </div>
</template>

<script>
export default {
    name: 'Download',
    props: ['type'], // country, state or city
    methods: {

        /**
         * Download request
         *
         * Todo - Send csv file to email address and dispatch download event via horizon
         * Todo - add filters to download options
         * Todo - open up more options for downloads (geojson, shapefile, etc)
         */
        download ()
        {
            if (this.type === 'country')
            {
                window.location.href = window.location.href + '/' + this.$store.state.locations.country + '/download';
            }

            // window.location.href = window.location.href + '/get';
        },
    },
}
</script>

