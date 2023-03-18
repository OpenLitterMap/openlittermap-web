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
                    <form 
                        method="post"
                        @submit.prevent="submitForm('mint')" 
                        v-if="!mintSuccess" 
                        >
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
                <hr>
                    <form 
                        method="post"
                        @submit.prevent="submitForm('burn')" 
                        v-if="!burnSuccess" 
                        >
                        <p><h1 class="title is-4">Burn Littercoin</h1></p>
                        Number Of Littercoins To Burn
                        <input
                            class="input"
                            type="number"
                            v-model="lcQty"
                            placeholder="Enter number of littercoins to burn" 
                        />
                        <div style="text-align: center; padding-bottom: 1em;">
                            <button
                                class="button is-medium is-primary mb1 mt1"
                                :class="burnFormSubmitted ? 'is-loading' : ''"
                                :disabled="checkBurnDisabled"
                            >Submit Tx</button>
                        </div>
                    </form>
                    <div v-if="burnSuccess">
                        <p><h1 class="title is-4">Burn Littercoin Success!!!</h1></p>
                        <p>Please wait approximately 20-60 seconds for the Ada to show up in your wallet.</p>
                        <p>To track this transaction on the blockchain, select the TxId link below.</p>
                        <p>TxId: <a style="font-size: small;" :href="this.burnTxIdURL" target="_blank" rel="noopener noreferrer" >{{ this.burnTxId }}</a></p>
                    </div>
                <hr>
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
                <hr>
                    <form 
                        method="post"
                        @submit.prevent="submitForm('addAda')" 
                        v-if="!addAdaSuccess" 
                    >
                        <p><h1 class="title is-4">Add Ada To Littercoin Smart Contract</h1></p>
                        <input
                            class="input"
                            type="number"
                            v-model="addAdaQty"
                            placeholder="Enter amount of Ada to send" 
                        >
                        <div style="text-align: center; padding-bottom: 1em;">
                            <button
                                class="button is-medium is-primary mb1 mt1"
                                :class="addAdaFormSubmitted ? 'is-loading' : ''"
                                :disabled="checkAddAdaDisabled"
                            >Submit Tx</button>
                        </div>                    
                    </form>
                    <div v-if="addAdaSuccess">
                        <p><h1 class="title is-4">Add Ada Success!!!</h1></p>
                        <p>Please wait approximately 20-60 seconds and refresh this page for the Ada to show up in the Littercoin Smart Contract.</p>
                        <p>To track this transaction on the blockchain, select the TxId link below.</p>
                        <p>TxId: <a style="font-size: small;" :href="this.addAdaTxIdURL" target="_blank" rel="noopener noreferrer" >{{ this.addAdaTxId }}</a></p>
                    </div>
                <hr>
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
            addAdaQty: 0,
            lcQty: 0,
            mintFormSubmitted: false,
            burnFormSubmitted: false,
            merchFormSubmitted: false,
            addAdaFormSubmitted: false,
            mintSuccess: false,
            burnSuccess: false,
            merchSuccess: false,
            addAdaSuccess: false,
            mintTxId: "",
            burnTxId: "",
            merchTxId: "",
            addAdaTxId: "",
            mintTxIdURL: "",
            burnTxIdURL: "",
            merchTxIdURL: "",
            addAdaTxIdURL: ""

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
	    checkBurnDisabled ()
        {
            if (this.burnFormSubmitted) return true

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
        /**
         * Return true to disable the button
         */
	    checkAddAdaDisabled ()
        {
            if (this.addAdaFormSubmitted) return true

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
            if (type === 'burn') 
            {
                if ( !this.lcQty > 1)
                {
                    alert ('Minimum 1 littercoin required for burn');
                    return
                }
                this.burnFormSubmitted = true;
                this.submitBurn();
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
            if (type === 'addAda') 
            {
                if ( !this.addAdaQty > 2)
                {
                    alert ('Minimum 2 Ada donation amount required');
                    return
                }
                this.addAdaFormSubmitted = true;
                this.addAda();
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
            } 
            
            // get the UTXOs from wallet,
            const cborUtxos = await walletAPI.getUtxos();

            // Get the change address from the wallet
            const hexChangeAddr = await walletAPI.getChangeAddress();

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
                            console.error("Littercoin Mint transaction could not be submitted");
                            alert ('Littercoin Mint transaction could not be submitted, please try again');
                            this.mintFormSubmitted = false;
                        }
                    })
                    .catch(error => {
                        console.error("littercoin-submit-mint-tx: ", error);
                        alert ('Littercoin Mint transaction could not be submitted, please try again');
                        this.mintFormSubmitted = false;
                    });
            
                } else {
                    console.error("Littercoin Mint transaction could not be submitted");
                    alert ('Littercoin Mint transaction could not be submitted, please try again');
                    this.mintFormSubmitted = false;
                }
            })
            .catch(error => {
                console.error("littercoin-mint-tx", error);
                alert ('Littercoin Mint transaction could not be submitted, please try again');
                this.mintFormSubmitted = false;
            });
        },
        async submitBurn() {

        // Connect to the user's wallet
        var walletAPI;
        if (this.walletChoice === "nami") {
            walletAPI = await window.cardano.nami.enable();
        } else if (this.walletChoice === "eternl") {
            walletAPI = await window.cardano.eternl.enable(); 
        } else {
            alert('No wallet selected');
            this.burnFormSubmitted = false;
        } 

        // get the UTXOs from wallet,
        const cborUtxos = await walletAPI.getUtxos();

        // Get the change address from the wallet
        const hexChangeAddr = await walletAPI.getChangeAddress();

        await axios.post('/littercoin-burn-tx', {
            lcQty: this.lcQty,
            changeAddr: hexChangeAddr,
            utxos: cborUtxos
        })
        .then(async response => {
            console.log("littercoin-burn-tx: ", response);
            const burnTx = await JSON.parse(response.data);

            if (burnTx.status == 200) {

                console.log("Get wallet signature");
                // Get user to sign the transaction
                const walletSig = await walletAPI.signTx(burnTx.cborTx, true);

                console.log("Submit transaction...");
                await axios.post('/littercoin-submit-burn-tx', {
                    cborSig: walletSig,
                    cborTx: burnTx.cborTx
                })
                .then(async response => {
                    console.log('littercoin-submit-burn-tx: ', response);
                    
                    const submitTx = await JSON.parse(response.data);
                    if (submitTx.status == 200) {
                        this.burnTxId = submitTx.txId;
                        this.burnTxIdURL = "https://preprod.cexplorer.io/tx/" + submitTx.txId;
                        this.burnSuccess = true;
                    } else {
                        console.error("Littercoin Burn transaction was not successful");
                        alert ('Littercoin Burn transaction could not be submitted, please try again');
                        this.burnFormSubmitted = false;
                    }
                })
                .catch(error => {
                    console.error("littercoin-submit-burn-tx: ", error);
                    alert ('Littercoin Burn transaction could not be submitted, please try again');
                    this.burnFormSubmitted = false;
                });

            } else if (burnTx.status == 401) {
                console.error("Insufficient Littercoin In Wallet For Burn");
                alert ('Insufficient Littercoin In Wallet For Burn');
                this.burnFormSubmitted = false;
            } else if (burnTx.status == 402) {
                console.error("Merchant Token Not Found");
                alert ('Merchant Token Not Found');
                this.burnFormSubmitted = false;
            } else if (burnTx.status == 403) {
                console.error("Ada Withdraw amount is less than the minimum 2 Ada");
                alert ('Ada Withdraw amount is less than the minimum 2 Ada');
                this.burnFormSubmitted = false;
            } else if (burnTx.status == 404) {
                console.error("Insufficient funds in Littercoin contract");
                alert ('Insufficient funds in Littercoin contract');
                this.burnFormSubmitted = false;
            } else {
                console.error("Littercoin Burn transaction was not successful");
                alert ('Littercoin Burn transaction could not be submitted, please try again');
                this.burnFormSubmitted = false;
            }
        })
        .catch(error => {
            console.error("littercoin-burn-tx", error);
            alert ('Littercoin Burn transaction could not be submitted, please try again');
            this.burnFormSubmitted = false;
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
            } 

            // get the UTXOs from wallet,
            const cborUtxos = await walletAPI.getUtxos();

            // Get the change address from the wallet
            const hexChangeAddr = await walletAPI.getChangeAddress();

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
                            console.error("Merchant Token Mint transaction could not be submitted");
                            alert ('Merchant Token Mint transaction could not be submitted, please try again');
                            this.merchFormSubmitted = false;
                        }
                    })
                    .catch(error => {
                        console.error("merchant-submit-mint-tx: ", error);
                        alert ('Merchant Token Mint transaction could not be submitted, please try again');
                        this.merchFormSubmitted = false;
                    });

                } else if (mintTx.status == 400) {
                    console.error("Must be an admin user to mint a merchant token");
                    alert ('Must be an admin user to mint a merchant token');
                    this.merchFormSubmitted = false;
                }else {
                    console.error("Merchant Token Mint transaction could not be submitted");
                    alert ('Merchant Token Mint transaction could not be submitted, please try again');
                    this.merchFormSubmitted = false;
                }
            })
            .catch(error => {
                console.error("merchant-submit-mint-tx: ", error);
                alert ('Merchant Token Mint transaction could not be submitted, please try again');
                this.merchFormSubmitted = false;
            });
        },
        async addAda() {

        // Connect to the user's wallet
        var walletAPI;
        if (this.walletChoice === "nami") {
            walletAPI = await window.cardano.nami.enable();
        } else if (this.walletChoice === "eternl") {
            walletAPI = await window.cardano.eternl.enable(); 
        } else {
            alert('No wallet selected');
            this.addAdaFormSubmitted = false;
        } 

        // get the UTXOs from wallet,
        const cborUtxos = await walletAPI.getUtxos();

        // Get the change address from the wallet
        const hexChangeAddr = await walletAPI.getChangeAddress();

        await axios.post('/add-ada-tx', {
            adaQty: this.addAdaQty,
            changeAddr: hexChangeAddr,
            utxos: cborUtxos
        })
        .then(async response => {
            console.log("add-ada-tx: ", response);
            const addAdaTx = await JSON.parse(response.data);

            if (addAdaTx.status == 200) {

                console.log("Get wallet signature");
                // Get user to sign the transaction
                const walletSig = await walletAPI.signTx(addAdaTx.cborTx, true);
      
                await axios.post('/add-ada-submit-tx', {
                    cborSig: walletSig,
                    cborTx: addAdaTx.cborTx
                })
                .then(async response => {
                    console.log('add-ada-submit-tx: ', response);
                    const submitTx = await JSON.parse(response.data);
                    if (submitTx.status == 200) {
                        this.addAdaTxId = submitTx.txId;
                        this.addAdaTxIdURL = "https://preprod.cexplorer.io/tx/" + submitTx.txId;
                        this.addAdaSuccess = true;
                    } else {
                        console.error("Could not submit transaction");
                        alert ('Add Ada transaction could not be submitted, please try again');
                        this.addAdaFormSubmitted = false;
                    }
                })
                .catch(error => {
                    console.error("add-ada-submit-tx: ", error);
                    alert ('Add Ada transaction could not be submitted, please try again');
                    this.addAdaFormSubmitted = false;
                });
            } else if (addAdaTx.status == 401) {
                console.error("More Ada in the wallet required for this transaction");
                alert ('More Ada in the wallet required for this transaction"');
                this.addAdaFormSubmitted = false;
            } else {
                console.error("Add Ada transaction was not successful");
                alert ('Add Ada transaction could not be submitted, please try again');
                this.addAdaFormSubmitted = false;
            }
        })
        .catch(error => {
            console.error("add-ada-tx", error);
            alert ('Add Ada transaction could not be submitted, please try again');
            this.addAdaFormSubmitted = false;
        });
        }
    }
}
</script>
