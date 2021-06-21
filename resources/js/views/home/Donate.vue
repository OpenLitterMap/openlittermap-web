<template>
    <section class="section hero is-fullheight is-primary is-bold">
        <div class="container">
            <h3 class="title is-3">{{ $t('home.donate.olm-dependent-on-donations') }}</h3>
            <br>
            <img
                src="/assets/IMG_0556.JPG"
                style="height: 450px; display: block; margin: auto; object-fit: cover;"
                alt="It's important"
                title="It's important"
            >
            <p style="text-align: center; margin-top: 12px">{{ $t('home.donate.its-important') }}</p>
            <br>
            <div style="word-break: break-all">
                <p>Bitcoin: 3Cvyhhec777Dnc6a5QHZ1S8DZpL3nodZ2K</p>
                <p>Bitcoin Cash: 14FEA8ckGiTf5HvYhANBAEpmvawdJpFFU6</p>
                <p>Ethereum: 0x43DbD68771cEDad272dcC78c4108B543DDF8a449</p>
                <p>Dash: XfLLMTKeSwXhaoUGpUJVTV9KrtGTXiMoAG</p>
                <p v-if="loading">Updating....</p>
            </div>
            <br>
            <div class="container">
                <donate-buttons @donations-loaded="loading = false" />
            </div>
        </div>
    </section>
</template>

<script>
import DonateButtons from '../../components/DonateButtons'

export default {
    name: 'Donate',
    components: { DonateButtons },
    data() {
        return {
            loading: true
        }
    }
}
</script>

<style scoped>

</style>
