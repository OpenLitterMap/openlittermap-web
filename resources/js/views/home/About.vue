<template>
    <div>

        <!-- Cigarette Butts -->
        <section class="section">
            <div class="columns">
                <div class="column is-one-third is-offset-1" style="text-align: center;">
                    <img src="/assets/butts.jpg" />
                </div>

                <div class="column cig-2">
                    <h2 id="butts1txt" class="title is-2" style="color: red; text-align: center;">
                        <strong>{{ $t('about.about2') }}</strong>
                    </h2>
                </div>
            </div>
            <br>
            <br>
            <div class="columns">
                <div class="column is-one-third is-offset-1">
                    <h1 class="title is-1" style="text-align: center;"><strong>{{ $t('about.about3') }}</strong></h1>
                    <br>
                    <h1 class="subtitle is-3">{{ $t('about.about4') }}</h1>
                    <br>
                    <h1 class="subtitle is-3">{{ $t('about.about5') }}</h1>
                    <br>
                    <p class="subtitle is-3">{{ $t('about.about6') }}</p>
                </div>

                <div class="column is-offset-1" style="text-align: center; padding-right: 5em;">
                    <img src="/assets/cigbutts_jar.jpg" style="height: 600px;">
                </div>
            </div>
        </section>

        <!-- Call to action -->
        <section class="hero is-success">
            <div class="hero-body">
                <div class="container has-text-centered">
                    <h1 class="title">
                        <strong>
                            {{ $t('about.about7') }}
                        </strong>
                    </h1>
                    <h2 class="subtitle">
                        <strong>
                            {{ $t('about.about8') }}
                        </strong>
                    </h2>
                </div>
                <div class="has-text-centered" style="padding-top: 2em;">
                    <router-link to="/signup">
                        <button class="button is-large is-info hov">{{ $t('about.about9') }}</button>
                    </router-link>
                </div>
            </div>
        </section>

        <!-- Plastic from land to sea -->
        <section>
            <div class="columns" style="padding-top: 4em; padding-bottom: 4em;">
                <div class="column is-one-third is-offset-1" style="padding-left: 2em; margin:auto;">
                    <h1 class="title is-2" style="text-align: center;"><strong style="color: red;">{{ $t('about.about10') }}</strong></h1>
                    <br>
                    <h1 class="title is-2" style="text-align: center;"><strong style="color: red;">{{ $t('about.about11') }}</strong></h1>
                </div>

                <div class="column is-one-third is-offset-1" style="padding-top: 4em; padding-bottom: 4em; margin:auto;">
                    <img src="/assets/plastic_bottles.jpg" style="">
                </div>
            </div>
        </section>

        <!-- The illusion of urban cleaning -->
        <section class="hero is-warning">
            <div class="hero-body">
                <div class="container">
                    <h1 class="title is-1">
                        {{ $t('about.about12') }}
                    </h1>

                    <img src="/assets/cigbutts.jpg">

                    <h1 class="title is-1 has-text-right">
                        {{ $t('about.about13') }}
                    </h1>
                </div>
            </div>
        </section>

        <!-- More info -->
        <div class="hero-body">
            <div class="container">
                <div class="tile is-ancestor">
                    <div class="tile is-vertical is-8">
                        <div class="tile">
                            <div class="tile is-parent is-vertical">
                                <article class="tile is-child notification is-primary">
                                    <p class="title">{{ $t('about.about14') }} <b style="color: black;">{{ $t('about.about14a') }}</b></p>
                                    <p class="title" style="padding-bottom: 1em;">{{ $t('about.about14b') }}</p>
                                    <p class="subtitle" style="text-align: right; color: red;"><strong>{{ $t('about.about15') }}</strong></p>
                                </article>
                                <article class="tile is-child notification is-warning">
                                    <p class="title" style="padding-bottom: 1em;">{{ $t('about.about16') }}</p>
                                    <p class="subtitle" style="text-align: right; color: red; padding-bottom: 1em;"><b>{{ $t('about.about17') }}</b></p>
                                </article>
                            </div>
                            <div class="tile is-parent">
                                <article class="tile is-child notification is-info">
                                    <p class="title">All Data is mapped by Space, Time, Location, and Behaviour by Design.</p>
                                    <p class="subtitle">Check out this incredible stretch of Free and Open Data on the pollution being caused by the products of a handful of global corporations</p>
                                    <!-- <figure class="image is-4by3"> -->
                                    <a href="https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/map">https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/map</a>
                                    <br>
                                    <br>
                                    <img src="/assets/nlbrands.png">
                                    <!-- </figure> -->
                                    <br>
                                    <br>
                                    <p class="subtitle">Want to download the data?</p>
                                    <a href="https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/download">https://openlittermap.com/maps/The%20Netherlands/Zuid-Holland/Wassenaar/download</a>
                                    <br>
                                </article>
                            </div>
                        </div>
                        <div class="tile is-parent">
                            <article class="tile is-child notification is-danger">
                                <p class="title">{{ $t('about.about18') }}</p>
                                <p class="subtitle">{{ $t('about.about19') }}<strong>{{ $t('about.about20') }}</strong> {{ $t('about.about21') }}</p>
                                <div class="content">
                                    <!-- Content -->
                                </div>
                            </article>
                        </div>
                    </div>
                    <div class="tile is-parent is-5">
                        <article class="tile is-child is-10 notification is-success">
                            <div class="content">
                                <p class="title">{{ $t('about.about22') }}</p>
                                <div class="content">
                                    <ul>
                                        <li>{{ $t('about.about24') }}</li>
                                        <li>{{ $t('about.about25') }}</li>
                                        <li>{{ $t('about.about26') }}</li>
                                        <li>{{ $t('about.about27') }}</li>
                                        <li>{{ $t('about.about28') }}</li>
                                        <li>{{ $t('about.about29') }}</li>
                                        <li>{{ $t('about.about29a') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <br>
            <h1 class="title is-1" style="color: black; text-align: center;"><strong>{{ $t('about.about30') }}</strong> <strong style="color: red;">{{ $t('about.about301') }}</strong></h1>
            <br>
        </div>

        <div class="container" style="padding-bottom:5em; text-align: center;">
            <img src="/assets/marinelitter.jpg" />
            <p style="padding-bottom: 3em;">Dublin, Ireland.</p>
            <h1 class="title is-1" style="color: black; text-align: center;">
                <strong style="color: red;">{{ $t('about.about302') }}</strong>
            </h1>
            <img src="/assets/microplastics_oranmore.JPG" />
            <p>Microplastics in Oranmore, Co. Galway.</p>
        </div>

        <div class="container">
            <h1 class="title is-1">
                {{ $t('about.about31') }}
            </h1>
            <br>
            <br>
        </div>

        <img src="/assets/urban.JPG" style="padding-bottom: 1em;">
        <p style="padding-bottom: 5em; text-align: center;">Penrose Wharf, Cork City, Ireland (above)</p>

        <div style="text-align: center;">
            <img src="/assets/IMG_0554.JPG" style="padding-bottom: 2em;">
            <p style="padding-bottom: 5em; text-align: center;">Accra, Capital of Ghana, North-Western Coast of Africa <a href="https://www.facebook.com/nshorena/posts/1652239435009949">more photos on facebook</a></p>
            <img src="/assets/IMG_0556.JPG" style="padding-bottom: 2em;">
            <p style="padding-bottom: 5em;">{{ $t('about.about32') }}</p>
        </div>

        <div class="container">
            <h1 class="title is-1 has-text-left">
                {{ $t('about.about33') }}
            </h1>
        </div>
        <br>
        <br>
        <div class="container">
            <h1 class="title is-1 has-text-right">
                {{ $t('about.about34') }}
            </h1>
            <br>
            <div class="has-text-centered" style="padding-top: 2em;">
                <form action="/signup">
                    <button class="button is-large is-primary hov">{{ $t('about.about35') }}</button>
                </form>
            </div>
            <br>
            <br>
        </div>

        <footer class="footer">
            <div class="container">

                <div class="content has-text-centered">
                    <p>
                        <strong>OpenLitterMap</strong> by <a href="https://ie.linkedin.com/in/seanlynchgis">Seán Lynch, M.Sc, M.Sc., B.A.</a>
                        <br>
                    <p>info@openlittermap.com</p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script>
export default {
    name: 'About'
}
</script>

<style scoped>

    .cig-2 {
        align-items: center;
        display: flex;
        padding: 5em;
    }
</style>
