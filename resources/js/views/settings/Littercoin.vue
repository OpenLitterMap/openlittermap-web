<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4"> {{ $t('settings.littercoin.littercoin-header') }}</h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-two-thirds is-offset-1">
                <h1 class="title is-4">Littercoin Smart Contract </h1>

                <p v-if="loading">Loading...</p>

                <div v-else>
                    <div class="mb-2">
                        <strong>Ada Locked at the Smart Contract</strong>
                        <p>{{ this.adaAmount.toLocaleString() }} ada</p>
                    </div>

                    <div class="mb-2">
                        <strong>Total Littercoin In Circulation</strong>
                        <p>{{ this.lcAmount.toLocaleString() }} Littercoin</p>
                    </div>

                    <div class="mb-2">
                        <strong>Ratio:</strong>
                        <p>{{ this.ratio.toLocaleString() }} ada per Littercoin</p>
                        <p>or {{ this.getLittercoinPrice }} per Littercoin</p>
                    </div>

                    <p>Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" >{{ this.lcScriptName }}</a></p>
                    <p>Address: <a style="font-size: small;" :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>
                </div>

                <hr>

                <h1 class="title is-4">My Littercoin</h1>
                <p>Total Littercoin Earned: {{ this.littercoinEarned }}</p>
                <p>Total Littercoin Received: {{ this.littercoinEarned - this.littercoinDue }}</p>
                <p>Littercoin Due: {{ this.littercoinDue }}</p>
                <hr>
                <div>
                    <h1 class="title is-4">Select Your Wallet</h1>
                    <p>
                        <input 
                            type="radio" 
                            v-model="walletChoice" 
                            v-on:change="getWalletInfo"
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
                            v-on:change="getWalletInfo"
                            value="eternl" 
                        />&nbsp;
                        <img src = "/assets/icons/littercoin/eternl.png" alt="Eternl Wallet" style="width:20px;height:20px;"/>
                        <label>&nbsp; Eternl</label>
                    </p>
                    <br>
                    <div v-if="walletChoice">
                        <p v-if="walletLoading">Loading...</p>
                        <div v-else>
                            Ada amount: {{ this.adaBalance.toLocaleString() }} <br>
                            Littercoin amount: {{ this.littercoinBalance.toLocaleString() }} <br>
                            Merchant Token amount: {{ this.merchTokenBalance.toLocaleString() }} <br>
                        </div>
                    </div>
                    <hr>

                    <div v-if="walletChoice">
                        <form 
                            method="post"
                            @submit.prevent="submitForm('mint')" 
                            v-if="!mintSuccess" 
                            >
                            <h1 class="title is-4">Mint Littercoin</h1>
                            Enter the wallet where you want your Littercoin to be sent
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
                    </div>
                    <div v-if="walletChoice">
                        <form 
                            method="post"
                            @submit.prevent="submitForm('burn')" 
                            v-if="!burnSuccess" 
                            >
                            <h1 class="title is-4">Burn Littercoin</h1>
                            Only those holding a Merchant Token can burn Littercoin to received Ada from the Littercoin Smart Contract
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
                            Note: There is a 4.2% (or 1 Ada minimum) service fee included in the burn transaction
                        </form>
                        <div v-if="burnSuccess">
                            <p><h1 class="title is-4">Burn Littercoin Success!!!</h1></p>
                            <p>Please wait approximately 20-60 seconds for the Ada to show up in your wallet.</p>
                            <p>To track this transaction on the blockchain, select the TxId link below.</p>
                            <p>TxId: <a style="font-size: small;" :href="this.burnTxIdURL" target="_blank" rel="noopener noreferrer" >{{ this.burnTxId }}</a></p>
                        </div>
                    <hr>
                    </div>
                    <div v-if="walletChoice">
                        <form 
                            method="post"
                            @submit.prevent="submitForm('merchant')" 
                            v-if="!merchSuccess && isAdmin" 
                        >
                            <p><h1 class="title is-4">Mint Merchant Token</h1></p>
                            Enter the wallet where you want a Merchant Token to be sent
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
                    </div>
                    <div v-if="walletChoice">
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
	</div>
</template>

<script>

export default {
    name: 'Littercoin',
    async created () {
        this.loading = true;

        await axios.get('/get-users-littercoin')
            .then(response => {
                this.littercoinEarned = response.data.littercoinEarned;
                this.littercoinDue = response.data.littercoinDue;
            })
            .catch(error => {
                console.error('littercoin', error);
            });
        await axios.get('/littercoin-info')
            .then(async response => {

                const lcInfo = await JSON.parse(response.data); 
                if (lcInfo.status == 200) {
                    this.adaAmount = lcInfo.payload.list[0].int / 1000000;
                    this.lcAmount = lcInfo.payload.list[1].int;
                    this.ratio = this.adaAmount / this.lcAmount;
                    this.lcAddr = lcInfo.payload.addr;
                    this.lcAddrURL = "https://cexplorer.io/address/" + lcInfo.payload.addr;
                    this.lcScriptName = lcInfo.payload.scriptName;
                    this.lcScriptURL = "/contracts/" + lcInfo.payload.scriptName;
                } else {
                    throw console.error("Could not fetch littercoin contract info");
                }
            })
            .catch(error => {
                console.error('littercoin-info', error);
            });
        await axios.get('https://api.coingecko.com/api/v3/simple/price?ids=cardano&vs_currencies=usd')
            .then(response => {
                console.log('ada-price', response);

                this.adaValues = response.data.cardano;
            })
            .catch(error => {
                console.error('ada-price', error);
            });

        this.loading = false;
    },
    data () {
        return {
            loading :true,
            adaAmount: 0,
            lcAmount: 0,
            ratio: 0,
            adaValues: {},
            selectedCurrency: 'usd',
            currencySymbols: {
                usd: "$",
                eur: "€",
                btc: "₿",
            },
            lcAddr: "",
            lcAddrURL: "",
            lcScriptName: "",
            lcScriptURL: "",
            littercoinEarned: 0,
            littercoinDue: 0,
            walletChoice: "",
            walletLoading: false, 
            adaBalance: 0,
            littercoinBalance: 0,
            merchTokenBalance: 0,
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
         * Shortcut to User object
         */
        user () {
            return this.$store.state.user.user;
        },
        /**
         * Is the user an admin
         */
        isAdmin () {
            return (this.$store.state.user.admin);
        },
         /**
         * Return true to disable the button
         */
	    checkMintDisabled () {
            if (this.mintFormSubmitted) return true

            return false;
        },
        /**
         * Return true to disable the button
         */
	    checkBurnDisabled () {
            if (this.burnFormSubmitted) return true

            return false;
        },
        /**
         * Return true to disable the button
         */
	    checkMerchDisabled () {
            if (this.merchFormSubmitted) return true

            return false;
        },
        /**
         * Return true to disable the button
         */
	    checkAddAdaDisabled () {
            if (this.addAdaFormSubmitted) return true

            return false;
        },
        /**
         * Get the Littercoin price for a given numbercoin
         */
        getLittercoinPrice () {
            const symbol = this.currencySymbols[this.selectedCurrency];
            const price = this.adaValues[this.selectedCurrency];

            return (this.lcAmount === 0)
                ? 0
                : symbol.toString()+ (this.ratio * price).toFixed(2).toString();
        }
    },
    methods: {

        async getWalletInfo () {

            this.walletLoading = true;
            try {
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

                // Get balance from wallet
                const balanceCbor = await walletAPI.getBalance();

                console.log({ balanceCbor });
                
                // Get the UTXOs from wallet,
                const cborUtxos = await walletAPI.getUtxos();

                console.log({ cborUtxos });

                await axios.post('/wallet-info', {
                    balanceCborHex: balanceCbor,
                    utxos: cborUtxos
                })
                .then(async response => {
                    const walletInfo = await JSON.parse(response.data);
                    this.adaBalance = walletInfo.payload.adaAmt;
                    this.littercoinBalance = walletInfo.payload.lcAmt;
                    this.merchTokenBalance = walletInfo.payload.mtAmt;
                    this.walletLoading = false;
                })
                .catch(error => {
                    console.error("Error accessing user wallet", error.response.data.errors);
                    alert ('Error accessing user wallet');
                });

            } catch (err) {
                console.error(err);
            }
        },
        /**
         * Submit a transaction to the cardano blockchain network
         */
         submitForm: function (type) {
            
            if (!this.walletChoice) {
                alert ('Please select a wallet');
                return;
            }
            if (this.adaBalance < 5) {
                alert ('Not enough Ada in the wallet for a transaction, please make sure there is 5 or more Ada in your wallet');
                return;
            }
            if (type === 'mint') {
                if (!this.mintDestAddr.match(/^addr/)) {
                    alert ('Please enter a valid mint littercoin destination address');
                    return
                }
                if (!this.littercoinDue > 0) {
                    alert ('There are no littercoin due for minting');
                    return
                }
                this.mintFormSubmitted = true;
                this.submitMint();
            }
            if (type === 'burn') {
                if (this.lcQty < 1) {
                    alert ('Minimum 1 littercoin required for burn');
                    return
                }
                if (this.lcQty > this.littercoinBalance) {
                    alert ('The amount of littercoin to burn exceeds the amount of littercoin in the wallet');
                    return
                }
                if (this.merchTokenBalance < 1) {
                    alert ('No Merchant Tokens founds in the wallet');
                    return;
                }
                this.burnFormSubmitted = true;
                this.submitBurn();
            }
            if (type === 'merchant') {
                if (!this.merchDestAddr.match(/^addr/)) {
                    alert ('Please enter a valid mint merchant token destination address');
                    return
                }
                this.merchFormSubmitted = true;
                this.merchMint();
            }
            if (type === 'addAda') {
                if (!this.addAdaQty > 2) {
                    alert ('Minimum 2 Ada donation amount required');
                    return
                }
                this.addAdaFormSubmitted = true;
                this.addAda();
            }
        },
        async submitMint() {
            try {

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
                
                    const mintTx = await JSON.parse(response.data);
                    if (mintTx.status == 200) {

                        // Get user to sign the transaction
                        console.log("Get wallet signature");

                        var walletSig;
                        try {
                            walletSig = await walletAPI.signTx(mintTx.cborTx, true);
                        } catch (err) {
                            console.error(err);
                            this.mintFormSubmitted = false;
                            return
                        }

                        console.log("Submit transaction...");
                        await axios.post('/littercoin-submit-mint-tx', {
                            cborSig: walletSig,
                            cborTx: mintTx.cborTx
                        })
                        .then(async response => {
                            
                            const submitTx = await JSON.parse(response.data);
                            if (submitTx.status == 200) {
                                this.mintTxId = submitTx.txId;
                                this.mintTxIdURL = "https://cexplorer.io/tx/" + submitTx.txId;
                                this.mintSuccess = true;
                            } else {
                                console.error("Littercoin Mint transaction could not be submitted");
                                alert ('Littercoin Mint transaction could not be submitted, please try again');
                                this.mintFormSubmitted = false;
                            }
                        })
                        .catch(error => {

                            if (error.response.status == 422){
                                console.error("Invalid Wallet Input", error.response.data.errors);
                            } else {
                                console.error("littercoin-submit-mint-tx: ", error);
                            }
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
                    
                    if (error.response.status == 422){
                        console.error("Invalid User Input", error.response.data.errors);
                        alert ('Please check that you have entered a valid destination address');
                    } else {
                        console.error("littercoin-mint-tx", error);
                        alert ('Littercoin Mint transaction could not be submitted, please try again');
                    }
                    this.mintFormSubmitted = false;
                });
            } catch (err) {
                console.error(err);
                this.mintFormSubmitted = false;
            }
        },
        async submitBurn() {

            try {

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
                    
                    const burnTx = await JSON.parse(response.data);
                    if (burnTx.status == 200) {

                        // Get user to sign the transaction
                        console.log("Get wallet signature");
                        var walletSig;
                        try {
                            walletSig = await walletAPI.signTx(burnTx.cborTx, true);
                        } catch (err) {
                            console.error(err);
                            this.burnFormSubmitted = false;
                            return
                        }
                        
                        console.log("Submit transaction...");
                        await axios.post('/littercoin-submit-burn-tx', {
                            cborSig: walletSig,
                            cborTx: burnTx.cborTx
                        })
                        .then(async response => {
                    
                            const submitTx = await JSON.parse(response.data);
                            if (submitTx.status == 200) {
                                this.burnTxId = submitTx.txId;
                                this.burnTxIdURL = "https://cexplorer.io/tx/" + submitTx.txId;
                                this.burnSuccess = true;
                            } else {
                                console.error("Littercoin Burn transaction was not successful");
                                alert ('Littercoin Burn transaction could not be submitted, please try again');
                                this.burnFormSubmitted = false;
                            }
                        })
                        .catch(error => {
                            if (error.response.status == 422){
                                console.error("Invalid Wallet Input", error.response.data.errors);
                            } else {
                                console.error("littercoin-submit-burn-tx: ", error);
                            }
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
                    } else if (burnTx.status == 405) {
                        console.error("No valid merchant token found in the wallet");
                        alert ('No valid merchant token found in the wallet');
                        this.burnFormSubmitted = false;
                    } else {
                        console.error("Littercoin Burn transaction was not successful");
                        alert ('Littercoin Burn transaction could not be submitted, please try again');
                        this.burnFormSubmitted = false;
                    }
                })
                .catch(error => {
                    if (error.response.status == 422){
                        console.error("Invalid User Input", error.response.data.errors);
                        alert ('Please check that you have entered a valid destination address');
                    } else {
                        console.error("littercoin-burn-tx", error);
                        alert ('Littercoin Burn transaction could not be submitted, please try again');
                    }
                    this.burnFormSubmitted = false;
                });
            } catch (err) {
                console.error(err);
                this.burnFormSubmitted = false;
            }
        },
        async merchMint() {

            try {

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
                    const mintTx = await JSON.parse(response.data);

                    if (mintTx.status == 200) {

                        // Get user to sign the transaction
                        console.log("Get wallet signature");
                        var walletSig;
                        try {
                            walletSig = await walletAPI.signTx(mintTx.cborTx, true);
                        } catch (err) {
                            console.error(err);
                            this.merchFormSubmitted = false;
                            return
                        }

                        console.log("Submit transaction...");
                        await axios.post('/merchant-submit-mint-tx', {
                            cborSig: walletSig,
                            cborTx: mintTx.cborTx
                        })
                        .then(async response => {
                    
                            const submitTx = await JSON.parse(response.data);
                            if (submitTx.status == 200) {
                                this.merchTxId = submitTx.txId;
                                this.merchTxIdURL = "https://cexplorer.io/tx/" + submitTx.txId;
                                this.merchSuccess = true;
                            } else {
                                console.error("Merchant Token Mint transaction could not be submitted");
                                alert ('Merchant Token Mint transaction could not be submitted, please try again');
                                this.merchFormSubmitted = false;
                            }
                        })
                        .catch(error => {
                            if (error.response.status == 422){
                                console.error("Invalid Wallet Input", error.response.data.errors);
                            } else {
                                console.error("merchant-submit-mint-tx: ", error);
                            }
                            alert ('Merchant Token Mint transaction could not be submitted, please try again');
                            this.merchFormSubmitted = false;
                        });

                    } else if (mintTx.status == 407) {
                        console.error("Must be an admin user to mint a merchant token");
                        alert ('Must be an admin user to mint a merchant token');
                        this.merchFormSubmitted = false;
                    } else {
                        console.error("Merchant Token Mint transaction could not be submitted");
                        alert ('Merchant Token Mint transaction could not be submitted, please try again');
                        this.merchFormSubmitted = false;
                    }
                })
                .catch(error => {
                    if (error.response.status == 422){
                        console.error("Invalid User Input", error.response.data.errors);
                        alert ('Please check that you have entered a valid destination address');
                    } else {
                        console.error("merchant-submit-mint-tx: ", error);
                        alert ('Merchant Token Mint transaction could not be submitted, please try again');
                    }
                    this.merchFormSubmitted = false;
                });
            } catch (err) {
                console.error(err);
                this.merchFormSubmitted = false;
            }
        },
        async addAda() {

            try {

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
                    
                    const addAdaTx = await JSON.parse(response.data);

                    if (addAdaTx.status == 200) {

                        // Get user to sign the transaction
                        console.log("Get wallet signature");
                        var walletSig;
                        try {
                            walletSig = await walletAPI.signTx(addAdaTx.cborTx, true);
                        } catch (err) {
                            console.error(err);
                            this.addAdaFormSubmitted = false;
                            return
                        }
            
                        await axios.post('/add-ada-submit-tx', {
                            cborSig: walletSig,
                            cborTx: addAdaTx.cborTx
                        })
                        .then(async response => {

                            const submitTx = await JSON.parse(response.data);
                            if (submitTx.status == 200) {
                                this.addAdaTxId = submitTx.txId;
                                this.addAdaTxIdURL = "https://cexplorer.io/tx/" + submitTx.txId;
                                this.addAdaSuccess = true;
                            } else {
                                console.error("Could not submit transaction");
                                alert ('Add Ada transaction could not be submitted, please try again');
                                this.addAdaFormSubmitted = false;
                            }
                        })
                        .catch(error => {
                            if (error.response.status == 422){
                                console.error("Invalid Wallet Input", error.response.data.errors);
                            } else {
                                console.error("add-ada-submit-tx: ", error);
                            }
                            alert ('Add Ada transaction could not be submitted, please try again');
                            this.addAdaFormSubmitted = false;
                        });
                    } else if (addAdaTx.status == 408) {
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
                    if (error.response.status == 422){
                        console.error("Invalid User Input", error.response.data.errors);
                        alert ('Please check that you have entered a valid destination address');
                    } else {
                        console.error("add-ada-tx", error);
                        alert ('Add Ada transaction could not be submitted, please try again');
                    }
                    this.addAdaFormSubmitted = false;
                });
            } catch (err) {
                console.error(err);
                this.addAdaFormSubmitted = false;
            }
        }
    }
}
</script>
