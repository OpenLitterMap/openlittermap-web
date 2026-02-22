<template>
    <div class="stats">
        <div class="stat">
            <h1 class="subtitle is-5 has-text-centered">
                <strong class="has-text-black font-800">
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
                <strong class="has-text-black font-800">
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
                <strong class="has-text-black font-800">
                    {{ $t('location.total-littercoin-issued') }}
                </strong>
            </h1>
            <h1 class="title is-2 has-text-centered has-text-white">
                <strong>
                    <span v-if="loading">...</span>

                    <number
                        v-else
                        :from="previous_littercoin"
                        :to="total_littercoin"
                        :duration="3"
                        :delay="1"
                        easing="Power1.easeOut"
                        :format="commas"
                    />
                </strong>
            </h1>
        </div>
    </div>
</template>

<script>
export default {
    name: "TotalGlobalCounts",
    props: [
        'loading'
    ],
    computed: {
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
        },

        /**
         * Total littercoin owed to users for proof of citizen science
         */
        total_littercoin ()
        {
            return this.$store.state.locations.littercoin;
        },
    },
    methods: {
        /**
         * Format number value
         */
        commas (n)
        {
            return parseInt(n).toLocaleString();
        }
    }
}
</script>

<style scoped>

    .stats {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        max-width: 800px;
        margin: auto;
        padding-bottom: 2em;
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