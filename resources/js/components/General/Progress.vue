<template>
    <div class="container mt2 progress-bar">
        <div>
            <!-- XP bar variables -->
            <div class="flex mb1">
                <h4 class="flex-1 has-text-white">
                    {{ $t('location.previous-target') }}:
                    <br>
                    <strong class="has-text-white">
                        {{ this.previousXp | commas }} {{ $t('location.litter') }}
                    </strong>
                </h4>
                <h4 class="has-text-white">{{ $t('location.next-target') }}:
                    <br>
                    <strong class="has-text-white">
                        {{ this.nextXp | commas }} {{ $t('location.litter') }}
                    </strong>
                </h4>
            </div>

            <ProgressBar
                :currentxp="total_litter"
                :startingxp="previousXp"
                :xpneeded="nextXp"
                class="mb1em"
            />

            <p
                v-if="loading"
                class="has-text-centered has-text-white mb2"
            >...%</p>

            <p
                v-else
                class="has-text-centered has-text-white mb2"
            >{{ this.progress }}%</p>
        </div>

        <div class="stats">
            <div class="stat">
                <h1 class="subtitle is-5 has-text-centered">
                    <strong class="has-text-black">
                        {{ $t('location.total-verified-litter') }}
                    </strong>
                </h1>
                <h1 class="title is-2 has-text-centered has-text-white">
                    <strong>
                        <span v-if="loading">...</span>

                        <number
                            v-else
                            :from="previous_total_litter"
                            :to="total_litter"
                            :duration="3"
                            :delay="1"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </strong>
                </h1>
            </div>

            <div class="stat">
                <h1 class="subtitle is-5 has-text-centered">
                    <strong class="has-text-black">
                        {{ $t('location.total-verified-photos') }}
                    </strong>
                </h1>
                <h1 class="title is-2 has-text-centered has-text-white">
                    <strong>
                        <span v-if="loading">...</span>

                        <number
                            v-else
                            :from="previous_total_photos"
                            :to="total_photos"
                            :duration="3"
                            :delay="1"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </strong>
                </h1>
            </div>

            <div class="stat">
                <h1 class="subtitle is-5 has-text-centered">
                    <strong class="has-text-black">
                        {{ $t('location.total-littercoin-issued') }}
                    </strong>
                </h1>
                <h1 class="title is-2 has-text-centered has-text-white">
                    <strong>
                        <span v-if="loading">...</span>

                        <number
                            v-else
                            :from="previous_littercoin"
                            :to="littercoin"
                            :duration="3"
                            :delay="1"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </strong>
                </h1>
            </div>
        </div>
    </div>
</template>

<script>
import ProgressBar from '../ProgressBar';

export default {
    name: "Progress",
    props: [
        'loading'
    ],
    components: {
        ProgressBar
    },
    methods: {
        /**
         * Format number value
         */
        commas (n)
        {
            return parseInt(n).toLocaleString();
        }
    },
    computed: {
        /**
         * Total littercoin owed to users for proof of citizen science
         */
        littercoin ()
        {
            return this.$store.state.locations.littercoin;
        },

        /**
         * The amount of XP we achieved at the current level
         */
        previousXp ()
        {
            return this.$store.state.locations.level.previousXp;
        },

        /**
         * The amount of XP we need to reach the next level
         */
        nextXp ()
        {
            return this.$store.state.locations.level.nextXp;
        },

        /**
         * The total amount of verified litter all users have uploaded
         */
        total_litter ()
        {
            return this.$store.state.locations.total_litter;
        },

        /**
         * The last littercoin the user has seen (saved in browser cache)
         *
         * Update to latest value once called
         */
        previous_littercoin ()
        {
            let littercoin = 0;

            if (this.$localStorage.get('littercoin_owed'))
            {
                littercoin = this.$localStorage.get('littercoin_owed');
            }

            this.$localStorage.set('littercoin_owed', this.littercoin);

            return littercoin;
        },

        /**
         * The last total_litter the user has seen (saved in browser cache)
         * Update to latest value once called
         */
        previous_total_litter ()
        {
            let prev_total = 0;

            if (this.$localStorage.get('total_litter'))
            {
                prev_total = this.$localStorage.get('total_litter');
            }

            this.$localStorage.set('total_litter', this.total_litter);

            return prev_total;
        },

        /**
         * The last total_photos the user has seen (saved in browser cache)
         * Update to latest value once called
         */
        previous_total_photos ()
        {
            let prev_photos = 0;

            if (this.$localStorage.get('total_photos'))
            {
                prev_photos = this.$localStorage.get('total_photos');
            }

            this.$localStorage.set('total_photos', this.total_photos);

            return prev_photos;
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
    .progress-bar {
        max-width: 600px;
    }
    .stats {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .stat {
        padding: 12px;
        flex: 1;
    }

    @media screen and (min-width: 768px) {
        .stats {
            flex-direction: row;
        }
    }
</style>
