<template>
	<button class="button is-large is-dark" @click="generateOneLtrx">
		Generate Littercoin!
	</button>
</template>

<script>

	if (typeof web3 !== 'undefined') {
  		web3 = new Web3(web3.currentProvider);
  		console.log(web3);


		var contract_address = "0xDA99A3329362220d7305e4C7071F7165abC34181";
		var contract_abi = [ { "constant": false, "inputs": [ { "name": "newSellPrice", "type": "uint256" }, { "name": "newBuyPrice", "type": "uint256" } ], "name": "setPrices", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "name", "outputs": [ { "name": "", "type": "string", "value": "Littercoin" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_spender", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "approve", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "totalSupply", "outputs": [ { "name": "", "type": "uint256", "value": "100000020000" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_from", "type": "address" }, { "name": "_to", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "transferFrom", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "decimals", "outputs": [ { "name": "", "type": "uint8", "value": "4" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "sellPrice", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "standard", "outputs": [ { "name": "", "type": "string", "value": "Littercoin" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" } ], "name": "balanceOf", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "target", "type": "address" }, { "name": "mintedAmount", "type": "uint256" } ], "name": "mintToken", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "buyPrice", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "owner", "outputs": [ { "name": "", "type": "address", "value": "0x770ea08d3c609e0e37ffdf443fd3842e426e7eb0" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [], "name": "symbol", "outputs": [ { "name": "", "type": "string", "value": "LTRX" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [], "name": "buy", "outputs": [], "payable": true, "type": "function" }, { "constant": false, "inputs": [ { "name": "_to", "type": "address" }, { "name": "_value", "type": "uint256" } ], "name": "transfer", "outputs": [], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" } ], "name": "frozenAccount", "outputs": [ { "name": "", "type": "bool", "value": false } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "_spender", "type": "address" }, { "name": "_value", "type": "uint256" }, { "name": "_extraData", "type": "bytes" } ], "name": "approveAndCall", "outputs": [ { "name": "success", "type": "bool" } ], "payable": false, "type": "function" }, { "constant": true, "inputs": [ { "name": "", "type": "address" }, { "name": "", "type": "address" } ], "name": "allowance", "outputs": [ { "name": "", "type": "uint256", "value": "0" } ], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "amount", "type": "uint256" } ], "name": "sell", "outputs": [], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "target", "type": "address" }, { "name": "freeze", "type": "bool" } ], "name": "freezeAccount", "outputs": [], "payable": false, "type": "function" }, { "constant": false, "inputs": [ { "name": "newOwner", "type": "address" } ], "name": "transferOwnership", "outputs": [], "payable": false, "type": "function" }, { "inputs": [ { "name": "initialSupply", "type": "uint256", "index": 0, "typeShort": "uint", "bits": "256", "displayName": "initial Supply", "template": "elements_input_uint", "value": "100000000000" }, { "name": "tokenName", "type": "string", "index": 1, "typeShort": "string", "bits": "", "displayName": "token Name", "template": "elements_input_string", "value": "Littercoin" }, { "name": "decimalUnits", "type": "uint8", "index": 2, "typeShort": "uint", "bits": "8", "displayName": "decimal Units", "template": "elements_input_uint", "value": "4" }, { "name": "tokenSymbol", "type": "string", "index": 3, "typeShort": "string", "bits": "", "displayName": "token Symbol", "template": "elements_input_string", "value": "LTRX" } ], "payable": false, "type": "constructor" }, { "payable": false, "type": "fallback" }, { "anonymous": false, "inputs": [ { "indexed": false, "name": "target", "type": "address" }, { "indexed": false, "name": "frozen", "type": "bool" } ], "name": "FrozenFunds", "type": "event" }, { "anonymous": false, "inputs": [ { "indexed": true, "name": "from", "type": "address" }, { "indexed": true, "name": "to", "type": "address" }, { "indexed": false, "name": "value", "type": "uint256" } ], "name": "Transfer", "type": "event" } ];

		var contract_instance = web3.eth.contract(contract_abi).at(contract_address);
		console.log('this is my new contract instance');
		console.log(contract_instance);
	} else {
  		// set the provider you want from Web3.providers
  		// alert("Sorry, the web3js object is not available right now. Please configure MetaMask and try again.");
  		// web3 = new Web3(new Web3.providers.HttpProvider("http://localhost:8545"));
	}

	export default {

		props: ['eth_wallet'],

		created() {
			console.log('created!');
			console.log(this.eth_wallet);
		},

		methods: {
			generateOneLtrx() {
				var photo = document.getElementById('photonumber').getAttribute('data-int');

				contract_instance.mintToken(this.eth_wallet, 10000, function(error, result) {
					if(error) {
						console.error(error);
					} else {
						console.log(result);
						// reduce allowance 
			            axios({
			                method: 'post', 
			                url: '/admin/ltrxgenerated', 
			                data: { photoId: photo }
			            })
			            .then(response => { 
							alert('Ltrx transaction approved!');
			                // window.location.href = window.location.href
			            })
			            .catch(error => {
			                console.error(error);
			                alert('Error! Please try again');
			            });
					}
				});
			}
		}
	}
</script>