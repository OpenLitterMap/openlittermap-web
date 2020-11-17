<template>
    <section class="section hero is-fullheight is-primary is-bold">
        <h3 class="title is-3">{{ $t('home.donate.olm-dependent-on-donations') }}</h3>
        <br>
        <img src="/assets/IMG_0556.JPG" style="height: 450px; display: block; margin: auto;">
        <p style="text-align: center;">{{ $t('home.donate.its-important') }}</p>
        <br>
        <p>Bitcoin: 3Cvyhhec777Dnc6a5QHZ1S8DZpL3nodZ2K</p>
        <p>Bitcoin Cash: 14FEA8ckGiTf5HvYhANBAEpmvawdJpFFU6</p>
        <p>Ethereum: 0x43DbD68771cEDad272dcC78c4108B543DDF8a449</p>
        <p>Dash: XfLLMTKeSwXhaoUGpUJVTV9KrtGTXiMoAG</p>
        <p>Updating....brb.</p>
        <br>
        <div class="container">
            <donate-buttons  />
        </div>
    </section>
</template>

<script>
import DonateButtons from '../../components/DonateButtons'

export default {
    name: 'Donate',
    components: { DonateButtons },
}
</script>

<style scoped>

</style>