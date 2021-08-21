<template>
    <div class="profile-calendar">
        <FunctionalCalendar
            v-model="calendarData"
            :day-names="$t('common.day-names')"
            :month-names="$t('common.month-names')"
            :short-month-names="$t('common.short-month-names')"
            :sundayStart="false"
            :date-format="'yyyy-mm-dd'"
            :is-date-range="true"
            :is-date-picker="false"
            :change-month-function="true"
            :change-year-function="true"
        />

        <!-- Change time period -->
        <select v-model="period" class="input profile-calendar-select">
            <option v-for="time in periods" :value="time">{{ getPeriod(time) }}</option>
        </select>

        <br>

        <button
            :class="processing ? 'is-loading' : ''"
            class="button"
            @click="changePeriod"
            :disabled="disabled"
        >{{ $t('profile.dashboard.calendar-load-data') }}</button>
    </div>
</template>

<script>
import { FunctionalCalendar } from 'vue-functional-calendar';

export default {
    name: 'ProfileCalendar',
    components: {
        FunctionalCalendar
    },
    data ()
    {
        return {
            calendarData: {},
            period: 'created_at',
            periods: [
                'created_at',
                'datetime'
            ],
            processing: false
        }
    },
    computed: {
        /**
         * Return true to disable the button
         */
        disabled ()
        {
            if (this.processing) return true;

            if (!this.calendarData.hasOwnProperty('dateRange')) return true;

            if (!this.calendarData.dateRange.hasOwnProperty('start') && !this.calendarData.dateRange.hasOwnProperty('end')) return true;

            return false;
        }
    },
    methods: {
        /**
         * Get map data
         */
        async changePeriod ()
        {
            if (this.disabled) return;

            this.processing = true;

            await this.$store.dispatch('GET_USERS_PROFILE_MAP_DATA', {
                period: this.period,
                start: this.calendarData.dateRange.start,
                end: this.calendarData.dateRange.end,
            });

            this.processing = false;
        },

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (!period) period = this.period;

            return this.$t('teams.dashboard.times.' + period)
        },
    }
};
</script>

<style scoped>

    .profile-calendar {
        flex: 1;
        padding-top: 5em;
    }

    .profile-calendar-select {
        margin: 1em 0;
        width: 21em;
    }

</style>
