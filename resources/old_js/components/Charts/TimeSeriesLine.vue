<script>
import { Line } from 'vue-chartjs'

export default {
    extends: Line,
    name: 'TimeSeriesLine',
    props: ['ppm'],
    data ()
    {
        return {
            months: this.$t('common.short-month-names') //['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
        }
    },
    mounted ()
    {
        // Convert string into array
        let arr = JSON.parse(this.ppm);

        let dates = [];
        let values = [];

        // convert label month to text
        // todo - translate this.months
        for (let k in arr)
        {
            dates.push(this.months[parseInt(k.substring(0,2))-1] + k.substring(2,5));
            values.push(arr[k]);
        }

        this.renderChart({
                labels: dates,
                datasets: [
                    {
                        label: this.$t('profile.dashboard.timeseries-verified-photos'),
                        backgroundColor: '#1DD3B0',
                        data: values,
                        fill: false,
                        borderColor: '#1DD3B0',
                        maxBarThickness: '50'
                    }
                ]
            },
            {
                // options
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    labels: {
                        fontColor: '#1DD3B0'
                    }
                },
                scales: {
                    xAxes:[{
                        gridLines:{
                            color: "rgba(255,255,255,0.5)",
                            display: true,
                            drawBorder: true,
                            drawOnChartArea: false
                        },
                        ticks: {
                            fontColor: '#1DD3B0'
                        },
                    }],
                    yAxes:[{
                        gridLines:{
                            color: "rgba(255,255,255,0.5)",
                            display: true,
                            drawBorder: true,
                            drawOnChartArea: false
                        },
                        ticks: {
                            fontColor: '#1DD3B0'
                        },
                    }],
                }
            })
    }
}
</script>
