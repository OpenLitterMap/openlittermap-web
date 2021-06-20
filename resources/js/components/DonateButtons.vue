<template>
	<div>
		<loading v-if="loading" :active.sync="loading" :is-full-page="false" />

		<div v-else class="box">
			<h3 class="title is-2 mb1em">
				<strong style="color: #363636;">Select an amount:</strong>
			</h3>

			<div class="grid-container has-text-centered">
				<div v-for="amount in amounts">
					<div class="box" style="background-color: lightgreen;">
						<h3 class="title is-3 mb1em">
							<strong>€{{ amount.amount / 100 }}</strong>
						</h3>

						<button class="button is-medium is-primary" @click="donate(amount.id)">Donate now</button>
					</div>
				</div>
			</div>

			<h3 class="title is-1" style="text-align: right;">
				<strong style="color: #363636;">Thank you.</strong>
			</h3>
		</div>
	</div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'DonateButtons',
    components: { Loading },
    data () {
        return {
            stripeEmail: '',
            stripeToken: '',
            amount: '',
            loading: true
        }
    },
    async created ()
	{
        this.loading = true;

        await this.$store.dispatch('GET_DONATION_AMOUNTS');

        this.$emit('donations-loaded')

        this.loading = false;
	},
	computed: {
		/**
		 * The options to donate in cents (500 = €5)
		 */
		amounts ()
		{
			return this.$store.state.donate.amounts;
		}
	},
	methods: {
		/**
		 *
		 */
		donate (price)
		{
			this.amount = this.prices[price] * 100;

			this.stripe = StripeCheckout.configure({
				key: OLM.stripeKey,
				image: "https://stripe.com/img/documentation/checkout/marketplace.png",
				locale: "auto",
				panelLabel: "One-time Donation",
				// email: this.email,

				token: (token) => { // use the arrow ES15 syntax to make this local
					// input the token id into the form for submission
					this.stripeToken = token.id,
					this.stripeEmail = token.email;

					axios.post('/donate', this.$data)

						 .then(response => {
							alert('Congratulations! Your payment was successful. Thanks!');
						 })

						 .catch(error => {
							alert('Sorry, there was an error processing your card! You have not been charged. Please try again');
						 });
				}
			});

			this.stripe.open({
				name: '€'+this.prices[price],
				description: 'OpenLitterMap',
				zipCode: false,
				amount: this.prices[price] * 100,
			});
		}
	}
}
</script>

<style lang="scss" scoped>

	.grid-container {
		display: grid;
		grid-template-columns: 1fr 1fr 1fr 1fr;
		grid-column-gap: 1em;
		grid-row-gap: 1em;
		padding-bottom: 2em;
	}

	@media screen and (max-width: 1000px)
	{
		.grid-container {
			grid-template-columns: 1fr 1fr;
			grid-row-gap: 2em !important;
		}
	}

	@media screen and (max-width: 600px)
	{
		.grid-container {
			grid-template-columns: 1fr;
			grid-row-gap: 2em !important;
		}
	}
</style>
