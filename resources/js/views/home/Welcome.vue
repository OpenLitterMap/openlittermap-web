<template>
    <div>
        <div class="container home-container">

            <!-- Title, Subtitle, App Icons -->
            <div class="columns c-1">
                <div class="column is-half">
                    <transition name="slide-fade-left" mode="out-in">
                        <h1 class="main-title variable-height"
                            :key="activeHeading.title"
                            v-html="activeHeading.title"
                        ></h1>.
                    </transition>
                    <h2 class="subtitle is-3 home-img-padding">
                        {{ $t('home.welcome.help-us') }}.
                    </h2>

                    <!-- Download app icons -->
                    <div class="flex">
                        <img
                            src="/assets/icons/ios.png"
                            class="app-icon"
                            style="margin-right: 1em;"
                            @click="ios"
                        />
                        <img
                            src="/assets/icons/android.png"
                            class="app-icon"
                            @click="android"
                        />

                    </div>

                </div>

                <div class="column is-half">
                    <div class="top-image">
                        <transition name="slide-fade-right" mode="out-in">
                            <img
                                :key="activeHeading.title"
                                :src="activeHeading.img"
                                :alt="activeHeading.title"
                            />
                        </transition>
                    </div>
                </div>
            </div>

            <!-- Why its important -->
            <div class="why-container">
                <h1 class="main-title">{{ $t('home.welcome.why-collect-data') }}?</h1>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/world.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">1. {{ $t('home.welcome.visibility') }}</h2>
                        <h3 class="welcome-subtitle mb1em">{{ $t('home.welcome.our-maps-reveal-litter-normality') }}.</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/microscope.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">2. {{ $t('home.welcome.science') }}</h2>
                        <h3 class="welcome-subtitle mb1em">{{ $t('home.welcome.our-data-open-source') }}.</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/tree.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">3. {{ $t('home.welcome.community') }}</h2>
                        <h3 class="welcome-subtitle">{{ $t('home.welcome.must-work-together') }}.</h3>
                    </div>
                </div>
            </div>

            <!-- How does it work -->
            <div>
                <h1 class="main-title">{{ $t('home.welcome.how-does-it-work') }}?</h1>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/camera.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">1. {{ $t('home.welcome.take-a-photo') }}</h2>
                        <h3 class="welcome-subtitle mb1em">{{ $t('home.welcome.device-captures-info') }}</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/phone.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">2. {{ $t('home.welcome.tag-the-litter') }}</h2>
                        <h3 class="welcome-subtitle mb1em">{{ $t('home.welcome.tag-litter-you-see') }}!</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/twitter2.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">3. {{ $t('home.welcome.share-results') }}</h2>
                        <h3 class="welcome-subtitle">{{ $t('home.welcome.share') }}!</h3>
                    </div>
                </div>
            </div>

            <!-- Partners -->
            <div class="partners-container container is-max-desktop">
                <p class="has-text-centered main-title">{{ $t('home.welcome.our-partners') }}</p>
                <div>
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
                  </div>
                </div>
<!--                <div class="partners-action has-text-centered">-->
<!--                    <a href="mailto:info@openlittermap.com"-->
<!--                       class="button is-medium is-primary"-->
<!--                    >{{ $t('home.welcome.become-a-partner') }}</a>-->
<!--                </div>-->
            </div>

            <!-- I want to help -->
        </div>

        <Footer />
    </div>
</template>

<script>
import Footer from './Footer'

export default {
    name: 'Welcome',
    components: { Footer },
    data() {
        return {
            headings: [
                { title: this.$t('home.welcome.plastic-pollution-out-of-control'), img: '/assets/plastic_bottles.jpg' },
                { title: this.$t('home.welcome.fires-out-of-control'), img: '/assets/forest_fire.jpg' },
                { title: this.$t('home.welcome.climate-change-out-of-control'), img: '/assets/climate_pollution.jpg' },
            ],
            activeHeadingIndex: 0
        }
    },
    computed: {

        activeHeading() {
            return this.headings[this.activeHeadingIndex]
        },

        /**
         * Boolean to show or hide the modal
         */
        modal ()
        {
            return this.$store.state.modal.show;
        }
    },
    methods: {

        /**
         * Open Google Play store download page
         */
        android ()
        {
            window.open('https://play.google.com/store/apps/details?id=com.geotech.openlittermap', '_blank');
        },

        /**
         * Open App Store download page
         */
        ios ()
        {
            window.open('https://apps.apple.com/us/app/openlittermap/id1475982147', '_blank');
        },

        /**
         * Switches the heading and top image every 5 seconds
         * @see https://usefulangle.com/post/280/settimeout-setinterval-on-inactive-tab
         */
        startHeadingsAnimation ()
        {
            let vm = this;
            let interval = null;

            function setAnimation () {
                if (document.hidden) {
                    // tab is now inactive
                    if (interval) clearInterval(interval);
                    return;
                }

                // tab is active again
                interval = setInterval(() => {
                    vm.activeHeadingIndex = (vm.activeHeadingIndex + 1) % vm.headings.length;
                }, 5000);
            }

            setAnimation();

            document.addEventListener('visibilitychange', setAnimation);
        },
    },
    mounted () {
        this.startHeadingsAnimation();
    }
}
</script>

<style scoped lang="scss">

    .about-icon {
        height: 10em;
        text-align: center;
    }

    .c-1 {
        margin-bottom: 3em;
    }

    .home-container {
        padding-top: 5em;
    }

    .home-img-padding {
        padding-right: 2em;
    }

    .main-title {
        font-size: 4rem;
        font-weight: 800;
        color: #363636;
        line-height: 1.125;
        margin-bottom: 1em;
    }

    .icon-center {
        margin: auto;
    }

    .welcome-mb {
        margin-bottom: 5em;
    }

    .main-subtitle {
        font-size: 2rem;
        color: #4a4a4a;
        font-weight: 700;
        line-height: 1.5;
        margin-bottom: 0.5em;
    }

    .welcome-subtitle {
        color: #4a4a4a;
        font-size: 2rem;
        font-weight: 400;
        line-height: 1.5;
    }

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
        grid-template-columns: repeat(4, minmax(0, 1fr));
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

        .home-container {
            padding-left: 2em;
            padding-right: 2em;
        }

        .partners-list {
            grid-gap: 16px 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    /* Mobile view */
    @media (max-width: 768px) {

        .home-container {
            padding-top: 3em !important;
        }

        .home-img-padding {
            padding: 0;
        }

        .main-title {
            font-size: 3rem;
        }

        .icon-center {
            text-align: center;
            margin-bottom: 2em;
        }

        .welcome-mb {
            margin-bottom: 1em;
        }

        .why-container {
            margin-bottom: 5em;
        }

        .top-image {
            height: 400px;
        }

        .partners-container {
            padding-left: 0;
            padding-right: 0;
        }
    }

    /* Extra small */
    @media (max-width: 576px) {
        .variable-height {
            min-height: 162px;
        }

        .top-image {
            height: 260px;
        }

        .partners-list {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
    }

    /* Extra extra small */
    @media (max-width: 430px) {
        .variable-height {
            min-height: 220px;
        }
    }

    .slide-fade-left-enter-active {
        transition: all .5s ease;
    }
    .slide-fade-left-leave-active {
        transition: all .3s ease-out;
    }
    .slide-fade-left-enter, .slide-fade-left-leave-to {
        transform: translateX(-100px);
        opacity: 0;
    }

    .slide-fade-right-enter-active {
        transition: all .5s ease;
    }
    .slide-fade-right-leave-active {
        transition: all .3s ease-out;
    }
    .slide-fade-right-enter, .slide-fade-right-leave-to {
        transform: translateX(100px);
        opacity: 0;
    }

</style>
