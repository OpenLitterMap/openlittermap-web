<template>
    <div class="profile-card">
        <p class="profile-dl-title">{{ $t('profile.dashboard.download-data') }}</p>

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

        <div class="inputs-wrapper">
            <select v-model="period" class="input mt1 mb1">
                <option v-for="time in periods" :value="time">{{ $t('teams.dashboard.times.' + time) }}</option>
            </select>

            <button :class="button" @click="download" :disabled="processing">
                <span class="tooltip-text is-size-6">{{ $t('profile.dashboard.email-send-msg') }}</span>
                {{ $t('common.download') }}
            </button>
        </div>
    </div>
</template>

<script>
import { FunctionalCalendar } from 'vue-functional-calendar';

export default {
    name: 'ProfileDownload',
    components: { FunctionalCalendar },
    data ()
    {
        return {
            btn: 'button tooltip is-primary',
            processing: false,
            calendarData: {},
            period: 'created_at',
            periods: [
                'created_at',
                'datetime'
            ]
        };
    },
    computed: {

        /**
         * Add spinner when processing
         */
        button () {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        }
    },
    methods: {

        /**
         * Dispatch a request to download a users data
         */
        async download ()
        {
            this.processing = true;

            await this.$store.dispatch('DOWNLOAD_MY_DATA', {
                dateField: this.period,
                fromDate: this.calendarData?.dateRange?.start,
                toDate: this.calendarData?.dateRange?.end,
            });

            this.processing = false;
        }
    }
};
</script>

<style scoped>

    .profile-dl-title {
        color: #1DD3B0;
        margin-bottom: 1em;
        font-weight: 600;
    }

    .profile-dl-subtitle {
        color: #1DD3B0;
        margin-bottom: 1em;
    }

    .inputs-wrapper {
        display: flex;
        gap: 1rem;
        align-items: center;
    }
</style>
