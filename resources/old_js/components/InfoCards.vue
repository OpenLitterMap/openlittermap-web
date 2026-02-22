<template name="info-cards">
	<div class="container">
		<div class="columns">
	    <div class="column is-4" v-for="card in this.cards" @click="letsFlip(card.id)">
		    <transition-group name="component-fade" mode="out-in">
			    <div :class="card.wow" v-if="!card.flip" key="card.id">
		        <p class="has-text-centered" style="color:#3498db;">
		          <span :class="card.icon"></span>
		        </p>
		        <br>
		        <p class="has-text-centered" style="color:black;"><b>{{ card.title }}:</b></p>
		        <br>
		        <ul style="text-align: center;">
		          <li v-for="msg in card.messages">{{ msg }}</li>
		        </ul>
		      </div>

		      <div :class="card.wow" v-else key="card.id">
		      	<p class="has-text-centered" style="color:#3498db;">
		          <span :class="card.icon"></span>
		        </p>
		        <br>
		        <p class="has-text-centered" style="color:black;"><b>{{ card.title }}:</b></p>
		        <br>
		        <p>{{ card.text }}</p>
		      </div>
		    </transition-group>
	    </div>
	  </div>
	</div>
</template>

<script>
	export default {
		methods: {
			letsFlip(i) {
				this.cards.filter(function(item,index) {
					return item.id == i;
				}).forEach(function(item, index) {
					item.flip = ! item.flip;

				});
			}
		},
		data() {
			return {
				cards: [
					{
						id: 1,
						flip: false,
						icon: 'fa fa-mobile fa-4x',
						title: 'Citizens',
						messages: ['Sign up for an account', 'Create open data', 'Recieve Littercoin'],
						text: 'OpenLitterMap empowers you to create Open Data on plastic pollution. Anyone can download all of the data for free, and use it for any purpose.',
						wow: 'box section wow slideInRight'
					},
					{
						id: 2,
						flip: false,
						icon: 'fa fa-legal fa-4x',
						title: 'Governments',
						messages: ['Get Real-time Geospatial Data', 'Improve services and reduce costs', 'Reward Citizens by accepting Littercoin'],
						text: 'With Open Data we can reduce expenditure and improve services. However, there is a cost associated with getting this done.',
						wow: 'box section wow slideInUp'
					},
					{
						id: 3,
						flip: false,
						icon: 'fa fa-briefcase fa-4x',
						title: 'Industry',
						messages: ['Improve your Brand', 'Share the responsiblity', 'Reward Citizens by accepting Littercoin'],
						text: 'Your brand is at risk from the destruction it is causing when left to rot in the environment. We can help you turn this problem into a solution.',
						wow: 'box section wow slideInLeft'
					}
				]
			}
		}
	}
</script>

<style>
	.component-fade-enter-active, .component-fade-leave-active {
	  transition: opacity .3s ease;
	}
	.component-fade-enter, .component-fade-leave-to
	/* .component-fade-leave-active below version 2.1.8 */ {
	  opacity: 0;
	}
</style>