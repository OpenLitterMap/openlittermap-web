<template>
	<div class="container">
		<div class="columns" style="padding-top: 5em; margin-bottom: 3em;">
			<div class="column is-two-thirds is-offset-2">
				<div class="has-text-centered">
					<h3 class="pb2">Concerned Citizen</h3>

					<strong>Please consider supporting the development of open data on plastic pollution by crowdfunding OpenLitterMap with as little as 6 cents a day with a monthly subscription to help grow and develop this important platform.</strong>

					<div class="control mt2">
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

		<div class="signup-container">

            <h3 class="title is-3">Create your account</h3>

            <!-- action="/register"-->
            <form
                method="post"
                @submit.prevent="submit"
                @keydown="clearErrors($event.target.name)"
            >
                <!-- NAME -->
                <label for="name">Name</label>

                <span class="is-danger" v-if="errorExists('name')" v-text="getFirstError('name')" />
                <br>

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            name="name"
                            type="text"
                            class="input"
                            placeholder="Your full name"
                            required
                            v-model="name"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-user" />
                        </span>
                   </div>
                </div>

                <!-- USERNAME OR ORGANISATION -->
                <label for="username">Unique Identifier</label>

                <span class="is-danger" v-if="errorExists('username')" v-text="getFirstError('username')" />
                <br>

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            name="username"
                            class="input"
                            placeholder="Unique Username or Organisation"
                            required
                            type="text"
                            v-model="username"
                        />
                        <span class="icon is-small is-left">
                            @
                        </span>
                    </div>
                </div>

                <!-- EMAIL -->
                <label for="email">E-Mail Address</label>

                <span class="is-danger" v-if="errorExists('email')" v-text="getFirstError('email')" />
                <br>

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            name="email"
                            class="input"
                            type="email"
                            placeholder="you@email.com"
                            required
                            v-model="email"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-envelope" />
                        </span>
                    </div>
                </div>

                <!-- PASSWORD -->
                <label for="password">Password. Must contain Uppercase, lowercase and a number.</label>

                <span class="is-danger" v-if="errorExists('password')" v-text="getFirstError('password')" />
                <br>

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            class="input"
                            name="password"
                            type="password"
                            placeholder="Create a strong password"
                            required
                            v-model="password"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-key" />
                        </span>
                    </div>
                </div>

                <!-- CONFIRM PASSWORD -->
                <label for="password_confirmation">Confirm Password</label>

                <span class="is-danger" v-if="errorExists('password_confirmation')" v-text="getFirstError('password_confirmation')" />
                <br>

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            class="input"
                            type="password"
                            name="password_confirmation"
                            placeholder="Confirm your Password"
                            required
                            v-model="password_confirmation"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-refresh"/>
                        </span>
                    </div>
                </div>

                <!-- CHECKBOX, T+C -->
                <p>
                    <input
                        type="checkbox"
                        class="filled-in"
                        name="ConfirmToS"
                        id="ConfirmToS"
                        v-model="checkbox"
                    />
                    <label for="ConfirmToS">
                         I have read and agree to the <router-link to="/terms">Terms and Conditions of use</router-link> and <router-link to="/privacy">Privacy Policy</router-link>
                    </label>
                </p>

                <div class="captcha">
                    <!-- <span class="is-danger" v-if="errorExists('g_recaptcha_response')" v-text="getFirstError('g_recaptcha_response')"></span> -->
                    <div
                        id="grecaptcha"
                        class="g-recaptcha"
                        style="#recaptcha_area { margin: auto}"
                        :data-sitekey="computedKey"
                        :g-recaptcha-response="g_recaptcha_response"
                    />
                </div>
                <br>
                <div style="text-align: center;">

                    <button :class="button" :disabled="checkDisabled">Sign up</button>

                    <p>Note: If you do not recieve the verification e-mail in your inbox, please check your spam/junk folder.</p>

                </div>
            </form>
		</div>
	</div>
</template>

<script>
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
            processing: false,
            btn: 'button is-medium is-primary',
			// REGISTRATION
            name: '',
            username: '',
            email: '',
            password: '',
            checkbox: false,
            password_confirmation: '',
            g_recaptcha_response: '',
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
         * Add ' is-loading' when processing
         */
	    button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

	    /**
         * Return true to disable the button
         */
	    checkDisabled ()
        {
            if (this.processing) return true

            if (Object.keys(this.errors).length > 0) return true;

            return false;
        },

		/**
		 * Key to return for google-recaptcha
         * @olmbulma.test (old) 6Lfd4HMUAAAAAMZBVUIpBJI7OfwtPcbqR6kGndSE
         * @olm.test (new) 6LcvHsIZAAAAAOG0q9-1vY3uWqu0iFvUC3tCNhID
         * @production 6LciihwUAAAAADsZr0CYUoLPSMOIiwKvORj8AD9m
		 */
		computedKey ()
		{
			if (process.env.NODE_ENV === "development")
			{
                return "6LcvHsIZAAAAAOG0q9-1vY3uWqu0iFvUC3tCNhID"; // olm.test
			}

			return "6LciihwUAAAAADsZr0CYUoLPSMOIiwKvORj8AD9m" // production
		},

        /**
         * Errors object from createaccount.js
         */
        errors ()
        {
            return this.$store.state.createaccount.errors;
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

		/**
         * Check if terms are accepted
         */
		checkIsFormChecked ()
		{
			let box = document.getElementById('ConfirmToS');

			if (box.checked)
			{
				// Try to create a new user
				this.submitting = true;

				this.form.submit('post', '/register')
					// if successful
					.then(response => {
						// console.log('fourth');
						// console.log(response);
						if (this.plan == 1)
						{
							this.submitting = false;
							return alert('Congratulations! Your free account has been created. Please verify your email to activate login');
						}

						alert('Congratulations! An account has been created for you. Remember to verify your email to activate login. The window for processing your card will now load. Thank you for choosing to support the development of OpenLitterMap!');
						// this.submitting = true;
						this.loadStripe(response);

						resolve(response);
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

        /**
         *
         */
        clearErrors (key)
        {
            this.$store.commit('createCreateAccountError', key);
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

        /**
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors[key][0];
        },

        /**
         * Check if any errors exist for this key
         */
        errorExists (key)
        {
            return this.errors.hasOwnProperty(key);
        },

        /**
         *
         */
		findPlanById (id)
		{
			return this.plans.find(plan => plan.id == id);
		},

        /**
         *
         */
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
		},

        /**
         * Post request to sign a user up
         * Load stripe if a plan is selected
         */
        async submit ()
        {
            if (! this.checkbox)
            {
                alert ('Please accept the terms and conditions, and privacy policy to continue');
                return;
            }

            this.processing = true;

            this.$store.dispatch('CREATE_ACCOUNT', {
                name: this.name,
                username: this.username,
                email: this.email,
                password: this.password,
                password_confirmation: this.password_confirmation,
                recaptcha: this.g_recaptcha_response
            });

            this.processing = false;
        }
    }
}
</script>

<style>

    .captcha {
        display: flex;
        justify-content: center;
    }

    .field {
        padding-top: 0.5em;
    }

	.input-group {
		padding-bottom: 1em;
	}

	.is-danger {
		color: red;
	}

    .signup-container {
        margin: auto;
        width: 35em;
    }

</style>
