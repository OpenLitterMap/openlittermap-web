<template>
    <section class="section hero fullheight is-warning is-bold upload-section">
        <div class="container ma has-text-centered" style="flex-grow: 0; width: 100%;">
            <h1 class="title is-1 drop-title">
                {{ $t('upload.click-to-upload') }}
            </h1>

            <vue-dropzone id="customdropzone" :options="options" :use-custom-slot="true" @vdropzone-error="failed">
                <i class="fa fa-image upload-icon" aria-hidden="true" />
            </vue-dropzone>

            <h2 class="title is-2">
                {{ $t('upload.thank-you') }}
            </h2>

            <h3 class="title is-3 mb2r">
                {{ $t('upload.need-tag-litter') }}
            </h3>

            <button class="button is-medium is-info hov" @click="tag">
                {{ $t('upload.tag-litter') }}<i class="fa fa-arrow-right" aria-hidden="true" />
            </button>
        </div>
    </section>
</template>

<script>
import vue2Dropzone from 'vue2-dropzone';
import Vue from 'vue';

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
        };
    },
    async created ()
    {
        // user object is not passed when the user logs in. We need to get it here
        if (Object.keys(this.$store.state.user.user.length === 0))
        {
            await this.$store.dispatch('GET_CURRENT_USER');
        }
    },
    methods: {
        /**
         * Show the error when the user hovers over the X
         *
         * Todo: Show the error without having to hover over the X.
         */
        failed (file, message)
        {
            const elements = document.querySelectorAll('.dz-error-message span');

            const lastElement = elements[elements.length - 1];

            lastElement.textContent = message.message;

            const title = this.$t('notifications.error');
            const body = message.message;

            Vue.$vToastify.error({
                title,
                body,
                position: 'top-right',
                type: 'error'
            });
        },

        /**
         * Redirect the user to /tag
         */
        tag ()
        {
            this.$router.push({ path: '/tag' });
        },
    }
};
</script>

<style scoped lang="scss">
    @import '../../styles/variables.scss';

    .drop-title {
        margin-bottom: 1.5em;
        text-align: center;
    }

    .upload-section {
        padding: 5rem;
        .fa-arrow-right{
            margin: {
                left: 10px;
            }
        }
    }

    #customdropzone {
        border: 2px $drop-zone-border dashed;
        border-radius: 10px;
        margin: {
            bottom: 3rem;
        }
    }

    @include media-breakpoint-up(lg) {
        #customdropzone {
            margin: {
                left: 4rem;
                right: 4rem;
            };
        }
    }

    @include media-breakpoint-down(sm){
        .drop-title {
            font-size: 2.5rem;
        }

        .upload-section {
            padding: 2rem;
        }
    }

    .upload-icon {
        font-size: 60px;
        &:hover{
            transform: translate(0px, -5px);
            transition-duration: 0.3s
        }
    }
</style>
