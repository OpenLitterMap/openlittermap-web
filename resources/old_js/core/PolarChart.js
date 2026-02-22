import { PolarArea, mixins } from 'vue-chartjs'
// reactiveProp and reactiveData are available mixins
const { reactiveProp } = mixins

// reactive prop
// extends the logic of the chart component 
// creates prop name chartData and adds watch on it 
// if data is changed it will call update()
// if new dataset is added it will call renderChart()


// might need this
// reactive data 
// creates a local chart var which is not a prop
// Adds a watcher
// useful if you need to make api calls within single purpose charts 

export default PolarArea.extend({

  // reactive data
  // data() { 
  // return { chartData: null }
  //}

  mixins: [reactiveProp],
  props: ['chartItems','options'],
  mounted () {
    // this.chartData is created in the mixin
    // you can pass renderChart data and options objects
    // data: { labels: [], datasets: { label: '', backgroundColor: '', data: []}}
    this.renderChart(this.chartData, this.options)
  }
})
