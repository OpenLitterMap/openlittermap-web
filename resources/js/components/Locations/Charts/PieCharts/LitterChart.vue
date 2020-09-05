<script>
import { Doughnut } from 'vue-chartjs'

export default {
    extends: Doughnut,
    name: 'LitterChart',
    props: ['litter'],
    data ()
    {
        return {
            litterData: [],
            litterValues: [],
            colors: [
                '#C28535',
                '#8AAE56',
                '#B66C46',
                '#EAE741',
                '#BFE5A6',
                '#FFFFFF',
                '#BF00FE',
                '#add8e6'
            ]
        };
    },
    mounted ()
    {
        Object.keys(this.litter).map(key => {
            // if value exists, then push
            if (this.litter[key])
            {
                this.litterData.push(key);
                this.litterValues.push(this.litter[key]);
            }
        });

        this.renderChart({
            labels: this.litterData,
            datasets: [
                {
                    label: 'Collected',
                    backgroundColor: this.litterValues.map((key, index) => {
                        return this.colors[index];
                    }),
                    data: this.litterValues
                }
            ],
        },

        // options
        {
            responsive: true,
            maintainAspectRatio: true,
            legend: {
                labels: {
                    fontColor: '#ffffff'
                }
            },
            //  tooltips: {
            //     mode: 'single',  // this is the Chart.js default, no need to set
            //     callbacks: {
            //         label: function (tooltipItems, percentArray) {
            //           console.log(tooltipItems),
            //           console.log(percentArray)
            //         }
            //     }
            // },
        }
    )}
}
</script>
