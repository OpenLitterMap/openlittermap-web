<script>
import { Line } from 'vue-chartjs'

export default Line.extend({

  props: ['data'],

  mounted () {
    // Convert string into array
    // console.log("String:" + " " + this.data);
    var arr = JSON.parse(this.data);

    //            0     1     2      3       4      5      6      7      8      9      10    11      12
    var months = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

    // console.log(arr);

    var dates = [];
    var values = [];

    // for(var i=0; i<Object.keys(arr).length; i++) {
    //   dates[i] = months[parseInt(Object.keys(arr)[i].substr(0,2))] + Object.keys(arr)[i].substr(2, 5),
    //   values[i] = Object.values(arr)[i]
    // }

    for (var k in arr) {
      // console.log(k);
      dates.push(months[parseInt(k.substr(0,2))] + k.substr(2,5));
      values.push(arr[k]);
    }

    // console.log(dates);
    // console.log(values);

    // Overwriting base render method with actual data.
    this.renderChart({
      labels: dates,
      datasets: [
        {
          label: 'Photos per month',
          backgroundColor: '#FF0000',
          data: values,
          fill: false,
          borderColor: 'red'
        }
      ]
    },

    {
      // options

      title: {
        display: true,
        text: 'Verified Monthly Photos You Uploaded',
        fontColor: '#000000'
      },

      legend: {
        position: 'bottom',
        labels: {
          fontColor: '#ffffff',
        }
      },    

      scales: {
        xAxes:[{
          gridLines:{
            color:"rgba(255,255,255,0.5)"
          },
            ticks: {
              fontColor: '#ffffff'
            },
        }],
        yAxes:[{
          gridLines:{
            color:"rgba(255,255,255,0.5)",
          },
          ticks: {
            fontColor: '#ffffff',
            stepSize: 10,
          },
        }],
      },

    }, // end options
    ) // end render Chart 
  }, // end mounted 



});
</script>
