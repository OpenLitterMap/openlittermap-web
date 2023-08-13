<template>
    <div>
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
</template>

<script>
export default {
    name: "CreateMerchant",
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
         * Shortcut to merchant object
         *
         * default = {}
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

            this.processing = false;
        }
    }
}
</script>

<style scoped>
    .merchant-label {
        margin-bottom: 5px;
    }

    .merchant-input {
        margin-bottom: 10px;
    }
</style>
