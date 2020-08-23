<template>
    <section class="section hero fullheight is-warning is-bold" style="padding: 5em;">

        <div class="container ma has-text-centered" style="flex-grow: 0; width: 100%;">

            <h1 class="title is-1 drop-title">Click to upload or drop your photos</h1>

            <vue-dropzone id="dropzone" class="mb3" :options="options" />

            <h2 class="title is-2">Thank you!</h2>

            <h3 class="title is-3 mb2r">Next, you need to tag the litter</h3>

            <button class="button is-medium is-info hov" @click="tag">Tag Litter</button>
        </div>
    </section>
</template>

<script>
import vue2Dropzone from 'vue2-dropzone'

export default {
    name: 'Upload',
    components: {
        vueDropzone: vue2Dropzone
    },
    data ()
    {
        return {
            options: {
                url: '/submit',
                thumbnailWidth: 150,
                maxFilesize: 20,
                headers: {
                    'X-CSRF-TOKEN': window.axios.defaults.headers.common['X-CSRF-TOKEN']
                },
                includeStyling: true,
                duplicateCheck: true,
                paramName: 'file'
            }
        }
    },
    methods: {

        /**
         * Redirect the user to /tag
         */
        tag ()
        {
            this.$router.push({ path: '/tag' });
        }
    }
}
</script>

<style scoped lang="scss">

    .drop-title {
        margin-bottom: 1.5em;
        text-align: center;
    }

</style>
