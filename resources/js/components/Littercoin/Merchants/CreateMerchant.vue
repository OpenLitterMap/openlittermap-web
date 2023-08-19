<template>
    <div>
        <p class="title is-1">
            {{
                this.merchantWasCreated ? 'Upload photos' : 'Create a Merchant!'
            }}
        </p>

        <div v-if="!merchantWasCreated">
            <p class="merchant-label">Name</p>
            <input
                class="input w50 merchant-input"
                v-model="name"
                placeholder="Name of the business"
            />

            <div class="mb1">

                <p v-if="!merchant.lat">
                    Click anywhere on the map to set the location
                </p>

                <div v-else>
                    <p>
                        Lat: {{ merchant.lat }}
                    </p>

                    <p>
                        Lon: {{ merchant.lon }}
                    </p>
                </div>
            </div>

            <p class="merchant-label">Email</p>
            <input
                class="input w50 merchant-input"
                v-model="email"
                placeholder="Enter their email"
                type="email"
            />

            <p class="merchant-label">Website</p>
            <input
                class="input w50 merchant-input"
                v-model="website"
                placeholder="https://website.com"
            />

            <p class="merchant-label">About</p>
            <input
                class="input w50 merchant-input"
                v-model="about"
                placeholder="Information or keywords"
                style="margin-bottom: 2em;"
            />

            <br>

            <button
                class="button is-medium is-primary"
                :class="processing ? 'is-loading' : ''"
                :disabled="processing"
                @click="submit"
            >
                Create
            </button>
        </div>

        <vue-dropzone
            v-else
            id="dropzone"
            :options="dropzoneOptions"
            :use-custom-slot="true"
            @vdropzone-error="failed"
        >
            <i class="fa fa-image upload-icon" aria-hidden="true"/>
        </vue-dropzone>
    </div>
</template>

<script>
import vue2Dropzone from "vue2-dropzone";
import Vue from "vue";

export default {
    name: "CreateMerchant",
    components: {
        vueDropzone: vue2Dropzone
    },
    data () {
        return {
            name: "",
            address: "",
            email: "",
            website: "",
            about: "",
            processing: false,
            merchantWasCreated: false,
            dropzoneOptions: {
                url: '/merchants/upload-photo',
                thumbnailWidth: 150,
                maxFilesize: 20,
                headers: {
                    'X-CSRF-TOKEN': window.axios.defaults.headers.common['X-CSRF-TOKEN']
                },
                paramName: 'file',
                acceptedFiles: 'image/*,.heic,.heif',
                params: {
                    merchantId: 0
                }
            }
        }
    },
    computed: {
        /**
         * Shortcut to merchant object
         *
         * default = { lat: 0, lon : 0 }
         */
        merchant ()
        {
            return this.$store.state.merchants.merchant;
        },
    },
    methods: {
        /**
         * The User with role helper wants to create a Merchant
         *
         * Location must be selected
         */
        async submit ()
        {
            if (this.merchant.lat === 0 && this.merchant.lon === 0)
            {
                alert("Please select a location");
                return;
            }

            if (this.name === "" || this.email === "" || this.website === "" || this.about === "")
            {
                alert("Please enter something into all fields");
                return;
            }

            this.processing = true;

            await this.$store.dispatch('CREATE_MERCHANT', {
                name: this.name,
                lat: this.merchant.lat,
                lon: this.merchant.lon,
                email: this.email,
                about: this.about,
                website: this.website
            });

            this.name = "";
            this.email = "";
            this.about = "";
            this.website = "";
            this.merchantWasCreated = true;
            this.dropzoneOptions.params.merchantId = this.merchant.id;

            this.processing = false;
        },

        /**
         * Show the error when the user hovers over the X
         *
         * @see https://github.com/rowanwins/vue-dropzone/issues/238#issuecomment-603003150
         */
        failed (file, message)
        {
            const errorMessage = message.message;

            const errorElement = file.previewElement.querySelector('.dz-error-message');
            if (errorElement)
            {
                errorElement.textContent = errorMessage;
                errorElement.style.opacity = 1;
                errorElement.style.pointerEvents = 'auto';
            }

            const title = this.$t('notifications.error');
            const body = message.message;

            Vue.$vToastify.error({
                title,
                body,
                position: 'top-right',
                type: 'error'
            });
        },
    }
}
</script>

<style scoped lang="scss">
    .merchant-label {
        margin-bottom: 5px;
    }

    .merchant-input {
        margin-bottom: 10px;
    }

    #dropzone {
        min-height: 150px;
        border: 2px solid rgba(0, 0, 0, 0.3);
        background: white;
        padding: 20px 20px;
        max-width: 22em
    }

    #dropzone .dz-preview.dz-error .dz-error-message {
        opacity: 1;
        pointer-events: auto;
    }

</style>
