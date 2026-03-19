<template>
    <div class="partners-container container is-max-desktop">
        <p class="main-title">{{ $t('Our Partners') }}</p>

        <div class="partners-list">
            <div class="partner has-text-centered">
                <a href="https://cardano.org/" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/cardano.svg" alt="Partner" />
                </a>
            </div>
            <div class="partner has-text-centered">
                <a href="https://elfc.com/" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/engine-lease-finance-logo.png" alt="Partner" />
                </a>
            </div>
            <div class="partner has-text-centered">
                <a href="https://twitter.com/PlasticRaiders?s=20" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/plastic_raiders.png" alt="Partner" />
                </a>
            </div>
            <div class="partner has-text-centered">
                <a href="https://www.gitcoin.co/" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/gitcoin.png" alt="Partner" />
                </a>
            </div>
            <div class="partner has-text-centered">
                <a href="https://www.digitalpublicgoods.net/" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/dpga.png" alt="Partner" />
                </a>
            </div>
            <div class="partner has-text-centered">
                <a href="https://www.cardano4climate.com/" target="_blank">
                    <img loading="lazy" src="../../../assets/partners/c4clogo.jpeg" alt="Partner" />
                </a>
            </div>
        </div>
    </div>
</template>

<script setup></script>

<style scoped lang="scss">
.partners-container {
    padding-left: 72px;
    padding-right: 72px;
    margin-bottom: 36px;
}

.partners-action {
    margin-top: 36px;
}

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

/* Extra small */
@media (max-width: 576px) {
    .partners-list {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
}
</style>
