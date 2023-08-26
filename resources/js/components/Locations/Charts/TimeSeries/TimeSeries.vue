<script>
import { Bar } from 'vue-chartjs';

export default {
    extends: Bar,
    name: 'TimeSeries',
    props: ['ppm'],
    data() {
        return {
            // todo - translate this.months
            months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        };
    },
    mounted() {
        this.updateChart();
    },
    methods: {
        updateChart() {
            if (!this.ppm) {
                return; // Don't update the chart if ppm is not set yet
            }

            let dates = [];
            let values = [];

            // Convert label month to text
            for (let date in this.ppm) {
                dates.push(this.months[parseInt(date.substring(0, 2)) - 1] + date.substring(2, 5));
                values.push(this.ppm[date]);
            }

            this.renderChart(
                {
                    labels: dates,
                    datasets: [
                        {
                            label: 'Verified Photos',
                            backgroundColor: '#FF0000',
                            data: values,
                            fill: false,
                            borderColor: 'red',
                            maxBarThickness: '50',
                        },
                    ],
                },
                {
                    // options
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: {
                        labels: {
                            fontColor: '#000000',
                        },
                    },
                    scales: {
                        xAxes: [
                            {
                                gridLines: {
                                    color: 'rgba(255,255,255,0.5)',
                                    display: true,
                                    drawBorder: true,
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    fontColor: '#000000',
                                },
                            },
                        ],
                        yAxes: [
                            {
                                gridLines: {
                                    color: 'rgba(255,255,255,0.5)',
                                    display: true,
                                    drawBorder: true,
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    fontColor: '#000000',
                                },
                            },
                        ],
                    },
                }
            );
        },
    },
    watch: {
        ppm(newVal) {
            this.updateChart();
        },
    },
};
</script>
