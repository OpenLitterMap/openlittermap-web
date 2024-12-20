<template>
    <div class="profile-card">
       <TimeSeriesLine :ppm="ppm" />
    </div>
</template>

<script>
import TimeSeriesLine from '../../Charts/TimeSeriesLine.vue';

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

</style>
