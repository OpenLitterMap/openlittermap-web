<template name="my-cities">
	<select v-model="theCity" @change="changedCity">
		<option v-for="city in this.cities">
			{{ city.city }}
		</option>
	</select>
</template>

<script>
	export default {

		props: ['cities', 'newcityid', 'stateid'],

		data() {
			return {
				theCity: '',
				myNewCityId: ''
			}
		},

		created() {
			console.log('hello cities');
			this.theCity = this.cities[0].city; 
			this.changedCity();
		},

		methods: {
			changedCity() {
				// emit new city id 
				console.log('changed city');
				var firstState = true;
				for(var z=0; z<this.cities.length; z++) {
					if(this.cities[z].city == this.theCity) {
						this.myNewCityId = this.cities[z].id;
					}
				}
				console.log(this.myNewCityId);
				this.$emit('cityupdated', this.myNewCityId);

			}
		},

		watch: {

			cities: function() {
				this.theCity = this.cities[0].city;
			},

			newcityid: function() {
				this.myNewCityId = this.newcityid;
			}
		}
	}
</script>