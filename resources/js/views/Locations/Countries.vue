<template>
    <div>
        <section class="hero is-link is-bold">
            <section class="section is-link is-bold">

                <h3 class="title is-2 has-text-centered">Join us and share data!</h3>

                <TotalCounts />

                <!-- Global Leaderboard -->
                <div class="container">
                    <h3 class="title is-2 has-text-centered">{{ $t('location.maps4') }}</h3>

                    <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

                    <!-- Top-10 Leaderboard-->
                    <global-leaders v-else />
                </div>

                <!-- Progress -->
                <div class="container mt2" v-if="! loading">
                    <div class="columns">
                        <div class="column is-half is-offset-3">
                            <!-- XP bar variables -->
                            <div class="columns">
                                <div class="column flex">
                                    <h4 class="flex-1">
                                        Previous Target:
                                        <br>
                                        <strong style="color: white;">
                                            {{ this.previousXp | commas }} {{ $t('location.maps9') }}
                                        </strong>
                                    </h4>
                                    <h4>{{ $t('location.maps8') }}:
                                        <br>
                                        <strong style="color: white;">
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

                </div>
            </section>
        </section>

        <sort-locations type="country" />
    </div>
</template>

<script>
import TotalCounts from '../../components/global/TotalCounts';
import GlobalLeaders from '../../components/GlobalLeaders'
import ProgressBar from '../../components/ProgressBar'
import SortLocations from './SortLocations'

import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'Countries',
    components: {
        TotalCounts,
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
            littercoinPaid: '2,950' // hard-coded for now
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
            let range = this.nextXp - this.previousXp;

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
    }
}
</script>

<style scoped>

</style>
