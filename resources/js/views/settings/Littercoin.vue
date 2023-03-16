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
                <p>Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" >{{ this.lcScriptName }}</a></p>
                <p>Address: <a style="font-size: small;" :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>
                <hr>

                <p><h1 class="title is-4">My Littercoin</h1></p>
                <p>Total Littercoin Earned: {{ littercoinOwed }}</p>
                <p>Total Littercoin Received: {{ littercoinPaid }}</p>
                <p>Littercoin Due: {{ this.littercoinOwed - this.littercoinPaid }}</p>
                <hr>
                <div>
                    <form 
                        method="post"
                        @submit.prevent="submitForm('mint')" 
                        v-if="!mintSuccess" 
                    >
                        <p><h1 class="title is-4">Select Your Wallet</h1></p>
                        <p>
                            <input 
                                type="radio" 
                                v-model="walletChoice" 
                                value="nami" 
                            /> &nbsp;
                            <img src = "/assets/icons/littercoin/nami.png" alt="Nami Wallet" style="width:20px;height:20px;"/>
                            <label>&nbsp; Nami</label>
                        </p>
                        <br>
                        <p>
                            <input 
                                type="radio" 
                                v-model="walletChoice" 
                                value="eternl" 
                            />&nbsp;
                            <img src = "/assets/icons/littercoin/eternl.png" alt="Eternl Wallet" style="width:20px;height:20px;"/>
                            <label>&nbsp; Eternl</label>
                        </p>
                        <hr>
                        <p><h1 class="title is-4">Mint Littercoin</h1></p>
                        Destination Wallet Address
                        <input
                            class="input"
                            v-model="mintDestAddr"
                            placeholder="Enter destination wallet address" 
                        />
                        <div style="text-align: center; padding-bottom: 1em;">
                            <button
                                class="button is-medium is-primary mb1 mt1"
                                :class="mintFormSubmitted ? 'is-loading' : ''"
                                :disabled="checkMintDisabled"
                            >Submit Tx</button>
                        </div>
                    </form>
                    <div v-if="mintSuccess">
                        <p><h1 class="title is-4">Mint Littercoin Success!!!</h1></p>
                        <p>Please wait approximately 20-60 seconds for the littercoin to show up in your wallet.</p>
                        <p>To track this transaction on the blockchain, select the TxId link below.</p>
                        <p>TxId: <a style="font-size: small;" :href="this.mintTxIdURL" target="_blank" rel="noopener noreferrer" >{{ this.mintTxId }}</a></p>
                    </div>
                </div>
                <hr>
                <div>
                    <form 
                        method="post"
                        @submit.prevent="submitForm('merchant')" 
                        v-if="!merchSuccess && isAdmin" 
                    >
                        <p><h1 class="title is-4">Mint Merchant Token</h1></p>
                        Destination Wallet Address
                        <input
                            class="input"
                            v-model="merchDestAddr"
                            placeholder="Enter destination wallet address" 
                        >
                        <div style="text-align: center; padding-bottom: 1em;">
                            <button
                                class="button is-medium is-primary mb1 mt1"
                                :class="merchFormSubmitted ? 'is-loading' : ''"
                                :disabled="checkMerchDisabled"
                            >Submit Tx</button>
                        </div>                    
                    </form>
                    <div v-if="merchSuccess && isAdmin">
                        <p><h1 class="title is-4">Mint Merchant Token Success!!!</h1></p>
                        <p>Please wait approximately 20-60 seconds for the merchant token to show up in the wallet.</p>
                        <p>To track this transaction on the blockchain, select the TxId link below.</p>
                        <p>TxId: <a style="font-size: small;" :href="this.merchTxIdURL" target="_blank" rel="noopener noreferrer" >{{ this.merchTxId }}</a></p>
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
            walletChoice: "",
            mintDestAddr: "",
            merchDestAddr: "",
            mintFormSubmitted: false,
            merchFormSubmitted: false,
            mintSuccess: false,
            merchSuccess: false,
            mintTxId: "",
            merchTxId: "",
            mintTxIdURL: "",
            merchTxIdURL: ""
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
        /**
         * Is the user an admin
         */
        isAdmin ()
        {
            return (this.$store.state.user.admin);
            //return true;
        },
         /**
         * Return true to disable the button
         */
	    checkMintDisabled ()
        {
            if (this.mintFormSubmitted) return true

            return false;
        },
                /**
         * Return true to disable the button
         */
	    checkMerchDisabled ()
        {
            if (this.merchFormSubmitted) return true

            return false;
        },
    },
    methods: {
  
        /**
         * Submit a minting transaction to the cardano blockchain network
         */
         submitForm: function (type) {
            
            if ( !this.walletChoice )
            {
                alert ('Please select a wallet');
                return;
            }
            if (type === 'mint') 
            {
                if ( !this.mintDestAddr.match(/^addr/))
                {
                    alert ('Please enter a valid mint littercoin destination address');
                    return
                }
                this.mintFormSubmitted = true;
                this.submitMint();
            }
            if (type === 'merchant') 
            {
                if ( !this.merchDestAddr.match(/^addr/))
                {
                    alert ('Please enter a valid mint merchant token destination address');
                    return
                }
                this.merchFormSubmitted = true;
                this.merchMint();
            }
        },
        async submitMint() {

            // Connect to the user's wallet
            var walletAPI;
            if (this.walletChoice === "nami") {
                walletAPI = await window.cardano.nami.enable();
            } else if (this.walletChoice === "eternl") {
                walletAPI = await window.cardano.eternl.enable(); 
            } else {
                alert('No wallet selected');
                this.mintFormSubmitted = false;
                throw console.error("No wallet selected");
            } 
            
            // get the UTXOs from wallet,
            const cborUtxos = await walletAPI.getUtxos();

            // Get the change address from the wallet
            const hexChangeAddr = await walletAPI.getChangeAddress();

            //console.log("hexChangeAddr: ", hexChangeAddr);
            //console.log("cborUtxos: ", cborUtxos);
            
            await axios.post('/littercoin-mint-tx', {
                destAddr: this.mintDestAddr,
                changeAddr: hexChangeAddr,
                utxos: cborUtxos
            })
            .then(async response => {
                console.log("littercoin-mint-tx: ", response);
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
                            this.mintTxId = submitTx.txId;
                            this.mintTxIdURL = "https://preprod.cexplorer.io/tx/" + submitTx.txId;
                            this.mintSuccess = true;
                        } else {
                            alert ('Littercoin Mint transaction could not be submitted, please try again');
                            this.mintFormSubmitted = false;
                            console.error("Could not submit transaction");
                        }
                    })
                    .catch(error => {
                        alert ('Littercoin Mint transaction could not be submitted, please try again');
                        this.mintFormSubmitted = false;
                        console.error("littercoin-submit-mint-tx: ", error);
                    });
            
                } else {
                    alert ('Littercoin Mint transaction could not be submitted, please try again');
                    this.mintFormSubmitted = false;
                    console.error("Littercoin Mint transaction was not successful");
                }
            })
            .catch(error => {
                alert ('Littercoin Mint transaction could not be submitted, please try again');
                this.mintFormSubmitted = false;
                console.error("littercoin-mint-tx", error);
            });
        },
        async merchMint() {

            // Connect to the user's wallet
            var walletAPI;
            if (this.walletChoice === "nami") {
                walletAPI = await window.cardano.nami.enable();
            } else if (this.walletChoice === "eternl") {
                walletAPI = await window.cardano.eternl.enable(); 
            } else {
                alert('No wallet selected');
                this.merchFormSubmitted = false;
                throw console.error("No wallet selected");
            } 

            // get the UTXOs from wallet,
            const cborUtxos = await walletAPI.getUtxos();

            // Get the change address from the wallet
            const hexChangeAddr = await walletAPI.getChangeAddress();

            //console.log("hexChangeAddr: ", hexChangeAddr);
            //console.log("cborUtxos: ", cborUtxos);

            await axios.post('/merchant-mint-tx', {
                destAddr: this.merchDestAddr,
                changeAddr: hexChangeAddr,
                utxos: cborUtxos
            })
            .then(async response => {
                console.log("merchant-mint-tx': ", response);
                const mintTx = await JSON.parse(response.data);

                if (mintTx.status == 200) {

                    console.log("Get wallet signature");
                    // Get user to sign the transaction
                    const walletSig = await walletAPI.signTx(mintTx.cborTx, true);
                    //console.log("walletSig: ", walletSig);
                    //console.log("mintTx.cborTx: ", mintTx.cborTx);

                    console.log("Submit transaction...");
                    await axios.post('/merchant-submit-mint-tx', {
                        cborSig: walletSig,
                        cborTx: mintTx.cborTx
                    })
                    .then(async response => {
                        console.log('merchant-submit-mint-tx: ', response);
                        const submitTx = await JSON.parse(response.data);
                        if (submitTx.status == 200) {
                            this.merchTxId = submitTx.txId;
                            this.merchTxIdURL = "https://preprod.cexplorer.io/tx/" + submitTx.txId;
                            this.merchSuccess = true;
                        } else {
                            alert ('Merchant Token Mint transaction could not be submitted, please try again');
                            this.merchFormSubmitted = false;
                            console.error("Merchant Token Mint transaction could not be submitted");
                        }
                    })
                    .catch(error => {
                        alert ('Merchant Token Mint transaction could not be submitted, please try again');
                        this.merchFormSubmitted = false;
                        console.error("merchant-submit-mint-tx: ", error);
                    });

                } else {
                    alert ('Merchant Token Mint transaction could not be submitted, please try again');
                    this.merchFormSubmitted = false;
                    console.error("Merchant Token Mint transaction could not be submitted");
                }
            })
            .catch(error => {
                alert ('Merchant Token Mint transaction could not be submitted, please try again');
                this.merchFormSubmitted = false;
                console.error("merchant-submit-mint-tx: ", error);
            });
        }
    }
}
</script>
