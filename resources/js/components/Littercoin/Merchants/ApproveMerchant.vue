<template>
    <div>
        <p v-if="loading">Loading...</p>

        <p v-else-if="this.merchant.lat === 0 && this.merchant.lon === 0">Done - Thank you!</p>

        <div v-else>
            <p>Name: {{ this.merchant.name }}</p>
            <p>About: {{ this.merchant.about }}</p>
            <p>Website: {{ this.merchant.website }}</p>

            <div class="flex mt1">
                <button
                    @click="approveMerchant"
                    class="button is-medium is-primary mr1"
                    :class="processing ? 'is-loading' : ''"
                    :disabled="processing"
                >
                    Approve
                </button>

                <button
                    @click="deleteMerchant"
                    class="button is-medium is-danger"
                    :class="processingDelete ? 'is-loading' : ''"
                    :disabled="processingDelete"
                >
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "ApproveMerchant",
    data() {
        return {
            processing: false,
            processingDelete: false,
            loading: true
        }
    },
    async created () {
        this.loading = true;

        await this.$store.dispatch('GET_NEXT_MERCHANT_TO_APPROVE');

        this.loading = false;
    },
    computed: {
        /**
         * Shortcut to cleanup/s state
         */
        merchant() {
            return this.$store.state.merchants.merchant;
        },
    },
    methods: {
        /**
         * Admin only
         */
        async approveMerchant () {
            this.processing = true;

            await this.$store.dispatch('APPROVE_MERCHANT');

            this.processing = false;
        },

        /**
         * Admin only
         */
        async deleteMerchant () {
            this.processingDelete = true;

            await this.$store.dispatch('DELETE_MERCHANT');

            this.processingDelete = false;
        }
    }
}
</script>

<style scoped>

</style>
