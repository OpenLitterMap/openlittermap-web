<template name="create-account">
	<form action="/join" method="POST">
		<!-- <input type="hidden" name="stripeToken" v-model="stripeToken"> -->
		<!-- <input type="hidden" name="stripeEmail" v-model="stripeEmail"> -->
		<select name="plan" v-model="plan">
			<option v-for="plan in plans" :value="plan.id">
				{{ plan.name }} &mdash; €{{ plan.price / 100}}
			</option>
		</select>
		<!-- <button class="button is-success" @click.prevent="subscribe">Sign me up!</button> -->
		<p class="help is-danger" v-show="status" v-text="status">{{ status }}</p>
	</form>
</template>


<script>
	export default {

		props: ['plans'],

		mounted(){
			this.selectedPlan = document.getElementById('selectedPlan').innerText;
			console.log(this.selectedPlan);
			if (this.selectedPlan == 'free') {
				this.plan = 1;
			}
			if (this.selectedPlan == 'basic') {
				this.plan = 2;
			}
			if (this.selectedPlan == 'advanced'){
				this.plan = 3;
			}
			if (this.selectedPlan == 'pro'){
				this.plan = 4;
			}
		},

		data(){
			return {
				stripeEmail: '',
				stripeToken: '',
				plan: 1,
				status: '',
				selectedPlan: '',
			}
		},

		created(){

			// console.log(OLM);

			this.stripe = StripeCheckout.configure({
				key: OLM.stripeKey,
				image: "https://stripe.com/img/documentation/checkout/marketplace.png",
				locate: "auto",
				panelLabel: "Subscribe For",
				// email: 'email@email.com',

				// Callback with token object
				// get a token object in response to the forms ajax request 
				token: (token) => { // use the arrow ES15 syntax to make this local
					this.stripeToken = token.id,
					this.stripeEmail = token.email;

					axios.post('/join', this.$data)
							 .then(response => alert('Congratulations! You are now subscribed. Remember to verify your email to enable login.'))
							 // .catch(error => console.log(error))
				}
			});
	},

	methods: {

		// updatePlan(){

		// },

		subscribe() {
			let plan = this.findPlanById(this.plan);
			this.stripe.open({
				name: plan.name,
				description: plan.name,
				zipCode: false,
				amount: plan.price
			});
		},

		findPlanById(id){
			return this.plans.find(plan => plan.id == id);
		}
	}
}
</script>