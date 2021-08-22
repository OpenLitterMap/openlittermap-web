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
    components: {
        TimeSeriesLine
    },
    props: [
        'ppm'
    ]
};
</script>

<style scoped>

    .profile-time-series {
        flex: 1;
        padding: 3em;
    }
</style>
