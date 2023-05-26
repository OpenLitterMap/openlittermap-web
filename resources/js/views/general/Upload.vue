<template>
    <section class="section hero fullheight is-warning is-bold upload-section">
        <div class="container ma has-text-centered" style="flex-grow: 0; width: 100%;">
            <h1 class="title is-1 drop-title">
                {{ $t('upload.click-to-upload') }}
            </h1>

            <p v-if="team" class="subtitle mt-1">
                {{ $t('common.team') }}: <strong>{{team}}</strong>
            </p>

            <vue-dropzone
                id="customdropzone"
                :options="options"
                :use-custom-slot="true"
                @vdropzone-error="failed"
                @vdropzone-files-added="uploadStarted"
                @vdropzone-file-added="uploadStarted"
                @vdropzone-queue-complete="uploadCompleted"
                @vdropzone-total-upload-progress="uploadProgress"
            >
                <i class="fa fa-image upload-icon" aria-hidden="true"/>
            </vue-dropzone>

            <div class="wrapper" v-show="progress">
                <div class="progress-bar">
                    <span class="progress-bar-fill has-background-info" :style="{width: `${progress}%`}"></span>
                </div>
            </div>

            <h2 class="title is-2">
                {{ $t('upload.thank-you') }}
            </h2>

            <h3 class="title is-3 mb2r">
                {{ $t('upload.need-tag-litter') }}
            </h3>

            <button class="button is-medium is-info hov" @click="tag" v-if="showTagLitterButton">
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
                url: '/upload',
                thumbnailWidth: 150,
                maxFilesize: 20,
                headers: {
                    'X-CSRF-TOKEN': window.axios.defaults.headers.common['X-CSRF-TOKEN']
                },
                includeStyling: true,
                duplicateCheck: true,
                paramName: 'file',
                acceptedFiles: 'image/*,.heic,.heif'
            },
            showTagLitterButton: true,
            progress: 0
        };
    },
    computed: {
        team () {
            return this.$store.state.user.user.team?.name;
        }
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
         *
         * @see https://github.com/rowanwins/vue-dropzone/issues/238#issuecomment-603003150
         */
        failed (file, message)
        {
            let element = file.previewElement.querySelectorAll('.dz-error-message span');
            if (element && element.length) element[0].textContent = message.message;

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
         * A file has been added to the Dropzone
         */
        uploadStarted (file)
        {
            this.showTagLitterButton = false;
        },

        /**
         * All file uploads have finished
         */
        uploadCompleted (response)
        {
            this.showTagLitterButton = true;
        },

        /**
         * Redirect the user to /tag
         */
        tag ()
        {
            this.$router.push({ path: '/tag' });
        },

        uploadProgress (totalUploadProgress, totalBytes, totalBytesSent)
        {
            this.progress = totalUploadProgress;
        }
    }
};
</script>

<style scoped lang="scss">
    @import '../../styles/variables.scss';

    .drop-title {
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
            bottom: 1rem;
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

    .wrapper {
        margin: 0 4rem 2rem 4rem;
    }

    .progress-bar {
        width: 100%;
        background-color: #ffffff;
        border-radius: 2px;
    }

    .progress-bar-fill {
        display: block;
        height: 4px;
        border-radius: 2px;
        transition: width 500ms ease-in-out;
    }
</style>
