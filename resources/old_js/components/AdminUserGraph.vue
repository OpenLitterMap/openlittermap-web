<script>
	import { Line } from 'vue-chartjs'

	export default Line.extend({
		props: ['upm', 'uupm'],
		data() {
			return {
				MyObject: {},
				dates: [],
				values: []
			}
		},
		mounted() {
			console.log("Admin user graph!!!!");
			console.log(this.upm);

    		var arr = JSON.parse(this.upm);
    		console.log(arr);

    		var mydates = [];
    		var myvalues = [];

    		var hasUploaded = JSON.parse(this.uupm);
    		console.log(hasUploaded);
    		var hasUploadedDates = [];
    		var hasUploadedValues = [];

		    for (var k in arr) {
		      	// console.log(k);
		      	mydates.push(k);
		      	myvalues.push(arr[k]);
		    }

		    for (var n in hasUploaded) {
		    	hasUploadedDates.push(k);
		    	hasUploadedValues.push(hasUploaded[n]);
		    }

			this.renderChart({
		      labels: mydates,
		      datasets: [
		        {
		        	label: 'Users who have uploaded',
		        	backgroundColor: '#0000FF',
		        	data: hasUploadedValues,
		        	fill: false,
		        	borderColor: 'blue'
		        },
		        {
		          label: 'Verified Users per month-year',
		          backgroundColor: '#FF0000',
		          data: myvalues,
		          fill: false,
		          borderColor: 'red'
		        }
		      ]
		    },

		    {
		      // options
		      responsive: false,
		      maintainAspectRatio: false,

		      legend: {
		        labels: {
		          fontColor: '#000000'
		        }
		      },    

		      scales: {
		        xAxes:[{
		          gridLines:{
		            color:"rgba(255,255,255,0.5)"
		          },
		            ticks: {
		              fontColor: '#000000'
		            },
		        }],
		        yAxes:[{
		          gridLines:{
		            color:"rgba(255,255,255,0.5)",
		          },
		          ticks: {
		            fontColor: '#000000'
		          },
		        }],
		      },

		    }, // end options
		    ) // end render Chart 

		},
		computed: {

		}

	});
</script>