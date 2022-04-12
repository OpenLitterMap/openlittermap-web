<script>
import { Pie } from 'vue-chartjs'

export default Pie.extend({

  props: ['ordnance', 'military_equipment_or_weaponry', 'military_personnel'],

  mounted () {
    // console.log('profile chart vue');
    // console.log(this);

    // get fraction of data
    var total = parseInt(this.ordnance) + parseInt(this.military_equipment_or_weaponry) + parseInt(this.military_personnel);
    // console.log(total);

    var ordnancePercent = parseInt(this.ordnance) / total * 100 + "%";
    var militaryEquipmentPercent = parseInt(this.military_equipment_or_weaponry) / total * 100 + "%";
    var militaryPersonnelPercent = parseInt(this.military_personnel) / total * 100 + "%";
    var percentArray = [];
    percentArray.push(
        ordnancePercent.slice(0,4)+ "%",
        militaryEquipmentPercent.slice(0,4)+ "%",
        militaryPersonnelPercent.slice(0,4)+ "%",
    );
    // console.log(percentArray);


    // Overwriting base render method with actual data.
    this.renderChart({
      labels: ['Ordnance', 'Military equipment or weaponry', 'Military personnel'],
      datasets: [
        {
          label: 'Collected',
          backgroundColor: ['#C28535', '#8AAE56', '#B66C46', '#EAE741', '#FF0000', '#BFE5A6', '#FFFFFF', '#BF00FE'],
          data: [this.ordnance, this.military_equipment_or_weaponry, this.military_personnel]
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
