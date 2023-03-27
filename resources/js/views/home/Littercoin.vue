<template>
    <div class="outer-container">

        <div class="text-container">
            <h1 class="title is-1">
                A Zero Waste Currency For Humanity
            </h1>

            <h1 class="title is-2 p-6">
                Created by real people, who are collecting real data, about real companies, who are polluting a real environment.
            </h1>

            <button
                class="button is-large is-primary"
            >
                Join the community
            </button>
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
            totalAdaAmount: 0,
            totalLittercoinSupply: 0,
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
            ]
        };
    },
    async created () {
        this.loading = true;

        await axios.get('/littercoin-info')
            .then(async response => {

                const lcInfo = await JSON.parse(response.data);
                if (lcInfo.status == 200) {
                    this.totalAdaAmount = lcInfo.payload.list[0].int / 1000000;
                    this.totalLittercoinSupply = lcInfo.payload.list[1].int;
                    this.ratio = this.totalAdaAmount / this.totalLittercoinSupply;
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
    }
}
</script>

<style scoped>

    .text-container {
        padding: 5em;
        text-align: center;
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


</style>