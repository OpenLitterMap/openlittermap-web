<template>
    <section class="hero section-stats">
        <div class="hero-body is-flex">
            <div class="stats">
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">
                        <number
                            :from="0"
                            :to="stats.photosPerMonth"
                            :duration="2"
                            :delay="0"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </div>
                    <div class="is-size-5">{{ $t('home.community.photos-last-30-days') }}</div>
                    <p>{{ getUploadsPerMinute }}</p>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">
                        <number
                            :from="0"
                            :to="stats.usersPerMonth"
                            :duration="2"
                            :delay="0"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </div>
                    <div class="is-size-5">{{ $t('home.community.users-last-30-days') }}</div>
                    <p>{{ getNewUsersPerDay }}</p>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">
                        <number
                            :from="0"
                            :to="stats.litterTagsPerMonth"
                            :duration="2"
                            :delay="0"
                            easing="Power1.easeOut"
                            :format="commas"
                        />
                    </div>
                    <div class="is-size-5">{{ $t('home.community.litter-tags-last-30-days') }}</div>
                    <p>{{ getTagsPerMinute }}</p>
                </div>
            </div>
            <div class="charts mt-6">
                <div class="chart">
                    <StatsChart
                        :chart-data="yearlyStats"
                        :options="options"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
<script>
import StatsChart from './StatsChart.js';

export default {
    name: 'StatsSection',
    components: {
        StatsChart
    },
    data () {
        return {
            options: {
                aspectRatio: 3,
                maintainAspectRatio: false,
                legend: {labels: {fontColor: 'whitesmoke'}},
                scales: {
                    xAxes: [{gridLines: {display: false}, ticks: {fontColor: 'whitesmoke'}}],
                    yAxes: [
                        {id: 'photos', type: 'linear', position: 'left', gridLines: {display: false}, ticks: {fontColor: 'whitesmoke'}},
                        {id: 'users', type: 'linear', position: 'right', gridLines: {display: false}, ticks: {fontColor: 'whitesmoke'}}
                    ],
                }
            }
        };
    },
    computed: {
        getUploadsPerMinute () {
            const daily = this.stats.photosPerMonth / 30;
            const hourly = daily / 24;

            return (hourly / 60).toFixed(2) + " per minute";
        },
        getNewUsersPerDay () {
            const daily = this.stats.usersPerMonth / 30;

            return daily.toFixed(2) + " per day";
        },
        getTagsPerMinute () {
            const daily = this.stats.litterTagsPerMonth / 30;
            const hourly = daily / 24;

            return (hourly / 60).toFixed(2) + " per minute";
        },
        stats() {
            return this.$store.state.community;
        },
        yearlyStats() {
            if (!this.stats.statsByMonth) return {};

            return {
                labels: this.stats.statsByMonth.periods,
                datasets: [
                    {
                        label: this.$i18n.t('home.community.photos-every-month-label'),
                        yAxisID: 'photos',
                        borderColor: '#1DD3B0',
                        borderWidth: 3,
                        pointBackgroundColor: '#008080',
                        pointBorderColor: '#008080',
                        backgroundColor: 'transparent',
                        data: this.stats.statsByMonth.photosByMonth
                    },
                    {
                        label: this.$i18n.t('home.community.users-every-month-label'),
                        yAxisID: 'users',
                        borderColor: '#c2f970',
                        borderWidth: 3,
                        pointBackgroundColor: '#008080',
                        pointBorderColor: '#008080',
                        backgroundColor: 'transparent',
                        data: this.stats.statsByMonth.usersByMonth
                    }
                ]
            };
        }
    },
    async mounted ()
    {
        await this.$store.dispatch('GET_STATS');
    },
    methods: {
        /**
         * Format number value
         */
        commas (n)
        {
            return parseInt(n).toLocaleString();
        }
    }
};
</script>
<style lang="scss" scoped>
.hero-body {
    flex-direction: column;
}

.section-stats {
    background-color: #111827;

    .stats {
        width: 100%;
        display: grid;
        grid-gap: 3rem;
        grid-template-columns: repeat(auto-fit, minmax(250px, 2fr));

        .stat {
            background-color: transparent;

            .total {
                font-size: 5rem;
            }

            &:hover {
                transform: scale(1.05);
            }
        }
    }

    .charts {
        width: 100%;
        background-color: #111827;
        border-radius: 1rem;
        overflow: hidden;

        .chart {
            padding: 0.25rem;
        }
    }
}

@media screen and (min-width: 1280px) {
    .section-stats {
        .stats {
            max-width: 1000px;
            margin: auto;
            justify-content: center;
        }

        .charts {
            max-width: 1000px;
            margin: auto;
            justify-content: center;

            .chart {
                padding: 1rem;
            }
        }
    }
}

</style>
