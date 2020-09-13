<template>
	<div style="padding-left: 1em; padding-right: 1em;">
			<h1 class="title is-4">Littercoin (LTRX)</h1>
			<hr>
			<br>
			<div class="columns">

				<div class="column is-two-thirds is-offset-1">

                    <p v-if="true">This will be back later</p>

                    <div v-else>

                        <div v-if="!this.web3exists">
                            <p>If you want to just claim your tokens and access your wallet from elsewhere, enter your wallet ID and you will be sent your earnings.</p>
                            <div class="columns">
                                <div class="column is-half">
                                    <br>
                                    <div v-if="this.user.eth_wallet">
                                        <p>Your Wallet ID:</p>
                                        <p>{{ this.user.eth_wallet }}</p>
                                        <br>
                                        <button class="button is-medium is-danger" @click="deleteWallet">Delete wallet ID</button>
                                    </div>
                                    <div v-else>
                                        <form method="POST" action="/settings/littercoin/" role="form" @submit.prevent="addWallet">
                                            <input id="user-wallet-id" name="user-wallet-id" type="text" class="input" placeholder="Your Wallet ID" v-model="userwallet" />
                                            <br>
                                            <br>
                                            <button class="button is-medium is-primary">Submit ID</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <p>To see your Littercoin balance and send Littercoin from this page you will need to download Chrome and install <a href="https://metamask.io/">MetaMask</a>. Unfortuantely there is no mobile client available yet. When you install MetaMask these instructions will disappear.</p>
                            <br>
                            <ol>
                                <li>To create a new wallet visit <a href="https://myetherwallet.com">MyEtherWallet</a> and <a href="https://myetherwallet.github.io/knowledge-base/private-keys-passwords/difference-beween-private-key-and-keystore-file.html">export your Keystore UTC File</a>, which is an encrypted version of your password. If you are using Mist or some other wallet, export the same file (Accounts -> Backup -> Accounts). This file should be available on Unix systems at ~/User/Library/Ethereum/keystore/ as a 'UTC....' file.</li>
                                <li>Open Metamask and import this file as a json file.</li>
                                <li>Upload 7-days in a row, be the first to upload from a Country, State or City and earn Littercoin! More options coming soon!</li>
                            </ol>
                            <br>

                        </div>
                        <div v-else>
                            <div v-if="this.user.eth_wallet">
                                <p>My Wallet {{ this.user.eth_wallet }}</p>
                                <br>
                                <h1 class="title is-3">My Ethereum: <span id="mybal"></span></h1>
                                <h1 class="title is-3">My Littercoin: <span id="myLtrx"></span></h1>
                                <br>
                                <p>Do you want to send LTRX to another address? Note: This will cost gas (Eth).</p>
                                <p>Please ensure you enter the correct wallet ID. We cannot be held responsible if you enter an incorrect ID!</p>
                                <br>
                                <div class="input-group">
                                    <span class="input-group-addon" id="sizing-addon2">
                                        <span><strong>SEND</strong></span>
                                    </span>
                                    <input type="text" class="input" placeholder="Insert wallet ID here to send LTRX" size="50" v-model="inputltrx">
                                </div>
                                <div class="input-group">
                                    <span class="input-group-addon" id="sizing-addon2">
                                        <span><strong>LTRX</strong></span>
                                    </span>
                                    <input type="text" class="input" placeholder="Insert amount of LTRX to send eg 1.0000" size="50" v-model="amountltrx">
                                </div>
                                <br>
                                <button id="sendcoinbutton" class="button is-medium is-success" @click="sendltrx">Send LTRX</button>
                                <button class="button is-medium is-danger" @click="deleteWallet">Delete wallet ID</button>
                            </div>
                            <div v-else>
                                <div class="column one-third is-offset-1" style="padding-left: 2em; padding-right: 2em;">
                                    <p><b>Step 1: </b>Add the public Littercoin ID to the "Watch Token" section of your Ethereum Wallet:</p>
                                    <p style="color: grey;">0xDA99A3329362220d7305e4C7071F7165abC34181</p>
                                    <br>
                                        <form method="POST" action="/settings/littercoin/" role="form" @submit.prevent="addWallet">
                                            <p><b>Step 2: </b>Enter your Ethereum Wallet ID here so we know where to send and read your available Littercoin:</p>
                                            <br>
                                                <div class="columns">
                                                    <div class="column is-half">
                                                        <input id="user-wallet-id" name="user-wallet-id" type="text" class="input" placeholder="Your Wallet ID" v-model="userwallet" />
                                                    </div>
                                                </div>
                                            <button class="button is-medium is-primary">Submit ID</button>
                                        </form>

                                        <div v-show="this.userwallet.length > 40">
                                            <p>Your wallet ID is {{ this.userwallet }}</p>
                                        </div>
                                    <!-- <h1 class="title is-2">My Littercoins: </h1> -->
                                    <!-- <span id="myBalance"></span> -->
                                    <!-- <button class="button is-medium is-primary">Recieve</button> -->
                                    <!-- <button class="button is-medium is-danger">Send</button> -->
                                    <!-- <button class="button is-medium is-danger">Get My Balance</button> -->
                                </div>
                            </div>
                        </div>
				</div>
			</div>
        </div>
	</div>
</template>

<script>
	// <!-- <p>My personal wallet - remove</p> -->
	// <!-- <p>0x770ea08D3C609e0E37FFdf443fD3842E426e7Eb0</p> -->

	// if (typeof web3 !== 'undefined') {
	// 	this.web3exists = true;
	// 	// console.log('does web3 exist');
	// 	// console.log(this.web3exists);
  	// 	web3 = new Web3(web3.currentProvider);
  	// 	// console.log(web3);
    //
	// 	var contract_address = "0xDA99A3329362220d7305e4C7071F7165abC34181";
	// 	var contract_abi = [ { "constant": false, "inputs": [ { "name": "newSellPrice", "type": "uint256" }, { "name": "newBuyPrice", "type": "uint256" } ], "name": "setPrices", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "name", "outputs": [ { "name": "", "type": "string", "value": "Littercoin" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_spender", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "approve", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "totalSupply", "outputs": [ { "name": "", "type": "uint256", "value": "100000020000" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_from", "type": "address" }, { "name": "_to", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "transferFrom", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "decimals", "outputs": [ { "name": "", "type": "uint8", "value": "4" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "sellPrice", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "standard", "outputs": [ { "name": "", "type": "string", "value": "Littercoin" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" } ], "name": "balanceOf", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "target", "type": "address" }, { "name": "mintedAmount", "type": "uint256" } ], "name": "mintToken", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "buyPrice", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "owner", "outputs": [ { "name": "", "type": "address", "value": "0x770ea08d3c609e0e37ffdf443fd3842e426e7eb0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "symbol", "outputs": [ { "name": "", "type": "string", "value": "LTRX" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [], "name": "buy", "outputs": [], "payable": true, "type": "function" }, { "constant": false, "inputs": [ { "name": "_to", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "transfer", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" } ], "name": "frozenAccount", "outputs": [ { "name": "", "type": "bool", "value": false } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_spender", "type": "address" }, { "name": "_value", "type": "uint256" }, { "name": "_extraData", "type": "bytes" } ], "name": "approveAndCall", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" }, { "name": "", "type": "address" } ], "name": "allowance", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "amount", "type": "uint256" } ], "name": "sell", "outputs": [], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "target", "type": "address" }, { "name": "freeze", "type": "bool" } ], "name": "freezeAccount", "outputs": [], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "newOwner", "type": "address" } ], "name": "transferOwnership", "outputs": [], "payable": false, "type": "function" }, { "inputs": [ { "name": "initialSupply", "type": "uint256", "index": 0, "typeShort": "uint", "bits": "256", "displayName": "initial Supply", "template": "elements_input_uint", "value": "100000000000" }, { "name": "tokenName", "type": "string", "index": 1, "typeShort": "string", "bits": "", "displayName": "token Name", "template": "elements_input_string", "value": "Littercoin" }, { "name": "decimalUnits", "type": "uint8", "index": 2, "typeShort": "uint", "bits": "8", "displayName": "decimal Units", "template": "elements_input_uint", "value": "4" }, { "name": "tokenSymbol", "type": "string", "index": 3, "typeShort": "string", "bits": "", "displayName": "token Symbol", "template": "elements_input_string", "value": "LTRX" } ], "payable": false, "type": "constructor" }, { "payable": false, "type": "fallback" }, { "anonymous": false, "inputs": [ { "indexed": false, "name": "target", "type": "address" }, { "indexed": false, "name": "frozen", "type": "bool" } ], "name": "FrozenFunds", "type": "event" }, { "anonymous": false, "inputs": [ { "indexed": true, "name": "from", "type": "address" }, { "indexed": true, "name": "to", "type": "address" }, { "indexed": false, "name": "value", "type": "uint256" } ], "name": "Transfer", "type": "event" } ];
    //
	// 	var contract_instance = web3.eth.contract(contract_abi).at(contract_address);
    //
	// 	// console.log(contract_instance);
    //
	// 	// var version = web3.version.network;
	// 	// console.log(version); // 54
    //
	// 	var accounts = web3.eth.accounts;
	// 	// console.log(accounts);
    //
	// } else {
	//   		// set the provider you want from Web3.providers
	//   		// alert("Sorry, the web3js object is not available right now. Please configure MetaMask and try again.");
	//   		// web3 = new Web3(new Web3.providers.HttpProvider("http://localhost:8545"));
	// }

export default {
    name: 'Littercoin',
    created ()
    {
        // if (typeof web3 !== 'undefined') this.web3exists = true;
        //
        // if (this.user.eth_wallet.length > 40)
        // {
        //     if (contract_instance !== undefined)
        //     {
        //         contract_instance.balanceOf(this.user.eth_wallet, function(err, res) {
        //             if(err) {
        //                 // console.error(err);
        //             } else {
        //                 // console.log('success');
        //                 // console.log(res['c'][0]);
        //                 var littercoin = res['c'][0] / 10000;
        //                 var ltrxCoin = littercoin.toLocaleString(undefined, { maximumFractionDigits: 4 });
        //                 document.getElementById('myLtrx').innerText = ltrxCoin;
        //             }
        //         });
        //         // console.log(web3);
        //         web3.eth.getBalance(this.user.eth_wallet, web3.eth.defaultBlock, function(error, result) {
        //             if(error) {
        //                 console.error(error);
        //             } else {
        //                 var balance = web3.fromWei(result.toNumber());
        //                 // console.log(balance);
        //                 // console.log(typeof(balance));
        //                 document.getElementById('mybal').innerText = balance;
        //                 // this.$data.myBal = balance;
        //             }
        //         })
        //     }
        // }
    },
    data ()
    {
        return {
            userwallet: '',
            myBal: '',
            inputltrx: '',
            web3exists: false,
            amountltrx: '0.0000'
        };
    },
    methods: {

        /**
         *
         */
        addWallet ()
        {
            // Validate input
            if (this.userwallet.length < 40) {
                return alert('Sorry, that doesnt look like a valid wallet ID. Please try again');
            }

            axios({
                method: 'post',
                url: '/en/settings/littercoin/update',
                data: { wallet: this.userwallet }
            })
            .then(response => {
                alert('You have submitted a wallet id');
                window.location.href = window.location.href
             })
             .catch(error => {
                // console.log(error);
                alert('Error! Please try again');
             });
        },

        /**
         *
         */
        sendltrx ()
        {
            if (this.inputltrx.length < 10) {
                alert('Please enter a valid wallet id. If you are unable to please contact @ info@openlittermap.com');
            }

            else
            {
                contract_instance.transfer(this.inputltrx, this.amountltrx, function(error, result) {
                    if(error) {
                        alert(error);
                    } else {
                        // console.log(result);
                        alert('Success! Your transaction # is :' + result);
                    }
                });
            }
        },

        /**
         *
         */
        deleteWallet ()
        {
            axios({
                method: 'post',
                url: '/en/settings/littercoin/removewallet',
                data: { wallet: this.userwallet }
            })
            .then(response => {
                alert('Your wallet ID has been deleted.');
                window.location.href = window.location.href
             })
             .catch(error => {
                // console.log(error);
                alert('Error! Please try again');
             });
        }

    },

    watch: {
        inputltrx() {
            if(this.inputltrx.length > 10) {
                // console.log('over 10');
                document.getElementById('sendcoinbutton').disabled = false;
            }
            if(this.inputltrx.length < 10) {
                // console.log('less than 10');
                document.getElementById('sendcoinbutton').disabled = true;
            }
        }
    }
}
</script>
