<template>
	<div class="container">
		<div class="columns" style="padding-top: 5em; margin-bottom: 2em;">
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
				</div>
			</div>
		</div>

		<div class="signup-container">

            <h3 class="title is-3">Create your account</h3>

            <form
                method="post"
                @submit.prevent="submit"
                @keydown="clearError($event.target.name)"
            >
                <!-- NAME -->
                <label for="name">Name</label>

                <span
                    class="is-danger"
                    v-if="errorExists('name')"
                    v-text="getFirstError('name')"
                />
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

                <span
                    class="is-danger"
                    v-if="errorExists('username')"
                    v-text="getFirstError('username')"
                />

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

                <span
                    class="is-danger"
                    v-if="errorExists('email')"
                    v-text="getFirstError('email')"
                />

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

                <span
                    class="is-danger"
                    v-if="errorExists('password_confirmation')"
                    v-text="getFirstError('password_confirmation')"
                />

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
                     <span class="is-danger" v-if="errorExists('g_recaptcha_response')" v-text="getFirstError('g_recaptcha_response')"></span>

                    <vue-recaptcha
                        :sitekey="computedKey"
                        v-model="g_recaptcha_response"
                        :loadRecaptchaScript="true"
                        @verify="recaptcha"
                    />
                </div>
                <br>
                <div style="text-align: center; padding-bottom: 1em;">

                    <button :class="button" :disabled="checkDisabled">Sign up</button>

                    <p>Note: If you do not recieve the verification e-mail in your inbox, please check your spam/junk folder.</p>
                </div>
            </form>
		</div>
	</div>
</template>

<script>
import VueRecaptcha from 'vue-recaptcha'

export default {
	name: 'CreateAccount',
	props: ['plan'],
    components: { VueRecaptcha },
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
            btn: 'button is-medium is-primary mb1',
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
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors[key]) this.$store.commit('clearCreateAccountError', key);
        },

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
		loadStripe ()
		{
            let stripe = Stripe(process.env.MIX_STRIPE_KEY);

            console.log(stripe);

            stripe.redirectToCheckout({
                lineItems: [{
                    // Define the product and price in the Dashboard first, and use the price
                    // ID in your client-side code.
                    price: this.plans[this.planInt -1].name,
                    quantity: 1
                }],
                mode: 'subscription',
                successUrl: 'https://www.example.com/success',
                cancelUrl: 'https://www.example.com/cancel'
            });

            // Configure Stripe step 1
			// called handler in the docs
			// this.stripe = StripeCheckout.configure({
			// 	key: OLM.stripeKey,
			// 	image: "https://stripe.com/img/documentation/checkout/marketplace.png",
			// 	locale: "auto",
			// 	panelLabel: "Subscribe For",
			// 	email: this.email,
            //
			// 	// RESPONSE FROM STRIPE MODAL INPUT
			// 	// Submit the stripe form
			// 	// The callback to invoke the payment once the checkout process has been complete
			// 	// func ( token, args )
			// 	// token.id can be used to create a charge or can be attached to a customer
			// 	// token.email contains the email addresses entered by the user
			// 	// args contain billing + shipping info if enabled
            //
			// 	// Callback 1 from Stripe with token object -> user has been created?
			// 	// get a token object in response to the forms ajax request
			// 	token: (token) => { // use the arrow ES15 syntax to make this local
			// 		// input the token id into the form for submission
			// 		this.stripeToken = token.id,
			// 		this.stripeEmail = token.email;
			// 		// console.log(this);
			// 		axios.post('/join', this.$data)
			// 			// if 200
			// 			 .then(response => {
			// 				this.submitting = false;
			// 				this.form.reset();
			// 				alert('Congratulations! You are now subscribed. Remember to verify your email to enable login.');
			// 			 })
			// 			 // once alert closes, the response comes in
			// 			 .catch(error => {
			// 				// console.log(error);
			// 				this.submitting = false;
			// 				this.form.reset();
			// 				this.status = 'Sorry, but your card was declined. A free account was created for you but you will need to log in after verifying your email to update your account subscription in the settings panel.';
			// 			 });
			// 	} // end token
			// });
            //
			// // Stripe setup 2: Open the modal when
			// this.stripe.open({
			// 	name: plan.name,
			// 	description: plan.description,
			// 	zipCode: false,
			// 	amount: plan.price
			// });
		},

        /**
         * Google re-captcha has been verified
         */
        recaptcha (response)
        {
            this.g_recaptcha_response = response;
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

            await this.$store.dispatch('CREATE_ACCOUNT', {
                name: this.name,
                username: this.username,
                email: this.email,
                password: this.password,
                password_confirmation: this.password_confirmation,
                recaptcha: this.g_recaptcha_response,
                plan: this.planInt
            });

            this.processing = false;

            console.log('after submit');

            if (this.planInt > 1)
            {
                this.loadStripe();
            }

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
