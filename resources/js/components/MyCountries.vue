<template name="my-countries" >
	
	<div class="row">
		
		<select @change="changeCountry" id="countrySelect" v-model="theCountry">
			<option v-for="country in this.countries">
				{{ country.country }}
			</option>
		</select>

		<my-states :states="this.myNewStates" :cities="this.myNewCities" :countryid="this.theCountryId" :newcities="this.myNewCities" :allcities="this.cities" @stateupdated="stateUpdated" :newstateid="this.theStateId" newcityid="this.theCityId"></my-states>

		<my-cities :cities="this.myNewCities" :newcityid="this.theCityId" stateid="this.theStateId" @cityupdated="this.cityUpdated"></my-cities>

		<br>
		<p>Choose my suburb(s):</p>
		<my-suburbs :suburbs="this.myNewSuburbs"></my-suburbs>
		
	</div>

</template>

<script>
import MyStates from '../components/MyStates.vue'
import MyCities from '../components/MyCities.vue'
import MySuburbs from '../components/MySuburbs.vue'

	export default {

		components: {
			MyStates,
			MyCities,
			MySuburbs
		},

		props: ['countries', 'states', 'cities', 'suburbs'],

		data() {
			return {
				// Need to populate these dynamically
				theCountry: '',
				theCountryId: '', // 1
				myNewStates: [],
				theStateId: '1',
				myNewCities: [],
				theCityId: '',
				myNewSuburbs: [],
				theSuburbId: '1'
			}
		},

		created() {
			// console.log('created my suburbs');
			// console.log(this.countries);
			// console.log(this.states);
			// console.log(this.cities);
			// console.log(this.suburbs);

			// initialize from datasource
			this.theCountry = this.countries[0].country;
			this.theCountryId = this.countries[0].id;

			// execute on build
			this.changeCountry();

		},

		methods: {
			// The user has changed the Country
			changeCountry() {
				// loop over all countries in datasource
				// update theCountryid => v-model is theCountry
				for(var i=0; i<this.countries.length; i++) {
					if(this.countries[i]["country"] == this.theCountry) {
						this.theCountryId = this.countries[i].id;
					}
				}
				// Country has changed so the state has also changed
				this.changedState();
			},

			changedState() {
				this.myNewStates = [];
				var firstState = true;
				// loop over all states
				for(var x=0; x<this.states.length; x++) {
					// find the first state that matches the country_id
					if(this.states[x].country_id == this.theCountryId) {
						if(firstState) {
							firstState = false;
							// get the StateId 
							this.theStateId = this.states[x].id;
						}
						// push all states that match the country_id 
						this.myNewStates.push(this.states[x]);
					}
				}
				// execute changedCity 
				this.changedCity();
			},

			changedCity() {
				this.myNewCities = [];
				var firstCity = true;
				// loop over all cities 
				for(var y=0; y<this.cities.length; y++) {
					if(this.cities[y].state_id == this.theStateId) {
						if(firstCity) {
							firstCity = false;
							this.theCityId = this.cities[y].id;
						}
						this.myNewCities.push(this.cities[y]);
					}
				}

				this.changedSuburb();
			},

			changedSuburb() {
				this.myNewSuburbs = [];
				var firstSuburb = true;
				for(var i=0; i<this.suburbs.length; i++) {
					if(this.suburbs[i].city_id == this.theCityId) {
						if(firstSuburb) {
							firstSuburb = false;
							this.theSuburbId = this.suburbs[i].id;
						}
						this.myNewSuburbs.push(this.suburbs[i]);
					}
				}
				// console.log('My New States');
				// console.log(this.myNewStates);
			},

			// Events: 
			stateUpdated(stateid) {
				this.myNewCities = [];
				console.log('emitted: state updated');
				// console.log(newcities);
				console.log(stateid);
				this.theStateId = stateid;
				this.changedCity()
				// var sizeof = newcities.length;
				// console.log(sizeof);
				// for(var a=0; a<newcities.length; a++) {
					// this.myNewCities.push(newcities[a]);
				// }
			},

			// cityUpdated
			cityUpdated(thenewcityid) {
				console.log('emitted: city updated');
				console.log(thenewcityid);
				this.theCityId = thenewcityid;
				this.changedSuburb();
			}
			// purchase 
		}
	}
</script>