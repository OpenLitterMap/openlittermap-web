<template>
    <section class="community" :style="{backgroundImage: imageStyle}">
        <div class="hero is-medium container">
            <div class="hero-body community-hero">
                <h1 class="title has-text-white has-text-weight-bold is-size-1-desktop">
                    Help us change the world.
                </h1>
                <h2 class="subtitle has-text-white-bis is-size-3">
                    We're growing by the day
                </h2>
            </div>
        </div>
    </section>
</template>
<script>
export default {
    name: 'HeroSection',
    computed: {
        imageStyle ()
        {
            return `linear-gradient(to left, transparent 0%, black 100%), url("${window.location.origin}/assets/litter-picking-copy.jpg")`;
        }
    }
};
</script>
<style lang="scss" scoped>
.community {
    background: black no-repeat fixed top right;
    background-size: cover;
}

@media screen and (min-width: 1280px) {
    .community-hero {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
</style>
