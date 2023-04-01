<template>
    <div class="outer-container">

        <div class="text-container">
            <h1 class="title is-1">
                A Zero Waste Currency For Humanity
            </h1>

            <h1 class="main-points">
                <span>Created by <span class="is-real">real</span> people 🥷<br></span>
                <span>who are collecting <span class="is-real">real</span> data 📲<br></span>
                <span>about <span class="is-real">real</span> companies 🎯<br></span>
                <span>that are polluting a <span class="is-real">real</span> environment. 🌍<br></span>
            </h1>

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
        </div>

        <!-- Zero waste image -->
        <!-- Credit: Polina Tankilevitch -->
        <!-- https://images.pexels.com/photos/3735156/pexels-photo-3735156.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2 -->
        <img
            src="/assets/pexels-photo-3735156.jpeg"
        />

        <div class="text-container">
            <h1 class="title is-1">
                <!-- Needs tooptip -->
                Stimulating <strong class="is-green">climatokeveganomics</strong> 🌱
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
        font-size: 25px;
        font-weight: 500;
        width: fit-content;
        margin: auto;
        text-align: left;
        margin-top: 2em;
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

    @media screen and (max-width: 768px) {
        .text-container {
            padding: 1em;
            margin-top: 2em;
        }
    }


</style>