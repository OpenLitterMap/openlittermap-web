<template>
    <!-- Filters -->
    <div class="flex mb1">

        <div class="field flex-1 mb0 pt0 mr1">
            <div class="control has-icons-left">
                <input
                    class="input w10"
                    placeholder="Search by ID"
                    v-model="filter_by_id"
                    @input="search"
                />

                <span class="icon is-small is-left z-index-0">
                    <i :class="spinner" />
                </span>
            </div>
        </div>

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

        <select class="input mr1" v-model="period" @change="getPhotos">
            <option v-for="time in periods" :value="time">{{ getPeriod(time) }}</option>
        </select>

        <select class="input" v-model="verifiedIndex" @change="getPhotos">
            <option selected disabled :value="null">Verified</option>
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
            periods: [
                'created_at',
                'datetime'
            ],
            processing: false,
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
                    min: v.dateRange.start,
                    max: v.dateRange.end
                });

                if (v.dateRange.end) this.getPhotos();
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
                    key: 'id',
                    v
                });
            }
        },

        /**
         * Filter the photos by created_at or datetime
         *
         * Created_at = when the photo was uploaded
         * Datetime = when the photo was taken
         */
        period: {
            get () {
                return this.$store.state.photos.filters.period;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: 'period',
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
        },

        /**
         * Animate the spinner when searching by id
         */
        spinner ()
        {
            return this.processing
                ? 'fa fa-refresh fa-spin'
                : 'fa fa-refresh';
        },

        /**
         * Stage of verification to filter photos by
         */
        verifiedIndex: {
            get () {
                return this.$store.state.photos.filters.verified;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: 'verified',
                    v
                });
            }
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
         * Return the users photos based on the current filters
         */
        async getPhotos ()
        {
            await this.$store.dispatch('GET_USERS_FILTERED_PHOTOS');
        },

        /**
         * Filter photos by ID
         */
        search ()
        {
            this.processing = true;

            if (this.timeout) clearTimeout(this.timeout);

            this.timeout = setTimeout(async () => {

                await this.getPhotos();

                this.processing = false;

            }, 500);
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
