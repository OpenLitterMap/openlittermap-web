<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4"> {{ $t('settings.littercoin.littercoin-header') }}</h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-two-thirds is-offset-1">
                <p><h1 class="title is-4">Littercoin Smart Contract </h1></p>
                <p>Total Ada: {{ this.adaAmount.toLocaleString() }}</p>
                <p>Total Littercoin: {{ this.lcAmount.toLocaleString() }}</p>
                <p>Ratio: {{ this.ratio.toLocaleString() }}</p>
                <p>Script Address: <a style="font-size: small;" :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>
                <p>Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" >{{ this.lcScriptName }}</a></p>
                <hr>

                <p><h1 class="title is-4">Littercoin Stats</h1></p>
                <p>Total Littercoin Earned: {{ littercoinOwed }}</p>
                <p>Total Littercoin Received: {{ littercoinPaid }}</p>
                <p>Littercoin Due: {{ this.littercoinOwed - this.littercoinPaid }}</p>
                <hr>
                <div>
                    
                    <form 
                        method="post"
                        @submit.prevent="submitMintForm" 
                        v-if="!txSuccess" >
                        <p><h1 class="title is-4">Select Your Wallet</h1></p>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            value="nami" 
                        /> &nbsp;
                        <img src = "/assets/icons/littercoin/nami.png" alt="Nami Wallet" style="width:20px;height:20px;"/>
                        <label>&nbsp; Nami</label>
                        <br>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            value="eternl" 
                        />&nbsp;
                        <img src = "/assets/icons/littercoin/eternl.png" alt="Eternl Wallet" style="width:20px;height:20px;"/>
                        <label>&nbsp; Eternl</label>
                        <hr>
                        <p><h1 class="title is-4">Mint Littercoin</h1></p>
                        <span>Destination Wallet Address</span>
                        <input
                            class="input"
                            v-model="destAddr"
                            placeholder="Enter destination wallet address" 
                        />
                        <div style="text-align: center; padding-bottom: 1em;">
                            <button
                                class="button is-medium is-primary mb1 mt1"
                                :class="formSubmitted ? 'is-loading' : ''"
                                :disabled="checkDisabled"
                            >Submit Tx</button>
                        </div>
                    </form>
                    <div v-if="txSuccess">
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
            txSuccess: false,
            txId: "",
            txIdURL: ""
        };
    },
    computed: {
        /**
         * Return true to disable the button
         */
	    checkDisabled ()
        {
            if (this.formSubmitted) return true

            return false;
        },
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
        submitMintForm: function () {
            
            if ( !this.walletChoice )
            {
                alert ('Please select a wallet');
                return;
            }
            if ( !this.destAddr.match(/^addr/))
            {
                alert ('Please enter a valid destination address');
                return
            }
            this.formSubmitted = true;
            this.submitMint();
        },
        async submitMint() {

            // Connect to the user's wallet
            var walletAPI;
            if (this.walletChoice === "nami") {
                walletAPI = await window.cardano.nami.enable();
            } else if (this.walletChoice === "eternl") {
                walletAPI = await window.cardano.eternl.enable(); 
            } else {
                this.formSubmitted = true;
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
                            this.txSuccess = true;
                        } else {
                            alert ('Mint transaction could not be submitted, please try again');
                            this.formSubmitted = false;
                            console.error("Could not submit transaction");
                        }
                    })
                    .catch(error => {
                        alert ('Mint transaction could not be submitted, please try again');
                        this.formSubmitted = false;
                        console.error('littercoin-submit-mint-tx: ', error);
                    });
            
                } else {
                    alert ('Mint transaction could not be submitted, please try again');
                    this.formSubmitted = false;
                    console.error("Mint transaction was not successful");
                }
            })
            .catch(error => {
                alert ('Mint transaction could not be submitted, please try again');
                this.formSubmitted = false;
                console.error('littercoin-mint-tx', error);
            });
        }
    }
}
</script>
