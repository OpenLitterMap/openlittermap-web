<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4"> {{ $t('settings.littercoin.littercoin-header') }}</h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-two-thirds is-offset-1">
                <p><b>Littercoin Smart Contract </b></p>
                <p>Total Ada: {{ this.adaAmount }}</p>
                <p>Total Littercoin: {{ this.lcAmount }}</p>
                <p>Ratio: {{ this.ratio }}</p>
                <p>Script Address: <a :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>
                <p>Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" >{{ this.lcScriptName }}</a></p>
                <hr>

                <p><b>Total Littercoin earned: {{ littercoinOwed }}</b></p>
                <p>Littercoin received: {{ littercoinPaid }}</p>
                <p>From database: {{ this.littercoins.length }}</p>
                <p class="mb-4">Littercoin due: {{ this.littercoinOwed - this.littercoinPaid }} </p>
                <hr>
                <div>
                    <form @submit.prevent="submitForm" v-if="!formSubmitted">
                        <span>Select Your Wallet</span><br>
                        <label>Nami</label>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            value="nami" 
                        /><br>
                        <label>Eternl</label>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            value="eternl" 
                        /><br>
                        <span>Destination Wallet Address</span>
                        <input
                            class="input"
                            v-model="destAddr"
                            placeholder="Enter destination wallet address" 
                        />
                        <input 
                            class="submit" 
                            type="submit" 
                            value="Submit Tx"
                        >
                    </form>
                    <div v-if="formSubmitted">
                        <h3>Tx Submitted</h3>
                        <p><a :href="this.txIdURL" target="_blank" rel="noopener noreferrer" >{{ this.txId }}</a></p>
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
        await axios.get('/littercoin-info')
            .then(async response => {
                //console.log("env", process.env.NETWORK);
                console.log('littercoin-info', response);
                const lcInfo = await JSON.parse(response.data); 
                if (lcInfo.status == 200) {
                    //console.log("lcInfo", lcInfo);
                    this.adaAmount = lcInfo.payload.list[0].int / 1000000;
                    this.lcAmount = lcInfo.payload.list[1].int;
                    this.ratio = this.adaAmount / this.lcAmount;
                    this.lcAddr = lcInfo.payload.addr;
                    this.lcAddrURL = "https://preprod.cexplorer.io/address/" + lcInfo.payload.addr;
                    this.lcScriptName = lcInfo.payload.scriptName;
                    this.lcScriptURL = "/contracts/" + lcInfo.payload.scriptName;
                } else {
                    throw console.error("Could not fetch littercoin contract info");
                }
            })
            .catch(error => {
                console.error('littercoin-info', error);
            });
    },
 
    data () {
        return {
            loading :true,
            adaAmount: 0,
            lcAmount: 0,
            ratio: 0,
            lcAddr: "",
            lcAddrURL: "",
            lcScriptName: "",
            lcScriptURL: "",
            littercoins: [],
            lcQty: 0,
            destAddr: "",
            walletChoice: "",
            formSubmitted: false,
            txId: "",
            txIdURL: ""
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
        },
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

            //console.log("hexChangeAddr: ", hexChangeAddr);
            //console.log("cborUtxos: ", cborUtxos);
            
            await axios.post('/littercoin-mint-tx', {
                destAddr: this.destAddr,
                changeAddr: hexChangeAddr,
                utxos: cborUtxos
            })
            .then(async response => {
                console.log("mintTx: ", response);
                const mintTx = await JSON.parse(response.data);

                if (mintTx.status == 200) {

                    console.log("Get wallet signature");
                    // Get user to sign the transaction
                    const walletSig = await walletAPI.signTx(mintTx.cborTx, true);
                    //console.log("walletSig: ", walletSig);
                    //console.log("mintTx.cborTx: ", mintTx.cborTx);

                    console.log("Submit transaction...");
                    await axios.post('/littercoin-submit-mint-tx', {
                        cborSig: walletSig,
                        cborTx: mintTx.cborTx
                    })
                    .then(async response => {
                        console.log('littercoin-submit-mint-tx: ', response);
                        const submitTx = await JSON.parse(response.data);
                        if (submitTx.status == 200) {
                            this.txId = submitTx.txId;
                            this.txIdURL = "https://preprod.cexplorer.io/tx/" + submitTx.txId;
                        } else {
                            console.error("Could not submit transaction");
                        }
                    })
                    .catch(error => {
                        console.error('littercoin-submit-mint-tx: ', error);
                    });
            
                } else {
                    console.error("Mint transaction was not successful");
                }
            })
            .catch(error => {
                console.error('littercoin-mint-tx', error);
            });
        }
    }
}
</script>
