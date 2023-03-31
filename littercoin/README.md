# Littercoin Open Source Repository
#### Table Of Contents
- [Problem Statement](#problem-statement)
- [The Solution](#the-solution)
- [Funding](#funding)
- [The Application](#the-application)
  - [User Journey](#user-journey)
  - [High Level Design](#high-level-design)
  - [Application Architecture](#application-architecture)
  - [Littercoin Security Model](#littercoin-security-model)
  - [Adding Ada](#adding-ada)
  - [Minting Littercoin](#minting-littercoin)
  - [Minting Merchant Token](#minting-merchant-token)
  - [Burning Littercoin](#burning-littercoin)
- [Why Helios](#why-helios)
  - [Baseline Comparison](#baseline-comparison)
- [Getting Started](#getting-started)
  - [Owner PKH and root key](#owner-pkh-and-root-key)
  - [Demeter Run Setup](#demeter-run-setup)
- [Initialize The Smart Contract](#initialize-the-smart-contract)
  - [Create The Admin Keys And Address](#create-the-admin-keys-and-address)
  - [Send Ada To The Admin Address](#send-ada-to-the-admin-address)
  - [Determine The Admin UTXO](#determine-the-admin-utxo)
  - [Compile Smart Contract Code and Deploy](#compile-smart-contract-code-and-deploy)
  - [Threadtoken and Littercoin Initialization](#threadtoken-and-littercoin-initialization)
 - [Support/Issues/Community](#supportissuescommunity)   

## Problem Statement
Litter and plastic pollution are global problems. Crowdsourcing data can help fix this, but data collection tools, visualizations and incentives remain significantly underdeveloped

## The Solution
Littercoin is the first token rewarded for the production of geographic information. This means that real people, collecting real data, about real companies, who are polluting a real environment get rewarded with a new type of currency for their data collection work.
However, this currency can only be spent with pre-approved "green listed" merchants in the climate economy.
The type of data that you want to collect is entirely up to you. Most litter pickers photograph every object individually, as this tells a powerful story that is visualised and communicated through our interactive tools.
Our tools enable anyone to identify, visualise, and communicate local observations that are important to them. This data tells an important story about the individuals positive environmental impact and the data we produce is 
open source, available for anyone to download and use. Sometimes, objects may be too big or heavy to remove by a single person, or a person might not be equipped enough or  have the time to deal with something dangerous like broken glass.
With OpenLitterMap, you are empowered to use your device to collect data on whatever it is that you want. This includes if the litter has been removed, if its still there, if the bins are overflowing, if there is dumping, what brands are responsible... and a lot more.
No matter if you are a dedicated litter picker or someone who just wants to contribute a single photo, there is something here for everyone. 
By using your technology to produce real, objective, non-human information about the world, you are passively mining Littercoin in the background which can be spent with pre-approved
"green listed" merchants only. Most people spend their entire lives trying to accumulate an unlimited type of money, that has no open source code, which can be printed and devalued in an instant.
We are reinventing the production of value creation and transfer geospatially, by giving real people a climate-only reward for making real-world geospatial observations.
To give every Littercoin value, anyone can send `ada` to the Smart Contract, however only pre-approved climate merchants that hold a special Merchant Token can send Littercoin to the Smart Contract and get the ada out.

## Funding
The development of OpenLitterMap and Littercoin was supported by Project Catalyst, the world's first decentralised innovation fund run on the Cardano blockchain network.
Over 285 million ada holders, representing about $500 million USD in value, voted for our proposal `Littercoin: Mass Adoption` in Fund 4 with $50,000 worth of ada tokens.
[Read more](https://projectcatalyst.io/funds/4/f4-dapps-and-integrations/littercoin-mass-adoption-fb39e)

## The Application
#### User Journey
The following diagram depicts the typical user journey for littercoin.

![Littercoin User Journey](https://user-images.githubusercontent.com/7105016/227805539-14e24f07-2cff-4766-b48a-d6386d130a4b.png)


#### High Level Design
The following high level design was used to create a model of the sequence of transactions, the datum state data and the inputs and outputs.

![Littercoin High Level Design](https://user-images.githubusercontent.com/7105016/226448800-ef407f21-c51a-41a7-bf79-9226b4774d4e.png)

#### Application Architecture
The application architecture below shows the steps involved to mint a littercoin. These steps are simliar for the other types of tranasction (eg. Burn, Add Ada & Mint Merchant Token).
![Application Architecture](https://user-images.githubusercontent.com/7105016/227805634-32403c51-2206-40bf-9459-dd946fb36f4a.png)


### Littercoin Security Model
The security model for littercoin involves both the application layer security policies and the smart contract security policies.  By design, the security policies that may change over time have been defined within the application security layer whereas the security policies that are fixed are defined within the smart contract.   Here is a diagram depicting the high level security policies.
![Littercoin Security Model](https://user-images.githubusercontent.com/7105016/227960221-5a723417-9645-44a0-8ca1-7876d92c3154.png)




##### Adding Ada
Any user with a Nami or Eternl wallet can go the web application and add Ada to the smart contract. They will receive Littercoin Donation Rewards for every Ada sent to the smart contract.
##### Minting Littercoin
Only the user (after they have logged into the application) will be able to mint the amount of littercoins that they are due. The user will enter the address where to send the littercoin and mint them.   The littercoin application will check and confirm that the user is actually eligible, and will sign the transaction accordingly.
##### Minting Merchant Token
Only an admin who is logged into the littercoin application can mint merchant tokens.
##### Burning Littercoin
Only a wallet with a merchant token is able to burn littercoin and receive Ada.  The Merchant enters the amount of littercoin they have in their wallet that they will burn.  The Smart Contract will then burn the littercoin and send them the amount of Ada corresponding to the current Ada:Littercoin price ratio in the smart contract.

## Why Helios
Helios is a fantastic alternative language for writing plutus smart contracts.   No nix, no cabal and no haskell yet a strongly typed, functional programming language!   The excellent documentation and well designed language and syntax is very intuitive and easy to learn.  Find out more info here: [https://github.com/Hyperion-BT/Helios](https://github.com/Hyperion-BT/Helios)
#### Baseline Comparison
Since I initially wrote the littercoin smart contract using Haskell Plutus Tx, I was able to compare a baseline, so here are the results.

![image](https://user-images.githubusercontent.com/7105016/209332105-bd872821-3120-46a7-89d0-d97aa0210f56.png)

Baseline results for Littercoin validator script:

Helios Plutus V2 baseline testing log: https://github.com/lley154/littercoin/blob/preprod-5.0/testing/baseline.log

Haskell Plutus V2 baseline testing log: https://github.com/lley154/littercoin/blob/baseline/testing/baseline.log


## Getting Started
To setup your own incentivized token economy, you can follow the steps below and use this as a template.

A technical deep dive demo video is located here [Littercoin Smart Contract V2 Demo](https://youtu.be/Ikfx4etGbVg)

## Owner PKH and root key 
Generate the owner pkh and root key in the openLitterMap repo
```
1. cd littercoin/init
2. export ENTROPY="enter you seed phrase here"
3. node ./generate-private-key.mjs
4. cd ..
5. Update .env with root key and pkh generated above
6. Update .env with blockfrost api & url
7. Update .env with network params file
8. composer dump-autoload
9. exit
```

## Initialize The Smart Contract
To initialize the littercoin smart contract, we will need to identify a UTXO that the cardano-cli tx builder will consume.  This is used only once by running the init-tx.sh bash shell script.   We also need to setup a public & private key that will be used to pay for the initialization transaction executed by cardano-cli.  


#### Demeter Run Setup
Demeter Run is a fully hosted provider that creates workspaces where you can interact with the cardano node and build and launch web3 applications.  You will need to create a workspace (for free) for the steps below.
1. Go to https://demeter.run/ 
2. Sign in and create a New Project
3. Create or use an existing Organization
4. Select a cluster (US or Europe)
5. Select a plan Discover (free)
6. Select the Preprod Network
7. Enter a project name
8. Select Create Project
9. Select Open Project to go to the project console
10. On the Dashboard tab, select Setup a Dev Workspace
11. Make sure clone an existing repository is on
12. Use the following github URL: https://github.com/lley154/littercoin.git
13. Select your coding stack as Typescript
14. Select the small workspace size
15. Scroll to the bottom of the page and select "Create Workspace"
16. Wait for the workspace to be created


##### Create The Admin Keys And Address

1. Go to your Web VS Code in your browser
2. Select the hamburger menu (top left) and Terminal -> New Terminal
3. mkdir ~/.local/keys
2. cd ~/workspace
3. wget https://github.com/input-output-hk/cardano-wallet/releases/download/v2022-12-14/cardano-wallet-v2022-12-14-linux64.tar.gz
4. tar -xvzf cardano-wallet-v2022-12-14-linux64.tar.gz
5. cd cardano-wallet-v2022-12-14-linux64
7. ./cardano-address recovery-phrase generate --size 24 > ~/.local/keys/key.prv
8. ./cardano-address key from-recovery-phrase Shelley < ~/.local/keys/key.prv > ~/.local/keys/key.xprv
9. ./cardano-address key child 1852H/1815H/0H/0/0 < ~/.local/keys/key.xprv > ~/.local/keys/key.xsk
10. ./cardano-cli key convert-cardano-address-key --shelley-payment-key --signing-key-file ~/.local/keys/key.xsk --out-file ~/.local/keys/key.skey
11. ./cardano-cli key verification-key --signing-key-file ~/.local/keys/key.skey --verification-key-file ~/.local/keys/key.vkey
12. ./cardano-cli address key-hash --payment-verification-key-file ~/.local/keys/key.vkey --out-file ~/.local/keys/key.pkh
13. ./cardano-cli address build --payment-verification-key-file ~/.local/keys/key.vkey --out-file ~/.local/keys/key.addr --testnet-magic 1
14. more ~/.local/keys/key.addr 

You will see something similar to the following:
```
abc@hallowed-birthday-3qoq5k-0:~/workspace/cardano-wallet-v2022-12-14-linux64$ more ~/.local/keys/key.addr
addr_test1v83ynr979e4xpjj28922y4t3sh84d0n08juy58am7jxmp4g6cgxr4
```

##### Send Ada To The Admin Address
You need to send 2 transactions to this address from your Nami wallet.

- Transaction #1 - 5 tAda
- Transaction #2 - 35 tAda

1. Open your Nami wallet and select Send
2. Copy and paste the admin address you created above as the receiving address
3. Sign and submit the transaction
4. You may need to wait 10 - 60 seconds for the transaction to complete

##### Determine The Admin UTXO
Now to see the UTXOs at your admin address, you can execute the following command

```
cardano-cli query utxo --address addr_test1v83ynr979e4xpjj28922y4t3sh84d0n08juy58am7jxmp4g6cgxr4 --cardano-mode --testnet-magic 1
                           TxHash                                 TxIx        Amount
--------------------------------------------------------------------------------------
d36e0a777ac7234a1dcf30a485dea1c68b81f1286f3c016e35ed5598652976e8     0        35000000 lovelace + TxOutDatumNone
ff5141fe2535284719b2261e78a97b2c3e7111210b6af56b5f6107a2938ee382     0        5000000 lovelace + TxOutDatumNone
```

Note: 1 Ada = 1,000,000 lovelace.

#### Compile Smart Contract Code and Deploy 
1. Open the Web VS Code editor and open the explorer tab on the left.  
2. Navigate to the src directory and open threadToken.hl file.
3. Find and replace the UTXO that you identified in finding the UTXO step above
```
// Define the UTXO to be spent
const TX_ID: ByteArray = #d36e0a777ac7234a1dcf30a485dea1c68b81f1286f3c016e35ed5598652976e8
```
4. Using the Web VS Code explorer, open merchToken.hl
5. Find and replace the PKH of the owner that was obtained above.
```
// Define the owner public key hash (PKH)
const OWNER_PKH: ByteArray = #b9abcf6867519e28042048aa11207214a52e6d5d3288b752d1c27682
```

Save the file
6. In a terminal window, go to the project root directory by typing 
```
cd ~/workspace/repo
```
7. Install deno testing a simple welcome typescript program
```
npx deno-bin run https://deno.land/std/examples/welcome.ts
```
Then execute the following command to compile the threadToken.hl file
```
npx deno-bin run --allow-read --allow-write ./src/deploy-init.js
thread token mph:  c644be7457a17fe5d6a2636bc30dc2acc2ef32cbecfa44daec10b58a
thread token name:  {"bytes": "54687265616420546f6b656e204c6974746572636f696e"}
merchant token mph:  10009086d699dfdd386ab1ddbfb6d6492228e039172f78af780f4686
merchant token name:  #4d65726368616e7420546f6b656e204c6974746572636f696e
```
8. Using the Web VS Code explorer, open lcMint.hl and rewardsToken.hl and update with the new thread token mph
9.Then execute the following command to compile the littercoin and rewards minting policies.
```
npx deno-bin run --allow-read --allow-write ./src/deploy-mint.js 
littercoin mph:  1f6b4c1fbe934b6e778e7086147b8c0a37b1cae31c2d118276c757cd
littercoin token name:  {"bytes": "4c6974746572636f696e"}
Donation rewards mph:  21b3fdc9da10189c19680788c1110d6e684c315614d7e725528827c1
Donation rewards token name:  {"bytes": "446f6e6174696f6e2052657761726473204c6974746572636f696e"
```
10.  Using the Web VS Code explorer, open the lcValidator.hl file and replace the threadtoken mph, the merchant mph and owner pkh with the output from above.  The other values remain the same so you don't need to update them unless you are changing the thread token name, littercoin token name and merchant token name.
```
// Define thread token value
const TT_MPH: ByteArray = #c644be7457a17fe5d6a2636bc30dc2acc2ef32cbecfa44daec10b58a
```
```
// Define the merchant token
const MERCHANT_MPH: ByteArray = #10009086d699dfdd386ab1ddbfb6d6492228e039172f78af780f4686
```
```
// Define the pkh of the owner
const OWNER_PKH: ByteArray = #b68cf82d0cf89438a84bbf5506801e5a9372c3bcc7cfb7fb59b8d901 
```

11. Save the file and then run deno again
```
npx deno-bin run --allow-read --allow-write src/deploy-val.js 
littercoin validator hash:  1289f3bf1ffb1a1dd43f590dd641d85c3dc4f97bd60510216388a8d3
littercoin validator address:  addr_test1wrq55l5av8ff570h42cz88xhcl2fv0q5452hc44gdt8aldqp9hr70
```
12.  Now copy the source and generated files in the src and deploy directories respectively
```
cp src/*.hl app/contracts/
cp deploy/* scripts/preprod/data
```

#### Threadtoken and Littercoin Initialization
1. Next we are going to initialize the smart contract
```
cd scripts
./init-tx.sh preprod
```
2. After waiting 10-60 seconds, you should be able to query the blockchain and see the threadtoken and littercoin locked at the smart contract.
```
cardano-cli query utxo --address addr_test1wz4t9har763a2wrv8qv2ltf400lkjpmxh8wa2ujyqc05fzgauxtdx --cardano-mode --testnet-magic 1
                           TxHash                                 TxIx        Amount
--------------------------------------------------------------------------------------
a5508a1bf3b5e33a0cd9363b5642e63a61f813d32ba87eba2d80766ce09f91c4     0        2000000 lovelace + 1 c644be7457a17fe5d6a2636bc30dc2acc2ef32cbecfa44daec10b58a.54687265616420546f6b656e204c6974746572636f696e + TxOutDatumInline ReferenceTxInsScriptsInlineDatumsInBabbageEra (ScriptDataList [ScriptDataNumber 2000000,ScriptDataNumber 2])
a5508a1bf3b5e33a0cd9363b5642e63a61f813d32ba87eba2d80766ce09f91c4     1        30000000 lovelace + TxOutDatumNone
```

#### Update the Helios files in the openLitterMap repo
Copy the helios files over to the openLitterMap repo so it has the latest version with the updated threadtoken.

```
littercoin$ cp ./src/*.hl ~/Code/openlittermap-web/public/contracts
```

The littercoin smart contract should now be loaded onto the Cardano blockchain and can be accessed via the openLitterMap web application.


## Support/Issues/Community

[Slack](https://join.slack.com/t/openlittermap/shared_invite/zt-fdctasud-mu~OBQKReRdC9Ai9KgGROw) is our main medium of communication and collaboration. Power-users, newcomers, developers, a community of over 400 members - we're all there. 
