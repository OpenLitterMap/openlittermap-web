<template>
    <div class="grid-container">
        <!-- Cigarette Butts -->
        <div>
            <img src="/assets/butts.jpg">
        </div>

        <div class="text-px">
            <h1 class="title has-text-centered" style="color: red">
                <strong>{{ $t('home.about.about2') }}</strong>
            </h1>
        </div>
        <div class="text-px">
            <h1 class="title has-text-centered">
                <strong>{{ $t('home.about.about3') }}</strong>
            </h1>
            <h1 class="subtitle is-4 font-size-mb">
                {{ $t('home.about.about4') }}
            </h1>
            <h1 class="subtitle is-4 font-size-mb">
                {{ $t('home.about.about5') }}
            </h1>
            <p class="subtitle is-4 font-size-mb">
                {{ $t('home.about.about6') }}
            </p>
        </div>
        <div class="butts-img">
            <img src="/assets/cigbutts_jar.jpg" class="img-max-height">
        </div>

        <!-- Call to action -->
        <section class="hero is-success grid-item-full">
            <div class="hero-body has-text-centered">
                <div class="pb-5">
                    <h1 class="title">
                        <strong>
                            {{ $t('home.about.about7') }}
                        </strong>
                    </h1>
                    <!-- subtitle removed -->
                </div>
                <router-link
                    to="/signup"
                    class="button is-size-5-mobile is-size-4-desktop is-size-4-tablet is-info hov"
                >
                    {{ $t('home.about.about9') }}
                </router-link>
            </div>
        </section>

        <!-- 1. Just take a photo -->
        <div class="has-text-centered grid-item-full">
            <h1 class="title">
                1. Just take a photo
            </h1>
            <img class="pt-2" src="/assets/about/iphone.PNG">
        </div>

        <!-- 2. Tag it with our app -->
        <div class="has-text-centered grid-item-full">
            <h1 class="title">
                2. Tag the litter
            </h1>
            <img class="pt-2 img-max-height" src="/assets/about/facemask-tag.PNG">
        </div>

        <!-- 3. Upload it -->
        <div class="has-text-centered grid-item-full">
            <h1 class="title">
                3. Upload it
            </h1>
            <img class="pt-2 img-max-height" src="/assets/about/facemask-map.PNG">
        </div>

        <!-- Download app icons -->
        <div class="flex jc grid-item-full">
            <img
                src="/assets/icons/ios.png"
                class="app-icon mr-4"
                @click="ios"
            >
            <img
                src="/assets/icons/android.png"
                class="app-icon"
                @click="android"
            >
        </div>

        <!-- Plastic from land to sea -->
        <div class="text-px has-text-centered">
            <h1 class="title">
                <strong style="color: red;">{{ $t('home.about.about10') }}</strong>
            </h1>
            <h1 class="title">
                <strong style="color: red;">{{ $t('home.about.about11') }}</strong>
            </h1>
        </div>
        <div>
            <img src="/assets/plastic_bottles.jpg">
        </div>

        <!-- The illusion of urban cleaning -->
        <section class="hero is-warning grid-item-full">
            <div class="hero-body">
                <div class="container has-text-centered">
                    <h1 class="title has-text-left">
                        {{ $t('home.about.about12') }}
                    </h1>

                    <img class="img-max-height" src="/assets/cigbutts.jpg">

                    <h1 class="title has-text-right">
                        {{ $t('home.about.about13') }}
                    </h1>
                </div>
            </div>
        </section>

        <!-- More info -->
        <div class="more-info-grid grid-item-full">
            <article class="notification is-primary">
                <p class="title">
                    {{ $t('home.about.about14') }} <b style="color: black;">{{ $t('home.about.about14a') }}</b>
                </p>
                <p class="title pb-4">
                    {{ $t('home.about.about14b') }}
                </p>
                <p class="subtitle has-text-right" style="color: red;">
                    <strong>{{ $t('home.about.about15') }}</strong>
                </p>
            </article>
            <article class="notification is-info grid-item-2">
                <p class="title">
                    Our data is mapped by Space, Time, Location, and Behaviour.
                </p>
                <p class="subtitle">
                    Check out this incredible stretch of Free and Open Data on the pollution being caused by the products of a handful of global corporations
                </p>
                <!-- <figure class="image is-4by3"> -->
                <a href="https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/map" class="text-truncate mb-4" style="display: block;">
                    https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/map
                </a>
                <img class="mb-4" src="/assets/nlbrands.png">
                <!-- </figure> -->
                <p class="subtitle">
                    Want to download the data?
                </p>
                <a href="https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/download" class="text-truncate" style="display: block;">
                    https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/download
                </a>
            </article>
            <article class="is-10 notification is-success grid-item-3">
                <div class="content">
                    <p class="title">
                        {{ $t('home.about.about22') }}
                    </p>
                    <ul>
                        <li>{{ $t('home.about.about24') }}</li>
                        <li>{{ $t('home.about.about25') }}</li>
                        <li>{{ $t('home.about.about26') }}</li>
                        <li>{{ $t('home.about.about27') }}</li>
                        <li>{{ $t('home.about.about28') }}</li>
                        <li>{{ $t('home.about.about29') }}</li>
                        <li>{{ $t('home.about.about29a') }}</li>
                    </ul>
                </div>
            </article>
            <article class="notification is-warning">
                <p class="title pb-4">
                    {{ $t('home.about.about16') }}
                </p>
                <p class="subtitle has-text-right" style="color: red;">
                    <b>{{ $t('home.about.about17') }}</b>
                </p>
            </article>
            <article class="notification is-danger grid-item-5">
                <p class="title">
                    {{ $t('home.about.about18') }}
                </p>
                <p class="subtitle">
                    {{ $t('home.about.about19') }}
                    <strong>{{ $t('home.about.about20') }}</strong>
                    {{ $t('home.about.about21') }}
                </p>
                <div class="content">
                    <!-- Content -->
                </div>
            </article>
        </div>

        <div class="grid-item-full">
            <h1 class="title has-text-centered">
                <strong>{{ $t('home.about.about30') }}</strong> <strong style="color: red;">{{ $t('home.about.about301') }}</strong>
            </h1>
        </div>

        <div class="has-text-centered grid-item-full">
            <img class="img-max-height" src="/assets/marinelitter.jpg">
            <p> Dublin, Ireland. </p>
        </div>
        <div class="grid-item-full">
            <h1 class="title has-text-centered is-danger">
                <strong>{{ $t('home.about.about302') }}</strong>
            </h1>
        </div>
        <div class="grid-item-full has-text-centered">
            <img class="img-max-height" src="/assets/microplastics_oranmore.JPG">
            <p>Microplastics in Oranmore, Co. Galway.</p>
        </div>

        <div class="text-px grid-item-full">
            <h1 class="title">
                {{ $t('home.about.about31') }}
            </h1>
        </div>
        <div class="has-text-centered align-normal">
            <img src="/assets/urban.JPG">
            <p class="has-text-centered">
                Penrose Wharf, Cork City, Ireland (above)
            </p>
        </div>

        <div class="has-text-centered align-normal">
            <img src="/assets/IMG_0554.JPG">
            <p>
                Accra, Capital of Ghana, North-Western Coast of Africa <a href="https://www.facebook.com/nshorena/posts/1652239435009949">more photos on facebook</a>
            </p>
        </div>
        <div class="grid-item-full has-text-centered">
            <img src="/assets/IMG_0556.JPG">
            <p>
                {{ $t('home.about.about32') }}
            </p>
        </div>

        <div class="grid-item-full text-px">
            <h1 class="title has-text-left">
                {{ $t('home.about.about33') }}
            </h1>
        </div>
        <div class="grid-item-full text-px">
            <h1 class="title has-text-right">
                {{ $t('home.about.about34') }}
            </h1>
        </div>
        <div class="has-text-centered grid-item-full">
            <router-link to="/signup" class="button is-size-5-mobile is-size-4-desktop is-size-4-tablet is-primary hov">
                {{ $t('home.about.about35') }}
            </router-link>
        </div>

        <footer class="grid-item-full pt-5">
            <div class="has-text-centered">
                <p>
                    <strong>OpenLitterMap</strong> by <a href="https://ie.linkedin.com/in/seanlynchgis">Seán Lynch, M.Sc, M.Sc., B.A.</a>
                </p>
                <p>info@openlittermap.com</p>
            </div>
        </footer>
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
};
</script>

<style scoped lang="scss">
@import '../../styles/variables.scss';
    .grid-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        row-gap: 2rem;
        padding: 2rem 10px;
        @include media-breakpoint-up(md) {
            grid-template-columns: repeat(2, minmax(240px, 1fr));
            column-gap: 2rem;
            padding: 3rem 0;
            align-items: center;
            .img-max-height{
                max-height: 650px
            }
            .more-info-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(240px, 1fr));
                column-gap: 1rem;
                .grid-item-2 {
                    grid-column-start: 2;
                    grid-row: 1 / 3;
                }
                .grid-item-3 {
                    grid-row: 1 / 3;
                    grid-column-start: 3;
                }
                .grid-item-5 {
                    grid-row-start: 3;
                    grid-column: 1 / 4;
                }
            }
            .text-px {
                padding: 0 2rem;
            }
            .align-normal {
                align-self: normal;
            }
            .grid-item-full {
                grid-column: 1 / 3;
            }
        }
        @include media-breakpoint-down(sm) {
            .font-size-mb {
                font-size: 1rem;
            }
            .title {
                font-size: 1.5rem;
            }
        }
    }
    .butts-img {
        text-align: center;
        padding-right: 5em;
    }
    .text-truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap
    }
    @media screen and (max-width: 768px)
    {
        .butts-img {
            padding-right: 0;
        }
    }
</style>
