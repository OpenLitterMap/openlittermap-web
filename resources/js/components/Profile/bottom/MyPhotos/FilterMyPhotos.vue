<template>
    <!-- Filters -->
    <div class="flex mb1 filter-my-photos">

        <router-link to="/tag">
            <button class="button is-primary">Tag individually</button>
        </router-link>

        <div class="field mb0 pt0">
            <div class="control has-icons-left">
                <input
                    class="input w10"
                    :placeholder="$t('common.search-by-id')"
                    v-model="filter_by_id"
                    @input="search"
                />

                <span class="icon is-small is-left z-index-0">
                    <i :class="spinner" />
                </span>
            </div>
        </div>

        <button class="button is-primary select-all-photos" @click="toggleAll">
            {{ getSelectAllText }}
        </button>

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
                        :day-names="$t('common.day-names')"
                        :month-names="$t('common.month-names')"
                        :short-month-names="$t('common.short-month-names')"
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

        <div>
            <select class="input" v-model="period" @change="getPhotos">
                <option v-for="time in periods" :value="time">{{ getPeriod(time) }}</option>
            </select>
        </div>

        <!--        <select class="input" v-model="verifiedIndex" @change="getPhotos">-->
        <!--            <option disabled :value="null">Verification</option>-->
        <!--            <option v-for="i in verifiedIndices" :value="i">{{ getVerifiedText(i) }}</option>-->
        <!--        </select>-->
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
            // verifiedIndices: [
            //     0,1
            // ]
        };
    },
    computed: {

        /**
         * Class to show when calender is open
         */
        calendar ()
        {
            return this.showCalendar
                ? 'dropdown is-active'
                : 'dropdown';
        },

        /**
         * Shortcut to filters object
         */
        filters ()
        {
            return this.$store.state.photos.filters;
        },

        /**
         * Filter by calendar dates
         */
        filter_by_calendar: {
            get () {
                return this.filters.calendar;
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
                return this.filters.id;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: 'id',
                    v
                });
            }
        },

        /**
         *
         */
        getSelectAllText ()
        {
            return this.selectAll
                ? this.$t('common.de-select-all')
                : this.$t('common.select-all');
        },

        /**
         * Filter the photos by created_at or datetime
         *
         * Created_at = when the photo was uploaded
         * Datetime = when the photo was taken
         */
        period: {
            get () {
                return this.filters.period;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: 'period',
                    v
                });
            }
        },

        /**
         * Toggle the selected-all checkbox, and all photo.selected values
         */
        selectAll: {
            get () {
                return this.$store.state.photos.selectAll;
            },
            set (v) {
                this.$store.commit('selectAllPhotos', v);
            }
        },

        /**
         * Text to load calendar dropdown,
         *
         * Or if dates exist, show dates.
         */
        showCalendarDates ()
        {
            return (this.filters.dateRange.start && this.filters.dateRange.end)
                ? `${this.filters.dateRange.start} - ${this.filters.dateRange.end}`
                : this.$t('common.choose-dates');
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
                return this.filters.verified;
            },
            set (v) {
                this.$store.commit('filter_photos', {
                    key: 'verified',
                    v
                });
            }
        },
    },
    methods: {

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.dashboard.times.' + period)
        },

        /**
         * Return the users photos based on the current filters
         */
        async getPhotos ()
        {
            await this.$store.dispatch('GET_USERS_FILTERED_PHOTOS');
        },

        /**
         *
         */
        getVerifiedText (i)
        {
            return (i === 0)
                ? this.$t('common.not-verified')
                : this.$t('common.verified');
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
         *
         */
        toggleAll ()
        {
            this.$store.commit('selectAllPhotos', this.selectAll);
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

    .select-all-photos {
        min-width: 9em;
    }

    .filter-my-photos {
        flex-direction: column;
        gap: 8px
    }

    /* Laptop and above */
    @media (min-width: 1027px)
    {
        .filter-my-photos {
            flex-direction: row;
            gap: 16px;
        }
    }

</style>
