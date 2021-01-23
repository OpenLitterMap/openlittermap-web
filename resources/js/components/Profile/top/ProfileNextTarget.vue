<template>
    <div class="profile-card">
        <p class="mb1">Coming soon - Next Target / Progress</p>

        <p>You have reached <strong style="color: white;">Level 5</strong></p>
        <p class="mb2">You need 300xp to reach the next level.</p>

        <!-- Change time period -->
        <select v-model="period" @change="changePeriod" class="input" style="width: 10em;">
            <option v-for="time in timePeriods" :value="time">{{ getPeriod(time) }}</option>
        </select>
    </div>
</template>

<script>
export default {
    name: 'ProfileNextTarget',
    data ()
    {
        return {
            period: 'today',
            timePeriods: [
                'today',
                'week',
                'month',
                'year',
                'all'
            ],
        };
    },
    methods: {

        /**
         * Get map data
         */
        async changePeriod ()
        {
            await this.$store.dispatch('GET_USERS_PROFILE_MAP_DATA', this.period);
        },


        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.times.' + period)
        },
    }
};
</script>

<style scoped>

</style>
