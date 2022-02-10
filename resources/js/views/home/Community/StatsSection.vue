<template>
    <section class="hero section-stats">
        <div class="hero-body is-flex">
            <div class="stats">
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">75</div>
                    <div class="is-size-5">photos uploaded per day</div>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">42</div>
                    <div class="is-size-5">new users per week</div>
                </div>
                <div class="stat has-text-light has-text-centered">
                    <div class="total has-text-weight-bold">99</div>
                    <div class="is-size-5">Littercoin mined per month</div>
                </div>
            </div>
            <div class="charts mt-6">
                <div class="chart">
                    <StatsChart :chart-data="yearlyStats" :options="options" />
                </div>
            </div>
        </div>
    </section>
</template>
<script>
import StatsChart from './StatsChart.js';

export default {
    name: 'StatsSection',
    components: {StatsChart},
    data ()
    {
        return {
            yearlyStats: {},
            options: {
                aspectRatio: 3,
                maintainAspectRatio: false,
                legend: {labels: {fontColor: 'whitesmoke'}},
                scales: {
                    xAxes: [{gridLines: {display: false}, ticks: {fontColor: 'whitesmoke'}}],
                    yAxes: [{gridLines: {display: false}, ticks: {fontColor: 'whitesmoke'}}],
                }
            }
        };
    },
    mounted ()
    {
        this.fillData();
    },
    methods: {
        fillData ()
        {
            this.yearlyStats = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Photos uploaded every month',
                        borderColor: '#1DD3B0',
                        borderWidth: 3,
                        pointBackgroundColor: '#008080',
                        pointBorderColor: '#008080',
                        backgroundColor: 'transparent',
                        data: [...Array(12)].map(e => Math.random() * 100 | 0)
                    },
                    {
                        label: 'New users every month',
                        borderColor: '#c2f970',
                        borderWidth: 3,
                        pointBackgroundColor: '#008080',
                        pointBorderColor: '#008080',
                        backgroundColor: 'transparent',
                        data: [...Array(12)].map(e => Math.random() * 100 | 0)
                    }
                ]
            };
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
