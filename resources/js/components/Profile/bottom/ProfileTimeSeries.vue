<template>
    <div class="profile-time-series">
       <TimeSeriesLine
           :ppm="ppm"
       />
    </div>
</template>

<script>
import TimeSeriesLine from '../../Charts/TimeSeriesLine';

export default {
    name: 'ProfileTimeSeries',
    components: { TimeSeriesLine },
    computed: {

        /**
         * The users photos per month, as a string. Saved as metadata because CPU.
         */
        ppm ()
        {
            return this.$store.state.user.user.photos_per_month;
        }
    }
};
</script>

<style scoped>

    .profile-time-series {
        flex: 1;
        padding: 3em;
    }
</style>
