<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4"> {{ $t('settings.littercoin.littercoin-header') }}</h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-two-thirds is-offset-1">
                <p>Littercoin 2.0</p>

                <p>Total Littercoin earned: {{ littercoinOwed }}</p>
                <p>Littercoin received: {{ littercoinPaid }}</p>
                <p>From database: {{ this.littercoins.length }}</p>
                <p class="mb-4">Littercoin due: {{ this.littercoinOwed - this.littercoinPaid }} </p>

                <input
                    class="input"
                    v-model="wallet_address"
                />

                <button
                    class="button is-medium is-primary"
                    @click="submit"
                >Submit</button>

            </div>
        </div>
	</div>
</template>

<script>

export default {
    name: 'Littercoin',
    async created () {
        await axios.get('/littercoin')
            .then(response => {
                console.log('littercoin', response);

                this.littercoins = response.data.littercoin;
            })
            .catch(error => {
                console.error('littercoin', error);
            });
    },
    data () {
        return {
            loading :true,
            littercoins: [],
            wallet_address: ""
        };
    },
    computed: {
        /**
         * Total number of Littercoin the User is owed
         */
        littercoinOwed () {
            return this.user.littercoin_owed + this.user.littercoin_allowance + this.littercoins.length;
        },

        /**
         * Total number of Littercoin the user has received
         */
        littercoinPaid () {
            return this.user.littercoin_paid;
        },

        /**
         * Shortcut to User object
         */
        user () {
            return this.$store.state.user.user;
        }
    },
    methods: {
        /**
         *
         */
        async submit () {
            // Part 1:

            // construct the transaction

            // open browser wallet

            // user has to sign it

            // Part 2:

            // Send the transaction to the backend,
            // to sign again with a private key

            await axios.post('/littercoin', {
                transaction: 'hex'
            })
            .then(response => {
                console.log('littercoin', response);
            })
            .catch(error => {
                console.error('littercoin', error);
            });
        }
    }
}
</script>
