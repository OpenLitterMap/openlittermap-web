<template>
    <div class="outer-container">

        <div class="text-container">
            <h1 class="title is-1 mb-0">
                A Zero Waste Currency For Humanity
            </h1>

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
                <div class="flex">
                    <div class="mb-2" style="padding-right: 3em;">
                        <strong>Total ada</strong>
                        <p class="sc-number">{{ this.adaAmount.toLocaleString() }}</p>
                    </div>

                    <div class="mb-2">
                        <strong>Total Littercoin</strong>
                        <p class="sc-number">{{ this.lcAmount.toLocaleString() }}</p>
                    </div>
                </div>

                <div class="mb-2">
                    <p>{{ this.ratio.toLocaleString() }} ada per Littercoin</p>
                    <p>or {{ this.getLittercoinPrice }} per Littercoin</p>
                </div>

                <p>Source Code: <a :href="this.lcScriptURL" target="_blank" rel="noopener noreferrer" >{{ this.lcScriptName }}</a></p>
                <p>Address: <a style="font-size: small;" :href="this.lcAddrURL" target="_blank" rel="noopener noreferrer" >{{ this.lcAddr }}</a></p>
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

            <div class="flex">
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
            <h1 class="title is-1" style="margin-bottom: 2em;">
                Open source 🕊
            </h1>

            <button
                class="button is-large is-primary"
            >
                Join the community
            </button>
        </div>

        <div class="more-info-container">
            <h1 class="title is-1" style="margin-bottom: 1.5em;2">More info:</h1>

            <p class="subtitle is-3">Get 100 images past verification in a row to earn 1 Littercoin!</p>
            <p class="subtitle is-3">This is likely to change soon, depending on what you and the community want.</p>
            <p class="subtitle is-3">The supply of Littercoin is a social construct determined by the total amount of litter.</p>
            <p class="subtitle is-3">Unlike goverment money which is just printed digitally out of thin air, Littercoin is produced geospatially.</p>
            <p class="subtitle is-3">Anyone can put the cryptocurrency `ada` into the Smart Contract, giving every Littercoin value.</p>
            <p class="subtitle is-3">By doing so, Littercoin gains more value giving merchants a reason to accept it.</p>
            <p class="subtitle is-3">Climate merchants (eg. zero waste stores) can apply to become Partners.</p>
            <p class="subtitle is-3">Approved Partners receive a special Merchant NFT.</p>
            <p class="subtitle is-3">The people holding the Merchant Token can put Littercoin into the Smart Contract and get the crypto out.</p>
            <p class="subtitle is-3">Merchant Tokens expire after 12 months.</p>
            <p class="subtitle is-3">Check out the open source code and documentation</p>
            <p class="subtitle is-3">By putting `ada` into the Smart Contract, doners are rewarded with Donation Incentive Reward Tokens.</p>
            <p class="subtitle is-3">These will be used for governance in the future, as for merit and commitment.</p>
            <p class="subtitle is-3">Mining Littercoin is supposed to be fun, easy and enjoyable.</p>
            <p></p>
        </div>

    </div>
</template>

<script>
export default {
    name: "Littercoin",
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
                ? 0
                : symbol.toString()+ (this.ratio * price).toFixed(2).toString();
        }
    }
}
</script>

<style scoped>

    .text-container {
        padding: 5em;
        text-align: center;
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

    .is-real {
        font-weight: 900;
    }

    .p-6 {
        padding: 0.5em 1em;
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

    @media screen and (max-width: 768px) {
        .main-points {
            font-size: 20px;
        }

        .text-container {
            padding: 1em;
            margin-top: 2em;
        }
    }


</style>