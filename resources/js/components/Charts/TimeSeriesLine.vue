<script>
import { Line } from 'vue-chartjs'

export default {
    extends: Line,
    name: 'TimeSeriesLine',
    props: ['ppm'],
    data ()
    {
        return {
            months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
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
                        label: 'Verified Photos',
                        backgroundColor: '#8e7fd6',
                        data: values,
                        fill: false,
                        borderColor: '#8e7fd6',
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
                        fontColor: '#8e7fd6'
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
                            fontColor: '#8e7fd6'
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
                            fontColor: '#8e7fd6'
                        },
                    }],
                }
            })
    }
}
</script>
