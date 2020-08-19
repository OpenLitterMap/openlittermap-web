<template>
    <div>
        <section class="hero is-fullheight is-link is-bold">
            <section class="section is-link is-bold">

                <!-- We are creating open data -->
                <h1 class="title is-1" style="text-align: center; padding-bottom: 10px;">
                    {{ $t('location.maps1') }}
                </h1>
                <h2 class="subtitle is-4" style="text-align: center;">
                    {{ $t('location.maps2') }}
                </h2>
                <div class="container" style="padding-top: 20px;">
                    <h3 class="title is-4 has-text-centered">{{ $t('location.maps4') }}</h3>

                    <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

                    <!-- Top-10 Leaderboard-->
                    <global-leaders v-else />

                </div>
                <div class="container" v-if="! loading">
                    <div class="columns">
                        <div class="column is-half is-offset-3">
                            <!-- XP bar variables -->
                            <div class="columns">
                                <div class="column flex">
                                    <h4 class="flex-1">
                                        Previous Target:
                                        <br>
                                        <strong>
                                            {{ this.previousXp | commas }} {{ $t('location.maps9') }}
                                        </strong>
                                    </h4>
                                    <h4>{{ $t('location.maps8') }}:
                                        <br>
                                        <strong>
                                            {{ this.nextXp | commas }} {{ $t('location.maps9') }}
                                        </strong>
                                    </h4>
                                </div>
                            </div>

                            <progress-bar
                                v-if="! loading"
                                :currentxp="total_litter"
                                :startingxp="previousXp"
                                :xpneeded="nextXp"
                                class="mb1em"
                            />

                            <p class="has-text-centered mb2em">{{ this.progress }}%</p>
                        </div>
                    </div>

                    <div class="columns">
                        <div class="column is-half is-offset-3">
                            <div class="columns">
                                <div class="column is-one-third">
                                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                                        <strong style="color:black;">
                                            {{ $t('location.maps10') }}
                                        </strong>
                                    </h1>
                                    <h1 class="title is-2" style="text-align: center;"><strong>{{ this.total_litter }}</strong></h1>
                                </div>

                                <div class="column is-one-third">
                                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                                        <strong style="color: black;">
                                            {{ $t('location.maps11') }}
                                        </strong>
                                    </h1>
                                    <h1 class="title is-2" style="text-align: center;"><strong>{{ this.total_photos }}</strong></h1>
                                </div>

                                <div class="column is-one-third">
                                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                                        <strong style="color: black;">
                                            {{ $t('location.maps11a') }}
                                        </strong>
                                    </h1>
                                    <h1 class="title is-2" style="text-align: center;">
                                        <strong>{{ this.littercoinPaid }}</strong>
                                    </h1>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </section>

        <section class="hero is-primary">
            <sort-locations type="country" />
        </section>
    </div>
</template>

<script>
import GlobalLeaders from '../../components/GlobalLeaders'
import ProgressBar from '../../components/ProgressBar'
import SortLocations from './SortLocations'

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'Countries',
    components: {
        GlobalLeaders,
        ProgressBar,
        SortLocations,
        Loading
    },
    async created ()
    {
        this.loading = true;
        await this.$store.dispatch('GET_COUNTRIES');
        this.loading = false;
    },
    data ()
    {
        return {
            loading: true,
            // dummy data for now
            previousLevelInt: 0,
            nextLevelInt: 1,
            progressPercent: 50,
            littercoinPaid: 100,
        };
    },
    computed: {

        /**
         * The amount of XP we need to reach the next level
         */
        nextXp ()
        {
            return this.$store.state.locations.level.nextXp;
        },

        /**
         * The amount of XP we achieved at the current level
         */
        previousXp ()
        {
            return this.$store.state.locations.level.previousXp;
        },

        /**
         * % between currentLevel and nextLevel
         */
        progress ()
        {
            let range = this.nextXp - this.total_litter;

            let startVal = this.total_litter - this.previousXp;

            return ((startVal * 100) / range).toFixed(2); // percentage
        },

        /**
         * The total amount of verified litter all users have uploaded
         */
        total_litter ()
        {
            return this.$store.state.locations.total_litter;
        },

        /**
         * The total number of verified photos all users have uploaded
         */
        total_photos ()
        {
            return this.$store.state.locations.total_photos;
        }
    }
}
</script>

<style scoped>

</style>