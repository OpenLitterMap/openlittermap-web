<template>
	<div class="container">
		<div class="columns" style="padding-top: 5em;">
			<div class="column is-two-thirds is-offset-2">
				<div class="box">
					<h3 class="pb1em">Concerned Citizen</h3>

					<p class="pb1em">Please consider supporting the development of open data on plastic pollution by crowdfunding OpenLitterMap with as little as 6 cents a day with a monthly subscription to help grow and develop this important platform.</p>

					<div class="control">
						<div class="select">
							<select v-model="planInt">
								<option v-for="plan in plans" :value="plan.id">
									{{ plan.name }} &mdash; €{{ plan.price / 100 }}
								</option>
							</select>
						</div>
					</div>

<!--					<form action="/join" method="POST">-->
<!--						<input type="hidden" name="stripeToken" v-model="stripeToken">-->
<!--						<input type="hidden" name="stripeEmail" v-model="stripeEmail">-->
<!--					</form>-->
				</div>
			</div>
		</div>

		<div class="columns">
			<div class="column is-two-thirds is-offset-2">
				<div class="box">
					<div class="column is-two-thirds is-offset-2">
						<h3 class="title is-3">Create your account</h3>
						<form method="POST"
							  action="/register"
							  @submit.prevent="checkIsFormChecked"
							  role="form"
							  @keydown="form.errors.clear($event.target.name)"
						>
							<input type="hidden" name="csrf-token" :value="csrfToken">
		                     <!-- NAME -->
			                <label for="name">Name</label>
			              	<span class="is-danger" 
			              		  v-if="form.errors.has('name')" 
			              		  v-text="form.errors.get('name')"
			              	></span>
			                <div class="field">
			                	<div class="control has-icons-left">
			                    	<input id="name" 
			                    		   name="name" 
			                    		   type="text" 
			                    		   class="input" 
			                    		   placeholder="Your full name"  
			                    		   aria-describedby="sizing-addon2" 
			                    		   v-model="form.name" 
			                    		   required />
			                    	<span class="icon is-small is-left">
      									<i class="fa fa-user"></i>
    								</span>
			                   </div>
			                </div>

			                <!-- USERNAME OR ORGANISATION -->
		                    <label for="username">Unique Identifier</label>
			              	<span class="is-danger" 
			              		  v-if="form.errors.has('username')" 
			              		  v-text="form.errors.get('username')"
			              	></span>
		                    <br>
		                    <div class="field">
								<div class="control has-icons-left">
							    	<input id="inputusername"
							    		   name="username" 
							    		   class="input" 
							    		   type="text" 
							    		   placeholder="Unique Username or Organisation" required 
							    		   v-model="form.username" />
						    	    <span class="icon is-small is-left">
			      						@
			    					</span>
							    </div>
							</div>

		                    <!-- EMAIL -->
		                    <label for="email">E-Mail Address</label>
							<span class="is-danger" 
								  v-if="form.errors.has('email')" 
								  v-text="form.errors.get('email')"
							></span>
		                    <br>
		                    <div class="field">
								<div class="control has-icons-left">
							    	<input id="inputemail"
							    		   name="email"
							    		   class="input" 
							    		   type="email" 
							    		   placeholder="you@email.com" 
							    		   required 
							    		   v-model="form.email" />
						    	    <span class="icon is-small is-left">
			      						<i class="fa fa-envelope"></i>
			    					</span>
							    </div>
							</div>

		                    <!-- PASSWORD -->
		                    <label for="password">Password. Must contain Uppercase, lowercase and a number.</label>
		                    <br>
							<span class="is-danger" 
								  v-if="form.errors.has('password')" 
								  v-text="form.errors.get('password')"
							></span>
		                    <div class="field">
								<div class="control has-icons-left">
							    	<input class="input" 
							    		   name="password"
							    		   type="password" 
							    		   placeholder="Create a strong password" 
							    		   required v-model="form.password" 
							    		   id="inputpassword" />
							    	<span class="icon is-small is-left">
			      						<i class="fa fa-key"></i>
			    					</span>
							    </div>
							</div>

		                    <!-- CONFIRM PASSWORD -->
		                    <label for="password_confirmation">Confirm Password</label>
		                    <br>
							<span class="is-danger" 
								  v-if="form.errors.has('password_confirmation')" 
								  v-text="form.errors.get('password_confirmation')"
							></span>
		                    <div class="field">
								<div class="control has-icons-left">
							    	<input class="input" 
							    		   type="password" 
							    		   name="password_confirmation" 
							    		   placeholder="Confirm your Password" 
							    		   required 
							    		   v-model="form.password_confirmation" 
							    		   id="confirmpassword" />
							    	<span class="icon is-small is-left">
			      						<i class="fa fa-refresh"></i>
			    					</span>
							    </div>
							</div>

		                    <!-- CHECKBOX, T+C -->
		                    <p>   
		                        <input type="checkbox" class="filled-in" name="ConfirmToS" id="ConfirmToS" />
		                        <label for="ConfirmToS">
		                             I have read and agree to the <a href="/terms">Terms and Conditions of use</a> and <a href="/privacy">Privacy Policy</a>
		                        </label>                    
		                    </p>

		                    <div class="column is-half is-offset-2">
		                    	<!-- <span class="is-danger" v-if="form.errors.has('g_recaptcha_response')" v-text="form.errors.get('g_recaptcha_response')"></span> -->
		                    	<div id="grecaptcha" class="g-recaptcha" style="#recaptcha_area { margin: auto}" :data-sitekey="computedKey" :g-recaptcha-response="form.g_recaptcha_response"></div>
		                    </div>
		                    <br>
		                    <div style="text-align: center;">
		                        <button type="submit" 
		                        	    class="button" 
		                        	    :disabled="form.errors.any()" 
		                        	    :class="[{ 'is-loading': submitting }, 
		                        	    		   'is-primary' ]"
		                        >Sign up</button>
		                        <br>
		                        <br>
		                        <br>
		                        <p>Note: If you do not recieve the verification e-mail in your inbox, please check your spam/junk folder.</p>
		                    </div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>


<script>

class Errors {

	constructor ()
	{
		this.errors = {};
	}

	get (field)
	{
		if (this.errors[field])
		{
			return this.errors[field][0];
		}
	}


	has (field)
	{
		return this.errors.hasOwnProperty(field);
	}

	record (errors)
	{
		this.errors = errors;
	}

	clear (field)
	{
		if (this.has('g_recaptcha_response'))
		{
			delete this.errors['g_recaptcha_response'];
		}

		if (field)
		{
			delete this.errors[field];
			return;
		}
	}

	/**
	 * Determine if we have any errors
	 */
	any ()
	{
		if (this.has('g_recaptcha_response'))
		{
			delete this['g_recaptcha_response'];
		}

		return Object.keys(this.errors).length > 0;
	}
}

class Form {

	constructor (data)
	{
		this.originalData = data;

		// create data objects on the form
		for (let field in data)
		{
			this[field] = data[field];
		}

		this.errors = new Errors();
	}

	data ()
	{
		let data = {};
		// filter through the original data
		for (let property in this.originalData)
		{
			data[property] = this[property];
		}

		return data;
	}

	/**
	 * Reset the form fields
	 */
	reset ()
	{
		for (let field in this.originalData)
		{
			this[field] = '';
		}

		this.errors.clear();
	}

	/**
	 * Submit the form
	 */
	submit (requestType, url)
	{
		// bind captcha response
		this['g_recaptcha_response'] = document.getElementsByClassName("g-recaptcha-response")[0].value;

		// return a set up a promise
		return new Promise((resolve, reject) => {
			// submit the ajax request
			axios[requestType](url, this.data())
			  // 200
			 .then(response => {
				// use local onSuccess method then trigger Vue method with resolve
				this.onSuccess(response.data);
				// callback with the data
				resolve(response.data);
			 })
			  // not 200
			 .catch(error => {
				this.onFail(error.response.data.errors);
				reject(error.response.data);
			 });
		});
	}

	/**
	 * Handle a successful form submission
	 */
	onSuccess (data)
	{
		this.user_id = data.user_id;
		this.stripeEmail = data.email;
		this.reset();
	};

	/**
	 * Handle a failed form submission
	 */
	onFail (errors)
	{
		this.errors.record(errors);
	}
}

export default {
	name: 'CheckoutForm',
	props: ['plan'],
	created ()
	{
		if (this.plan)
		{
			if (this.plan == 'startup') this.planInt = 2;
			if (this.plan == 'basic') this.planInt = 3;
			if (this.plan == 'advanced') this.planInt = 4;
			if (this.plan == 'professional') this.planInt = 5;
		}
	},
	data ()
	{
		return {
			submitting: false,
			// REGISTRATION
			form: new Form({
				name: '',
				username: '',
				email: '',
				password: '',
				password_confirmation: '',
				g_recaptcha_response: '',
			}),
			// STRIPE
			user_id: '',
			stripeEmail: '',
			stripeToken: '',
			planInt: 1,
			status: '',
			selectedPlan: '',
		};
	},

	computed: {

		/**
		 *
		 */
		csrfToken ()
		{
			// return $('meta[name="csrf-token"]').attr('content');
			return OLM.csrfToken;
		},

		/**
		 * Key to return for google-recaptcha
		 */
		computedKey ()
		{
			if (process.env.NODE_ENV === "development")
			{
				return "6Lfd4HMUAAAAAMZBVUIpBJI7OfwtPcbqR6kGndSE";
			}

			return "6LciihwUAAAAADsZr0CYUoLPSMOIiwKvORj8AD9m"
		},

		/**
		 * Array of plans from the database
		 */
		plans ()
		{
			return this.$store.state.plans.plans;
		}
	},

	methods: {

		// CHECK IF THE TERMS ARE ACCEPTED
		checkIsFormChecked ()
		{
			var box = document.getElementById('ConfirmToS');
			if (box.checked){
				// Try to create a new user
				this.submitting = true;

				this.form.submit('post', '/register')
					// if successful
					.then(response => {
						// console.log('fourth');
						// console.log(response);
						if (this.plan == 1) {
							this.submitting = false;
							return alert('Congratulations! Your free account has been created. Please verify your email to activate login');
						}

						alert('Congratulations! An account has been created for you. Remember to verify your email to activate login. The window for processing your card will now load. Thank you for choosing to support the development of OpenLitterMap!');
						// this.submitting = true;
						this.loadStripe(response);
						// callback with the data
						// this.submitting = true;
						// console.log('callback');
						// console.log(this);
						// console.log(response);
						resolve(response);
						// console.log('fifth?');
						// console.log(resolve);
						// console.log(resolve.data);
				 })
				// if not successful
				.catch(errors => {
					// console.log(errors);
					console.log(this.form.errors);
					this.submitting = false;
					// this.status = 'Your card was declined';
				});
			} else {
				alert("Please indicate that you accept the Terms, Conditions and Privacy Policy");
			}
		},

		// // IF SO, SUBMIT THE FORM
		// subscribe() {

		// 	// 1. Get the recaptcha response
		// 	// this.form.g_recaptcha_response = document.getElementsByClassName("g-recaptcha-response")[0].value;
		// 	console.log('vue method');
		// 	this.form.g_recaptcha_response = document.getElementsByClassName("g-recaptcha-response")[0].value;
		// 	// 2. Create the user
		// 	axios.post('/register', this.form)
		// 		 .then(this.onSuccess(response))
		// 		 .catch(error => this.form.errors.record(error.response.data));

		// },

		findPlanById (id)
		{
			return this.plans.find(plan => plan.id == id);
		},

		loadStripe (response)
		{
			// console.log(response);
			let plan = this.findPlanById(this.plan);
			// Configure Stripe step 1
			// called handler in the docs
			this.stripe = StripeCheckout.configure({
				key: OLM.stripeKey,
				image: "https://stripe.com/img/documentation/checkout/marketplace.png",
				locale: "auto",
				panelLabel: "Subscribe For",
				email: response.email,

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
					// input the token id into the form for submission
					this.stripeToken = token.id,
					this.stripeEmail = token.email;
					// console.log(this);
					axios.post('/join', this.$data)
						// if 200
						 .then(response => {
							this.submitting = false;
							this.form.reset();
							alert('Congratulations! You are now subscribed. Remember to verify your email to enable login.');
						 })
						 // once alert closes, the response comes in
						 .catch(error => {
							// console.log(error);
							this.submitting = false;
							this.form.reset();
							this.status = 'Sorry, but your card was declined. A free account was created for you but you will need to log in after verifying your email to update your account subscription in the settings panel.';
						 });
				} // end token
			});

			// Stripe setup 2: Open the modal when
			this.stripe.open({
				name: plan.name,
				description: plan.description,
				zipCode: false,
				amount: plan.price
			});
		}
	}
}
</script>

<style>

	.input-group {
		padding-bottom: 1em;
	}

	.is-danger {
		color: red;
	}
</style>