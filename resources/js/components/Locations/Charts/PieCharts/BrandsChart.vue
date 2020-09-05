<script>
import { Doughnut } from 'vue-chartjs'

export default {
    extends: Doughnut,
    name: 'BrandsChart',
    props: ['brands'],
    data ()
    {
        return {
            myArray: [],
            top10keys: [],
            top10values: []
        };
    },
    mounted ()
    {
        // Step 1 - Map object to array
        Object.keys(this.brands).map((key, index) => {
            if (this.brands[key])
            {
                this.myArray.push({ key: key, value: this.brands[key] });
            }
        });

        // Step 2 - Sort Array ( todo - stop at 10 )
        this.myArray.sort(function(a, b)
        {
            return b.value - a.value;
        });

        for (var x in this.myArray)
        {
            if (x < 9)
            {
                if (this.myArray[x].value > 0)
                {
                    this.top10keys.push(this.myArray[x]["key"]);
                    this.top10values.push(this.myArray[x]["value"]);
                }
            }
        }

        // Overwriting base render method with actual data.
        this.renderChart({
            labels: this.top10keys,
            datasets: [
                {
                    label: 'Collected',
                    backgroundColor: this.myComputedBackgrounds,
                    data: this.top10values
                }
            ],
        },

        {
            responsive: false,
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
        })
    },

    computed: {

        /**
         * Refactor this.
         */
        myComputedBackgrounds ()
        {
            if (this.top10keys.length == 0) return ['#C28535'];

            if (this.top10keys.length == 1) return ['#C28535', '#8AAE56'];

            if (this.top10keys.length == 2) return ['#C28535', '#8AAE56', '#B66C46'];

            if (this.top10keys.length == 3) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741'];

            if (this.top10keys.length == 4) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000'];

            if (this.top10keys.length == 5) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6'];

            if (this.top10keys.length == 6) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF'];

            if (this.top10keys.length == 7) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE'];

            if (this.top10keys.length == 8) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE', '#ccc'];

            if (this.top10keys.length == 9) return ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE', '#000000'];
        }
  }
}
</script>
