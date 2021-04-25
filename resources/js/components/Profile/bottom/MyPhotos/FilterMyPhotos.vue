<template>
    <!-- Filters -->
    <div class="flex mb1">

        <input
            class="input mr1"
            placeholder="Search by ID"
            v-model="filter_by_id"
            @input="search"
        />

        <!-- Calendar -->
        <div :class="calendar">
            <div class="dropdown-trigger">
                <button class="button dropdownButtonLeft" @click="toggleCalendar">
                    <span>{{ showCalendarDates }}</span>
                </button>
            </div>
            <div class="dropdown-menu">
                <div class="dropdown-content calendar-box">
                    <FunctionalCalendar
                        :change-month-function="true"
                        :change-year-function="true"
                        :is-date-range="true"
                        :date-format="'yyyy/mm/dd'"
                        @selectedDaysCount="toggleCalendar"
                        ref="calendar"
                        v-model="filter_by_calendar"
                    />
                </div>
            </div>
        </div>

        <select class="input mr1">
            <option v-for="period in periods" :value="period">{{ getPeriod(period) }}</option>
        </select>

        <select class="input">
            <option selected disabled>Verified</option>
            <option v-for="i in verifiedIndices" :value="i">{{ i }}</option>
        </select>
    </div>
</template>

<script>
import { FunctionalCalendar } from 'vue-functional-calendar';

export default {
    name: 'FilterMyPhotos',
    components: {
        FunctionalCalendar,
    },
    data () {
        return {
            period: 'created_at',
            periods: [
                'created_at',
                'datetime'
            ],
            showCalendar: false,
            verifiedIndices: [
                0,1,2,3,4
            ]
        };
    },
    computed: {

        /**
         * Class to show when calender is open
         */
        calendar ()
        {
            return this.showCalendar
                ? 'dropdown is-active mr1'
                : 'dropdown mr1';
        },

        /**
         * Filter by calendar dates
         */
        filter_by_calendar: {
            get () {
                return this.$store.state.photos.filters.calendar;
            },
            set (v) {
                this.$store.commit('filter_photos_calendar', {
                    min: v.dateRange.start.date,
                    max: v.dateRange.end.date
                });
            }
        },

        /**
         * Filter photos by ID
         */
        filter_by_id: {
            get () {
                return this.$store.state.photos.filters.id;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: id,
                    v
                });
            }
        },

        /**
         * Text to load calendar dropdown,
         *
         * Or if dates exist, show dates.
         */
        showCalendarDates ()
        {
            return 'Show Calendar';
        }
    },
    methods: {

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.times.' + period)
        },

        /**
         * Filter photos by ID
         */
        search ()
        {
            // timeout
            // show spinner
            // dispatch request to filter
        },

        /**
         * Show or Hide the dropdown calendar
         */
        toggleCalendar ()
        {
            this.showCalendar = ! this.showCalendar;
        },
    }
};
</script>

<style scoped>

</style>
