<script>
import { Pie } from 'vue-chartjs'

export default Pie.extend({

  props: ['smoking', 'food', 'coffee', 'alcohol', 'softdrinks', 'drugs', 'sanitary', 'other'],

  mounted () {
    // console.log('profile chart vue');
    // console.log(this);

    // get fraction of data 
    var total = parseInt(this.smoking) + parseInt(this.food) + parseInt(this.coffee) + parseInt(this.alcohol) + parseInt(this.softdrinks) + parseInt(this.drugs) + parseInt(this.sanitary) + parseInt(this.other);
    // console.log(total);

    var smokingPercent = parseInt(this.smoking) / total * 100 + "%";
    var foodPercent = parseInt(this.food) / total * 100 + "%";
    var coffeePercent = parseInt(this.coffee) / total * 100 + "%";
    var alcoholPercent = parseInt(this.alcohol) / total * 100 + "%";
    var softdrinksPercent = parseInt(this.softdrinks) / total * 100 + "%";
    var drugsPercent = parseInt(this.drugs) / total * 100 + "%";
    var sanitaryPercent = parseInt(this.sanitary) / total * 100 + "%";
    var otherPercent = parseInt(this.other) / total * 100 + "%";

    var percentArray = [];
    percentArray.push(smokingPercent.slice(0,4)+ "%", foodPercent.slice(0,4)+ "%", coffeePercent.slice(0,4)+ "%", alcoholPercent.slice(0,4)+ "%", softdrinksPercent.slice(0,4)+ "%", drugsPercent.slice(0,4)+ "%", sanitaryPercent.slice(0,4)+ "%", otherPercent.slice(0,4)+ "%");
    // console.log(percentArray);


    // Overwriting base render method with actual data.
    this.renderChart({
      labels: ['Smoking', 'Food', 'Coffee', 'Alcohol', 'SoftDrinks', 'Drugs', 'Sanitary', 'Other'],
      datasets: [
        {
          label: 'Collected',
          backgroundColor: ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE'],
          data: [this.smoking, this.food, this.coffee, this.alcohol, this.softdrinks, this.drugs, this.sanitary, this.other]
        }
      ],
    },

    {


      legend: {
        labels: {
          fontColor: '#ffffff'
        }
      },

      // scales: {
      //   yAxes: [{
      //     ticks: {
      //       stepSize: 1
      //     }
      //   }]
      // },

     // tooltips: {
     //    mode: 'single',  // this is the Chart.js default, no need to set
     //    callbacks: {
     //        label: function (tooltipItems, percentArray) {
     //          console.log(tooltipItems),
     //          console.log(percentArray)
     //        }
     //    }
    // },

    } // end options 
    ) // end render chart 
  } // end mounted 
})
</script>
