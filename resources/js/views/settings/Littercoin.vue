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
                
                <div>
                    <form @submit.prevent="submitForm" v-if="!formSubmitted">
                        <span>Select Your Wallet</span><br>
                        <label>Nami</label>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            value="nami" 
                        /><br>
                        <span>Destination Wallet Address</span>
                        <input
                            class="input"
                            v-model="walletAddress"
                            placeholder="Enter wallet address" 
                        />
                        <input 
                            class="submit" 
                            type="submit" 
                            value="Submit Tx"
                        >
                    </form>
                    <div v-if="formSubmitted">
                        <h3>Tx Submitted</h3>
                        <p>Tx Id: </p>
                    </div>
                </div>
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
            walletAddress: "",
            walletChoice: "",
            formSubmitted: false
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
        submitForm: function () {
            this.formSubmitted = true;
            this.submit();
        },
        async submit() {

            // Part 1: 
            // Connect to the user's wallet
            var walletAPI;
            if (this.walletChoice === "nami") {
                walletAPI = await window.cardano.nami.enable();
            } else if (this.walletChoice === "eternl") {
                walletAPI = await window.cardano.eternl.enable(); 
            } else {
                throw console.error("No wallet selected");
            } 
            
            // get the UTXOs from wallet,
            const cborUtxos = await walletAPI.getUtxos();

            // Get the change address from the wallet
            const hexChangeAddr = await walletAPI.getChangeAddress();


            // Part 2:
            // Construct and signed the transaction in the
            // backend with private key. 
            await axios.post('/littercoin', {
                destAddr: this.walletAddress,
                changeAddr: hexChangeAddr,
                utxos: cborUtxos
            })
            .then(response => {
                console.log('littercoin', response);
            })
            .catch(error => {
                console.error('littercoin', error);
            });

            // Part 3:
            // Have the user sign and submit the finalized transaction.


        }
    }
}
</script>
