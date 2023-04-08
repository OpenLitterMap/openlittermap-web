<template>
    <div class="partners-list">
        <div class="partner has-text-centered">
            <a href="https://cardano.org/" target="_blank">
                <img loading="lazy" src="/assets/partners/cardano.svg" alt="Partner">
            </a>
        </div>
        <div class="partner has-text-centered">
            <a href="https://elfc.com/" target="_blank">
                <img loading="lazy" src="/assets/partners/engine-lease-finance-logo.png" alt="Partner">
            </a>
        </div>
        <div class="partner has-text-centered">
            <a href="https://twitter.com/PlasticRaiders?s=20" target="_blank">
                <img loading="lazy" src="/assets/partners/plastic_raiders.png" alt="Partner">
            </a>
        </div>
        <div class="partner has-text-centered">
            <a href="https://www.sfi.ie/" target="_blank">
                <img loading="lazy" src="/assets/partners/science-foundation-ireland.png" alt="Partner">
            </a>
        </div>
        <div class="partner has-text-centered">
            <a href="https://www.digitalpublicgoods.net/" target="_blank">
                <img loading="lazy" src="/assets/partners/dpga.png" alt="Partner">
            </a>
        </div>
        <div class="partner has-text-centered">
            <a href="https://www.cardano4climate.com/" target="_blank">
                <img loading="lazy" src="/assets/partners/c4clogo.jpeg" alt="Partner">
            </a>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Partners'
};
</script>

<style scoped lang="scss">

    .partners-list {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-gap: 32px 32px;
    }

    .partner {
        max-width: 200px;
        margin: auto;
    }

    .partner,
    .partner img {
        width: 100%;
    }

    /* Smaller screens */
    @media (max-width: 1024px) {

        .partners-list {
            grid-gap: 16px 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* Mobile view */
    @media (max-width: 768px) {

    }

    /* Extra small */
    @media (max-width: 576px) {

        .partners-list {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
    }

    /* Extra extra small */
    @media (max-width: 430px) {
    }

</style>
