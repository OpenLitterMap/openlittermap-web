<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Finance the development of OpenLitterMap</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column one-third is-offset-1">

				<div v-if="this.isvalid">

					<p>Current Subscription: <strong>{{ this.plan }}</strong></p>

					<div v-if="this.ongraceperiod">
						<p>Status: Your subscription has been cancelled.</p>
						<p>You have paid until: {{ this.ends_at }}.</p>
						<p v-show="this.msg_status" class="is-success">{{ this.msg_status }}</p>
						<br>
						<button class="button is-success" @click="reactivate" :class="[{ 'is-loading': submitting }, 'is-primary' ]">Reactivate My Subscription</button>
					</div>

					<div v-else>
						<p>Status: Active.</p>
						<p>Do you want to cancel your subscription?</p>
						<!-- <p v-show="this.msg_password" class="is-danger">{{ this.msg_password }}</p> -->
						<p v-show="this.msg_status" class="is-danger">{{ this.msg_status }}</p>
						<form method="POST" action="/cancel" role="form" @submit.prevent="cancelStripe">
							<input type="password" name="password" id="password" placeholder="******" v-model="cancelform.password" required @keydown="clearErrors" />
							<button class="button is-danger" :class="[{ 'is-loading': submitting }, 'is-success' ]">Cancel My Subscription</button>
						</form>
					</div>

				</div>

				<!-- Not Valid -->
				<div v-else>

					<!-- Todo - fix this -->
					<!-- <div v-if="this.user">
						<p>Your previous subscription and the grace period for 1-click reactivation has ended. Would you like to sign up again?</p>
						<br>
					</div> -->

					<!-- <div v-else> -->
						<p>Time to sign up?</p>
					<!-- </div> -->

					<div class="columns">
						<div class="column is-one-quarter">
							<br>
							<div class="control">
								<div class="select">
									<select name="plans" v-model="myplan">
										<option v-for="plan in this.plans" id="options" :value="plan.id">
											{{ plan.name }} &mdash; €{{ plan.price / 100}}
										</option>
									</select>
								</div>
							</div>
							<br>
							<button class="button is-large is-success" @click="subscribe">I want to subscribe.</button>
							<br>
							<br>
							<p>We use <a href="https://stripe.com">Stripe</a> to securely handle payments & subscriptions over an encrypted https network.</p>
						</div>

						<div class="column is-half is-offset-1">
							<p><strong style="color: #3273dc;">Free</strong></p>
							<ul>
								<li>- Upload 1000 images per day</li>
								<li>- All users can earn Littercoin</li>
								<li>- Your free account costs us money!</li>
							</ul>
							<br>
							<p><strong style="color: #22C65B;">Please help finance the development of OpenLitterMap with a monthly subscription starting at €5 per month (€0.06 per day)</strong></p>
							<ul>
								<li>- Support Open Data on Plastic Pollution</li>
								<li>- Help cover the server costs</li>
								<li>- Hire developers, designers & graduates</li>
								<li>- Produce videos</li>
								<li>- Write papers</li>
								<li>- Conferences & outreach</li>
								<li>- Incentivize data collection with Littercoin</li>
								<li>- More exciting updates coming soon</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>

class Errors {

	/**
	 * Create a new errors instance
	 */
	constructor() {
		this.errors = {};
	}

	/**
	 * Get the error message for a field
	 */
	get(field){
		if(this.errors[field]){
			return this.errors[field][0];
		}
	}

	/**
	 * Determine if an error exists for a given field
	 */
	has(field){
		return this.errors.hasOwnProperty(field);
	}

	/**
	 * Record the new errors
	 */
	record(errors){
		this.errors = errors;
	}

	/**
	 * Clear one or all error fields
	 */
	clear(field){
		if (field) {
			delete this.errors[field];
			return;
		}
		// else
		this.errors = {};
	}

	/**
	 * Determine if we have any errors
	 */
	any(){
		console.log(this);
		return Object.keys(this.errors).length > 0;
	}
}

class Form {

	/**
	 * Create a new Form instance
	 */
	constructor(data) {
		this.originalData = data;

		// create data objects on the form
		for(let field in data) {
			this[field] = data[field];
		}

		this.errors = new Errors();
	}

	/**
	 * Fetch relevant data for the form
	 */
	data() {
		// // clone the object  old way
		// let data = Object.assign({}, this);
		// // delete unnecessary data
		// delete data.originalData;
		// delete data.errors;
		// return data;
		let data = {};
		// filter through the original data
		for (let property in this.originalData){
			data[property] = this[property];
		}
		return data;
	}

	/**
	 * Reset the form fields
	 */
	reset() {
		for(let field in this.originalData){
			this[field] = '';
		}
		this.errors.clear();
	}

	/**
	 * Submit the form
	 */
	submit(requestType, url) {

		// return a set up a promise
		return new Promise((resolve, reject) => {
			// submit the ajax request
			axios[requestType](url, this.data())
			  // 200
			 .then(response => {
			 	console.log('first');
			 	// use local onSuccess method then trigger Vue method with resolve
			 	console.log(response.data); // .email | .user_id
			 	// this.onSuccess(response.data);
			 	// callback with the data
			 	resolve(response.data);
			 	console.log('third')
			 })
			  // not 200
			 .catch(error => {
			 	this.onFail(error.response.data);
			 	reject(error.response.data);
			 });
		});
	}

	/**
	 * Handle a successful form submission
	 */
	onSuccess(data){
	 	console.log('second');
		// this.reset();
	};

	/**
	 * Handle a failed form submission
	 */
	onFail(errors) {
		console.log(errors);
		this.errors.record(errors);
	}
}

export default {
	// props: ['user', 'subscription', 'plan', 'isvalid', 'ongraceperiod', 'ends_at', 'plans'],
    name: 'Payments',
	data() {
		return {
			// form manages errors
			cancelform: new Form({
				password: '',
			}),
			error: '',
			msg_password: '',
			submitting: false,
			msg_status: '',
			myplan: 1,
			stripeToken: '',
			stripeEmail: '',
			stripe: null
		};
	},
	methods: {
		subscribe() {
			if (this.myplan == 1) {
				return alert("You are already on the free plan. Please consider financing the development Open Litter Map with a monthly subscription starting as little as ~6 cents / p a day. You can unsubscribe or resubscribe at a click! It's easy.");
			}

			var newplan = this.plans[this.myplan - 1];

			this.stripe = StripeCheckout.configure({
				key: OLM.stripeKey,
				image: "https://stripe.com/img/documentation/checkout/marketplace.png",
				locale: "auto",
				panelLabel: "Subscribe For",
				email: this.email,

				// RESPONSE FROM STRIPE MODAL INPUT
				// Submit the stripe form
				// The callback to invoke the payment once the checkout process has been complete
				// func ( token, args )
				// token.id can be used to create a charge or can be attacted to a customer
				// token.email contains the email addresses entered by the user
				// args contain billing + shipping info if enabled

				// Callback 1 from Stripe with token object -> user has been created?
				// get a token object in response to the forms ajax request
				token: (token) => { // use the arrow ES15 syntax to make this local
					console.log(token);
					// input the token id into the form for submission
					this.stripeToken = token.id,
					this.stripeEmail = token.email;
					// console.log(this);
					axios.post('/change', this.$data)

						// respond immediately
						 .then(response => {
						 	this.submitting = false;
						 	// this.form.reset();
						 	alert('Congratulations! You are now subscribed. Remember to verify your email to enable login.');
			 				window.location.href = window.location.href;
						 	// console.log(response);
						 })
						 // once alert closes, the response comes in

						 .catch(error => {
						 	// console.log('errors');
						 	console.log(error);
						 	alert('Error! Please contact openlittermap@gmail.com for assistance. Thank you for your patience!');
						 	// this.submitting = false;
						 	// this.form.reset();
						 	// this.status = 'Sorry, but your card was declined. A free account was created for you but you will need to log in after verifying your email to update your account subscription in the settings panel.';
						 });
				} // end token
			});
			// Stripe setup 2: Open the modal when
			this.stripe.open({
				name: newplan.name,
				description: newplan.description,
				zipCode: false,
				amount: newplan.price
			});
		},
		cancelStripe() {
			this.submitting = true;
			axios({
                method: 'post',
                url: '/en/settings/payments/cancel',
                data: { password: this.cancelform.password }
            })
			.then(response => {
				// if you get  message, the password was wrong
			 	if (response.data) {
			 		this.submitting = false;
			 		this.msg_password = response.data.message;
			 		this.msg_status = response.data.status;
			 		this.cancelform.password = '';
			 		window.location.href = window.location.href;
			 	}
			 })
			 .catch(error => {
			 	console.log(error.response.data);
			 });
			// call back will then cancel on our end
		},
		clearErrors() {
			this.msg_password = '';
		},
		reactivate() {
			this.submitting = true;
			axios({
                method: 'post',
                url: '/en/settings/payments/reactivate',
            })

			.then(response => {
			 	console.log(response);
			 	this.submitting = false;
			 	this.msg_status = response.data.message;
			 	// window.location.href = window.location.href;
			 })
			 .catch(error => {
			 	console.log(error);
			 });
		}
	},
	computed: {
		csrfToken() {
			// console.log($('meta[name="csrf-token"]').attr('content'));
    		return $('meta[name="csrf-token"]').attr('content');
		}
	}
}
</script>
