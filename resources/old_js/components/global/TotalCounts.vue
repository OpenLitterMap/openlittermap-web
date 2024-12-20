<template>
    <div class="columns mb2">
        <div class="column is-half is-offset-3">
            <div class="columns">
                <div class="column is-one-third">
                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                        <strong style="color:black;">
                            {{ $t('location.maps10') }}
                        </strong>
                    </h1>
                    <h1 class="title is-2" style="text-align: center;">
                        <strong>
                            <number
                                :from="this.prev_total"
                                :to="total_litter"
                                :duration="3"
                                :delay="3"
                                easing="Power1.easeOut"
                                :format="commas"
                            />
                        </strong>
                    </h1>
                </div>

                <div class="column is-one-third">
                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                        <strong style="color: black;">
                            {{ $t('location.maps11') }}
                        </strong>
                    </h1>
                    <h1 class="title is-2" style="text-align: center;">
                        <strong>
                            <number
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

                <div class="column is-one-third">
                    <h1 class="subtitle is-5" style="color: black; text-align: center;">
                        <strong style="color: black;">
                            {{ $t('location.maps11a') }}
                        </strong>
                    </h1>
                    <h1 class="title is-2" style="text-align: center;">
                        <strong>
                            {{ this.littercoinPaid }}
                        </strong>
                    </h1>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TotalCounts',
    props: [
        'prev_total'
    ],
    data ()
    {
        return {
            littercoinPaid: '2,950' // hard-coded for now
        };
    },
    computed: {


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
        }
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
};
</script>

<style scoped>

</style>
