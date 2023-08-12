<template>
    <div class="h100">
        <div class="columns h100">

            <div class="column is-half">
                <MerchantMap />
            </div>

            <div class="column is-half pt5">
                <p class="title is-1">
                    Create a Merchant!
                </p>

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
                />

                <br>

                <button
                    class="button is-large is-primary"
                    :class="processing ? 'is-loading' : ''"
                    :disabled="processing"
                    @click="submit"
                >
                    Create
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import MerchantMap from "../../components/Admin/Merchants/MerchantMap";

export default {
    name: "Merchants",
    components: {
        MerchantMap
    },
    data () {
        return {
            name: "",
            address: "",
            email: "",
            website: "",
            about: "",
            processing: false
        }
    },
    computed: {
        /**
         * Shortcut to cleanup/s state
         */
        merchant ()
        {
            return this.$store.state.merchants.merchant;
        },
    },
    methods: {
        /**
         *
         */
        async submit () {
            this.processing = true;

            await this.$store.dispatch('CREATE_MERCHANT', {
                name: this.name,
                lat: this.merchant.lat,
                lon: this.merchant.lon,
                email: this.email,
                about: this.about,
                website: this.website
            });

            this.processing = false;
        }
    }
}
</script>

<style scoped>

    .merchant-map {

    }

    .merchant-label {
        margin-bottom: 5px;
    }

    .merchant-input {
        margin-bottom: 10px;
    }
</style>
