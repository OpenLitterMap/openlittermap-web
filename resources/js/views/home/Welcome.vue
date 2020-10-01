<template>
    <div>
        <div class="container home-container">

            <!-- Title, Subtitle, App Icons -->
            <div class="columns c-1">
                <div class="column is-half">
                    <h1 class="main-title">
                        Plastic pollution is out of control.
                    </h1>
                    <h2 class="subtitle is-3 home-img-padding">
                        Help us create the world's most advanced open database on litter, brands & plastic pollution.
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
                    <img src="/assets/plastic_bottles.jpg" />
                </div>
            </div>

            <!-- Why its important -->
            <div class="why-container">
                <h1 class="main-title">Why should we collect data?</h1>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/world.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">1. Visibility</h2>
                        <h3 class="welcome-subtitle mb1em">Our maps reveal what has become normal and invisible. This education is important to give visibility to litter.</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/microscope.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">2. Science</h2>
                        <h3 class="welcome-subtitle mb1em">Our data is open and accessible. Anyone can download our data and use it, for any purpose.</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/tree.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">3. Community</h2>
                        <h3 class="welcome-subtitle">Only by working together can we create a huge shift in how we understand and respond to pollution.</h3>
                    </div>
                </div>
            </div>

            <!-- How does it work -->
            <div>
                <h1 class="main-title">How does it work?</h1>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/camera.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">1. Take a photo</h2>
                        <h3 class="welcome-subtitle mb1em">Your device can capture valuable information about the location, time, object, material and brand.</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/home/phone.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">2. Tag the litter</h2>
                        <h3 class="welcome-subtitle mb1em">Just tag what litter you see in the photo. You can tag if the litter has been picked up or if it's still there!</h3>
                    </div>
                </div>

                <div class="columns welcome-mb">
                    <div class="column is-one-quarter icon-center">
                        <img src="/assets/icons/twitter2.png" class="about-icon" />
                    </div>

                    <div class="column ma">
                        <h2 class="main-subtitle">3. Share your results</h2>
                        <h3 class="welcome-subtitle">Share the maps or download our data. Let's show everyone how badly polluted the world really is!</h3>
                    </div>
                </div>
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
    computed: {

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
        }
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

    /* Smaller screens */
    @media (max-width: 1024px) {

        .home-container {
            padding-left: 2em;
            padding-right: 2em;
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
    }

</style>
