<template>
    <div>
        <!-- Cigarette Butts -->
        <section class="section">
            <div class="columns">
                <div class="column is-one-third is-offset-1" style="text-align: center;">
                    <img src="/assets/mfua/mine.jpeg" />
                </div>

                <div class="column cig-2">
                    <h2 id="butts1txt" class="title is-2" style="color: red; text-align: center;">
                        <strong>Ukraine is becoming contaminated with dangerous military equipment</strong>
                    </h2>
                </div>
            </div>
            <br>
            <br>
            <div class="columns">
                <div class="column is-one-third is-offset-1">
                    <h1 class="title is-1" style="text-align: center;"><strong>Peoples lives are at risk</strong></h1>
                    <br>
                    <h1 class="subtitle is-3">Do not attempt to look for or remove these objects</h1>
                    <br>
                    <h1 class="subtitle is-3">If you know of them in your community, report them with our app</h1>
                    <br>
                    <p class="subtitle is-3">Together we can help improve emergency response</p>
                </div>

                <div class="column is-offset-1 butts-img">
                    <img src="/assets/mfua/explosion.jpeg" style="height: 600px;">
                </div>
            </div>
        </section>

        <!-- Call to action -->
        <section class="hero is-success">
            <div class="hero-body">
                <div class="container has-text-centered">
                    <h1 class="title">
                        <strong>
                            {{ $t('home.about.about7') }}
                        </strong>
                    </h1>
                    <!-- subtitle removed -->
                </div>
                <div class="has-text-centered" style="padding-top: 2em;">
                    <router-link to="/signup">
                        <button class="button is-large is-info hov">{{ $t('home.about.about9') }}</button>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- 1. Just take a photo -->
        <div class="has-text-centered pt3">
            <h1 class="title is-1">1. {{ $t('home.about.about9a') }}</h1>

            <img src="/assets/about/iphone.PNG" />
        </div>

        <!-- 2. Tag it with our app -->
        <div class="has-text-centered pt3">
            <h1 class="title is-1">2. {{ $t('home.about.about9b') }}</h1>

            <img src="/assets/about/facemask-tag.PNG" />
        </div>

        <!-- 3. Upload it -->
        <div class="has-text-centered pt3">
            <h1 class="title is-1">3. {{ $t('home.about.about9c') }}</h1>

            <img src="/assets/about/facemask-map.PNG" />
        </div>

        <!-- Download app icons -->
        <div class="flex jc pt3">
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

        <div class="container">

            <div class="has-text-centered" style="padding-top: 2em;">
                <form action="/signup">
                    <button class="button is-large is-primary hov">{{ $t('home.about.about35') }}</button>
                </form>
            </div>
            <br>
            <br>
        </div>
    </div>
</template>

<script>
export default {
    name: 'About',
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

<style scoped>

    .butts-img {
        text-align: center;
        padding-right: 5em;
    }

    .cig-2 {
        align-items: center;
        display: flex;
        padding: 5em;
    }

    @media screen and (max-width: 768px)
    {
        .butts-img {
            padding-right: 0;
        }

        .cig-2 {
            padding: 1em;
        }
    }
</style>
