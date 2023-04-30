<template>
    <div class="outer-container">

        <div class="text-container">
            <h1 class="title is-1 mb-0">
                A Zero Waste Currency For Humanity
            </h1>

            <img
                src="/assets/littercoin/launched.png"
                class="launching-soon hide-mobile"
            />

            <h1 class="main-points">
                <p>Created by <span class="is-real">real</span> people 🥷<br></p>
                <p>who are collecting <span class="is-real">real</span> data 📲🎯🗺<br></p>
                <p>about <span class="is-real">real</span> products 🚬🚯🔪🍦<br></p>
                <p>that are polluting a <span class="is-real">real</span> environment. 🌍<br></p>
            </h1>

            <p v-if="loading">Loading...</p>

            <div
                v-else
                class="smart-contract"
                style="font-size: 20px;"
            >
                <div class="flex space-around littercoin-number-container">
                    <div class="mb-2">
                        <strong>Total ada</strong>
                        <p class="sc-number">{{ this.adaAmount.toLocaleString() }}</p>
                    </div>

                    <div class="mb-2">
                        <strong>Total Littercoin</strong>
                        <p class="sc-number">{{ this.lcAmount.toLocaleString() }}</p>
                    </div>
                </div>

                <div class="mb-2">
                    <p>{{ this.ratio.toLocaleString() }} ada or {{ this.getLittercoinPrice }} each</p>
                </div>

                <p style="margin-bottom: 5px;">Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" class="mobile-break-words">{{ this.lcScriptName }}</a></p>
                <p>Address: <a style="font-size: small;" :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>

                <div style="margin-top: 3em;">
                    <p>Anyone can add ada to the smart contract, which gives Littercoin value.</p>
                    <p>However, only pre-approved green-listed Climate Merchants can send Littercoin to the Smart Contract.</p>
                    <p class="mb1">For every ada added, doners receive 1 Littercoin Reward Token.</p>

                    <div v-if="componentIndex === 0">
                        <button
                            class="button is-large is-primary"
                            @click="componentIndex++"
                        >
                            Add ada
                        </button>

                        <BecomeAPartner />
                    </div>

                    <div v-else>
                        <h1 class="title is-4">Select Your Wallet</h1>

                        <div class="flex mb-4" style="justify-content: space-evenly;">
                            <p @click="walletChoice = 'nami'">
                                <input
                                    id="nami"
                                    name="name"
                                    type="radio"
                                    v-model="walletChoice"
                                    value="nami"
                                /> &nbsp;
                                <img src = "/assets/icons/littercoin/nami.png" alt="Nami Wallet" style="width:20px;height:20px;"/>
                                <label for="name">&nbsp; Nami</label>
                            </p>
                            <br>
                            <p @click="walletChoice = 'eternl'">
                                <input
                                    id="eternl"
                                    name="eternl"
                                    type="radio"
                                    v-model="walletChoice"
                                    value="eternl"
                                />&nbsp;
                                <img src = "/assets/icons/littercoin/eternl.png" alt="Eternl Wallet" style="width:20px;height:20px;"/>
                                <label for="eternl">&nbsp; Eternl</label>
                            </p>
                        </div>

                        <input
                            class="input littercoin-input mb-4"
                            v-model="addAdaQty"
                            type="number"
                            min="0"
                        />

                        <button
                            :class="addAdaFormSubmitted ? 'is-loading' : ''"
                            class="button is-large is-primary mb1"
                            @click="beginAddAdaTx"
                            :disabled="addAdaFormSubmitted"
                        >Add ada</button>

                        <p>
                            Thank you for supporting the Littercoin economy!
                        </p>

                        <div v-if="addAdaSuccess">
                            <h1 class="title is-4 mt1">Success!!!</h1>
                            <p>Please wait approximately 60 seconds before refreshing this page for the Ada to show up in the Littercoin Smart Contract.</p>
                            <p>To track this transaction on the blockchain, click on the transaction ID link below.</p>
                            <p>TxId:
                                <a
                                style="font-size: small;"
                                :href="this.addAdaTxIdURL"
                                target="_blank"
                                rel="noopener noreferrer"
                                >{{ this.addAdaTxId }}</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zero waste image -->
        <!-- Credit: Polina Tankilevitch -->
        <!-- https://images.pexels.com/photos/3735156/pexels-photo-3735156.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2 -->
        <img
            src="/assets/pexels-photo-3735156.jpeg"
        />

        <div class="text-container">
            <h1 class="title is-1">
                <strong class="is-green">How it works</strong> 🌱
            </h1>

            <div class="flex mobile-flex-col">
                <div class="littercoin-step">
                    <h1 class="title is-3">
                        1. Pick up some litter
                    </h1>

                    <img
                        src="/assets/littercoin/pick-up-litter.jpeg"
                    />
                </div>

                <div class="littercoin-step">
                    <h1 class="title is-3">
                        2. Collect data
                    </h1>

                    <img
                        src="/assets/about/iphone.PNG"
                    />
                </div>

                <div class="littercoin-step">
                    <h1 class="title is-3">
                        3. Share your impact
                    </h1>

                    <img
                        src="/assets/logo_small.png"
                    />

                    <img
                        src="/assets/icons/twitter2.png"
                        style="width: 5em;"
                    />

                    <img
                        src="/assets/icons/facebook2.png"
                        style="width: 5em;"
                    />

                    <img
                        src="/assets/icons/ig2.png"
                        style="width: 5em;"
                    />
                </div>
            </div>

            <!-- Dank Memes -->
            <h1 class="title is-1" style="margin-bottom: 2em;">
                Dank memes 🔥
            </h1>

            <div class="dank-memes-container">
                <img
                    v-for="meme in memes"
                    :key="meme"
                    :src="'/assets/memes/' + meme"
                    class="dank-meme"
                />
            </div>

            <!-- Open Source -->
            <h1 class="title is-1 mb2-not-mobile">
                Open source 🕊
            </h1>

            <button
                class="button is-large is-primary"
            >
                Join the community
            </button>
        </div>

        <div class="more-info-container">
            <h1
                class="title is-1"
                style="margin-bottom: 1.5em;"
            >
                More info:
            </h1>

            <p class="subtitle is-3 mobile-small-text">Get 100 images past verification in a row to earn 1 Littercoin!</p>
            <p class="subtitle is-3 mobile-small-text">This is likely to change soon, depending on what you and the community want.</p>
            <p class="subtitle is-3 mobile-small-text">The supply of Littercoin is a social construct determined by the total amount of litter.</p>
            <p class="subtitle is-3 mobile-small-text">Unlike government money which is just printed digitally out of thin air, Littercoin is produced geospatially.</p>
            <p class="subtitle is-3 mobile-small-text">Anyone can put the cryptocurrency <a href="https://coinmarketcap.com/currencies/cardano/" target="_blank">ada</a> into the Smart Contract, giving every Littercoin value.</p>
            <p class="subtitle is-3 mobile-small-text">By doing so, Littercoin gains more value giving merchants a reason to accept it.</p>
            <p class="subtitle is-3 mobile-small-text">Climate merchants (eg. zero waste stores) can apply to become Partners.</p>
            <p class="subtitle is-3 mobile-small-text">Approved Partners receive a special Merchant NFT.</p>
            <p class="subtitle is-3 mobile-small-text">Only those who possess a Merchant Token can put Littercoin into the Smart Contract and get the crypto out.</p>
            <p class="subtitle is-3 mobile-small-text">Merchant Tokens expire after 12 months.</p>
            <p class="subtitle is-3 mobile-small-text">By putting ada into the Smart Contract, doners are rewarded with Littercoin Reward Tokens.</p>
            <p class="subtitle is-3 mobile-small-text">These reward tokens will be used for governance and for partners to showcase their commitment.</p>
            <p class="subtitle is-3 mobile-small-text">Mining Littercoin is supposed to be fun, easy and enjoyable. Please do not take life too seriously!</p>
            <p class="subtitle is-3 mobile-small-text">Check out the
                <a
                    href="https://github.com/OpenLitterMap/openlittermap-web/pull/582"
                    target="_blank"
                >open source code</a>

                and

                <a
                    href="https://github.com/OpenLitterMap/openlittermap-web/blob/61d7bd23041c8061c20b812fea1843eb6917164a/littercoin/README.md"
                    target="_blank"
                >documentation</a>
            </p>
        </div>

    </div>
</template>

<script>
import BecomeAPartner from "../../components/BecomeAPartner";
export default {
    name: "Littercoin",
    components: {BecomeAPartner},
    data () {
        return {
            loading: true,
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
            memes: [
                'image.png',
                'IMG_8188.jpg',
                'IMG_8189.jpg',
                'IMG_8190.jpg',
                'IMG_8191.jpg',
                'IMG_8192.jpg',
                'IMG_8193.jpg',
                'IMG_8194.jpg',
                'IMG_8195.jpg',
                'IMG_8196.jpg',
            ],
            componentIndex: 0,
            addAdaQty: 0,
            walletChoice: "",
            addAdaFormSubmitted: false,
            addAdaSuccess: false,
            addAdaTxId: "",
            addAdaTxIdURL: ""
        };
    },
    async created () {
        this.loading = true;

        await axios.get('/littercoin-info')
            .then(async response => {

                console.log('littercoin info', response);

                const lcInfo = await JSON.parse(response.data);
                console.log({ lcInfo });

                if (lcInfo.status === 200) {
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
    computed: {
        /**
         * Get the Littercoin price for a given numbercoin
         */
        getLittercoinPrice () {
            const symbol = this.currencySymbols[this.selectedCurrency];
            const price = this.adaValues[this.selectedCurrency];

            return (this.lcAmount === 0)
                ? "$0"
                : symbol.toString()+ (this.ratio * price).toFixed(2).toString();
        }
    },
    methods: {
        /**
         * Create Transaction to submit ada to the Smart Contract
         */
        async addAda ()
        {
            try
            {
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

                    console.log({ addAdaTx });

                    if (addAdaTx.status === 200)
                    {
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

                        console.log('begin add-ada-submit-tx');

                        await axios.post('/add-ada-submit-tx', {
                            cborSig: walletSig,
                            cborTx: addAdaTx.cborTx
                        })
                        .then(async response => {

                            const submitTx = await JSON.parse(response.data);
                            console.log({ submitTx });

                            if (submitTx.status === 200)
                            {
                                this.addAdaTxId = submitTx.txId;
                                this.addAdaTxIdURL = "https://cexplorer.io/tx/" + submitTx.txId;
                                this.addAdaSuccess = true;

                                // display success
                                this.addAdaFormSubmitted = false;
                            }
                            else
                            {
                                console.error("Could not submit transaction");
                                alert ('Add Ada transaction could not be submitted, please try again');
                                this.addAdaFormSubmitted = false;
                            }
                        })
                        .catch(error => {
                            if (error.response.status === 422){
                                console.error("Invalid Wallet Input", error.response.data.errors);
                            } else {
                                console.error("add-ada-submit-tx: ", error);
                            }
                            alert ('Add Ada transaction could not be submitted, please try again');

                            this.addAdaFormSubmitted = false;
                        });
                    } else if (addAdaTx.status === 408)
                    {
                        console.error("More Ada in the wallet required for this transaction");

                        alert ('More Ada in the wallet required for this transaction"');

                        this.addAdaFormSubmitted = false;
                    }
                    else
                    {
                        console.error("Add Ada transaction was not successful");

                        alert ('Add Ada transaction could not be submitted, please try again');

                        this.addAdaFormSubmitted = false;
                    }
                })
                .catch(error => {
                    if (error.response.status === 422)
                    {
                        console.error("Invalid User Input", error.response.data.errors);

                        alert ('Please check that you have entered a valid destination address');
                    }
                    else
                    {
                        console.error("add-ada-tx", error);

                        alert ('Add Ada transaction could not be submitted, please try again');
                    }

                    this.addAdaFormSubmitted = false;
                });
            }
            catch (err)
            {
                console.error(err);

                this.addAdaFormSubmitted = false;
            }
        },

        /**
         * Validate the user has set enough funds
         *
         * Then begin the add ada transaction
         */
        beginAddAdaTx ()
        {
            if (this.addAdaQty < 2)
            {
                alert ('Minimum 2 Ada donation amount required');
                return
            }

            this.addAdaFormSubmitted = true;

            this.addAda();
        },
    }
}
</script>

<style scoped>

    .text-container {
        padding: 5em;
        text-align: center;
        position: relative;
    }

    .main-points {
        font-size: 30px;
        font-weight: 500;
        width: fit-content;
        margin: auto;
        text-align: center;
        padding: 1.5em 0;
    }

    .main-points p {
        margin-bottom: 8px;
    }

    .launching-soon {
        position: absolute;
        height: 250px;
        top: 9em;
        right: 7em;
    }

    .is-real {
        font-weight: 900;
    }

    .p-6 {
        padding: 0.5em 1em;
    }

    .mb2-not-mobile {
        margin-bottom: 2em;
    }

    .littercoin-number-container {
        min-width: 20em;
    }

    .dank-memes-container {
        overflow-x: scroll;
        overflow-y: hidden;
        white-space: nowrap;
        margin-bottom: 5em;
    }

    .dank-meme {
        display: inline-block;
        max-height: 350px;
        min-height: 200px;
        margin-right: 5em;
    }

    .littercoin-step {
        margin: 3em;
    }

    .smart-contract {
        font-size: 20px;
        width: fit-content;
        margin: auto;
    }

    .sc-number {
        font-size: 50px;
        font-weight: 600;
    }

    .more-info-container {
        margin: auto;
        width: fit-content;
        padding-bottom: 10em;
    }

    .space-around {
        justify-content: space-around;
    }

    @media screen and (max-width: 768px) {
        .main-points {
            font-size: 20px;
        }

        .text-container {
            padding: 1em;
            margin-top: 2em;
            margin-bottom: 2em;
        }

        .hide-mobile {
            display: none;
        }

        .mobile-flex-col {
            flex-direction: column;
        }

        .mobile-small-text {
            font-size: 22px;
        }

        .littercoin-step {
            margin: 2em;
        }

        .littercoin-number-container {
            min-width: 15em !important;
            flex-direction: column;
        }

        .mb2-not-mobile {
            margin-bottom: 1em !important;
        }

        .more-info-container {
            padding-left: 1em;
            padding-right: 1em;
        }

        .mobile-break-words {
            overflow-wrap: anywhere;
        }
    }


</style>
