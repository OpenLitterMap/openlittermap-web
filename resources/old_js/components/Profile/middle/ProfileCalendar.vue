<template>
    <div class="profile-card">
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
        <select v-model="period" class="input mt1 mb1">
            <option v-for="time in periods" :value="time">{{ getPeriod(time) }}</option>
        </select>

        <button :class="button" @click="changePeriod" :disabled="disabled">{{ $t('profile.dashboard.calendar-load-data') }}</button>
    </div>
</template>

<script>
import { FunctionalCalendar } from 'vue-functional-calendar';

export default {
    name: 'ProfileCalendar',
    components: { FunctionalCalendar },
    data ()
    {
        return {
            btn: 'button is-primary is-fullwidth',
            calendarData: {},
            period: 'created_at',
            periods: [
                'created_at',
                'datetime'
            ]
        }
    },
    computed: {

        /**
         * Add spinner when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

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

            await this.$store.dispatch('GET_USERS_PROFILE_MAP_DATA', {
                period: this.period,
                start: this.calendarData.dateRange.start,
                end: this.calendarData.dateRange.end,
            });
        },

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.dashboard.times.' + period)
        },
    }
};
</script>

<style scoped></style>
