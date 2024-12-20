<template>
    <section class="community" :style="{backgroundImage: imageStyle}">
        <div class="hero is-medium container">
            <div class="hero-body community-hero">
                <h1 class="title has-text-white has-text-weight-bold is-size-2 is-size-1-desktop">
                    {{ $t('home.community.title') }}
                </h1>
                <h2 class="subtitle has-text-white-bis is-size-4 mt-1">
                    {{ $t('home.community.subtitle') }}
                </h2>
            </div>
        </div>
        <p class="copyright">
            <a target="_blank" href="https://www.pexels.com/photo/green-grass-field-during-sun-rise-997121/">
                Photo by Peter Fazekas from Pexels
            </a>
        </p>
    </section>
</template>
<script>
export default {
    name: 'HeroSection',
    computed: {
        imageStyle ()
        {
            return `linear-gradient(to left, transparent 0%, black 100%), url("${window.location.origin}/assets/grass.jpg")`;
        }
    }
};
</script>
<style lang="scss" scoped>
.community {
    background: black no-repeat fixed top right;
    background-size: cover;
    position: relative;
}
.copyright {
    position: absolute;
    right: 5px;
    bottom: 5px;
    color: gray;
    font-size: 10px;

    a, a:hover {
        color: gray;
        text-decoration: none;
    }
}

@media screen and (min-width: 1280px) {
    .community-hero {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
}
</style>
