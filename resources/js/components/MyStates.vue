<template name="my-states">
	<select @change="changedState" v-model="theState">
		<option v-for="state in this.states">
			{{ state.state }}
		</option>
	</select>
</template>

<script>
	export default {

		props: ['states', 'cities', 'countryid', 'allcities', 'newstateid', 'newcityid'],
		
		created() {
			console.log('hello myNewStates');
			console.log(this.states);
			this.theState = this.states[0].state;
			this.changedState();
		},

		watch: {
			states: function() {
				this.theState = this.states[0].state;
			},

			newstateid: function() {
				this.myNewStateId = this.newstateid;
			},

			newcityid: function() {
				this.myNewCityId = this.newcityid;
			}
		},

		data() {
			return {
				theState: '',
				myNewStateId: '',
				myNewCityId: '',
				myNewCities: [],
				myNewSuburbs: [],
			}
		},

		methods: {
			changedState() {
				console.log('changed state');
				// var firstState = true;
				// this.myNewCities = [];
				for(var x=0; x<this.states.length; x++) {
					// find the first state that matches the country_id
					if(this.states[x].country_id == this.countryid) {
						// console.log(this.states[x]);
						// console.log(this.states[x].state);
						if(this.states[x].state == this.theState) {
							this.myNewStateId = this.states[x].id;
							// console.log(this.myNewStateId);
						}
					}
				}
				// var firstCity = true;
				// for(var y=0; y<this.allcities.length; y++) {
				// 	// city by Id
				// 	// city by state Id 
				// 	if(this.allcities[y].state_id == this.myNewStateId) {
				// 		// populate cities
				// 		// console.log(this.cities[y]);
				// 		// console.log('city');
				// 		// console.log(this.cities[y]);
				// 		if(firstCity) {
				// 			this.myNewCityId = this.allcities[y].id;
				// 		}
				// 		// this.myNewCities.push(this.allcities[y]);
				// 	}
				// }
				// this.$emit('stateupdated', this.myNewCities);
				this.$emit('stateupdated', this.myNewStateId);
			}
		}
	}
</script>