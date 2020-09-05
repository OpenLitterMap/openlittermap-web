<script>
import { Bar } from 'vue-chartjs'

export default {
    extends: Bar,
    name: 'TimeSeries',
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
                    backgroundColor: '#FF0000',
                    data: values,
                    fill: false,
                    borderColor: 'red'
                }
            ]
        },
        {
            // options
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                labels: {
                    fontColor: '#000000'
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
                        fontColor: '#000000'
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
                        fontColor: '#000000',
                    },
                }],
            }
        })
    }
}
</script>
