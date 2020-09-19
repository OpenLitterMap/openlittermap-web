<template>
	<div class="container">
		<div class="call-container">
            <div class="has-text-centered">
                <strong>Please consider supporting our work by crowdfunding OpenLitterMap with as little as 6 cents a day with a monthly subscription to help grow and develop this important platform.</strong>

                <div class="control mt2">
                    <div class="select">
                        <select v-model="planInt" @change="changeUrl">
                            <option v-for="plan in plans" :value="plan.id">
                                {{ plan.name }} &mdash; €{{ plan.price / 100 }}
                            </option>
                        </select>
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
                <p class="mtb1">
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
			if (this.plan === 'startup') this.planInt = 2;
			else if (this.plan === 'basic') this.planInt = 3;
			else if (this.plan === 'advanced') this.planInt = 4;
			else if (this.plan === 'pro') this.planInt = 5;
		}
	},
	data ()
	{
		return {
            btn: 'button is-medium is-primary mb1',
            planInt: 1,
            processing: false,
            // REGISTRATION
            name: '',
            username: '',
            email: '',
            password: '',
            checkbox: false,
            password_confirmation: '',
            g_recaptcha_response: '',
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

            // todo - disable the button when there are errors
            // and disable it when all errors have been cleared
            // if (Object.keys(this.errors).length > 0) return true;

            return false;
        },

		/**
		 * Key to return for google-recaptcha
         * @olmbulma.test (old) 6Lfd4HMUAAAAAMZBVUIpBJI7OfwtPcbqR6kGndSE
         * @olm.test (new) 6LcvHsIZAAAAAOG0q9-1vY3uWqu0iFvUC3tCNhID
         * @production 6LciihwUAAAAADsZr0CYUoLPSMOIiwKvORj8AD9m // todo - put this on .env
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
         * Errors object from plans
         */
        errors ()
        {
            return this.$store.state.plans.errors;
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
         * Update query string in the url bar
         */
        changeUrl (e)
        {
            let plan = this.plans[e.target.value -1].name.toLowerCase();

            this.$router.push({
                path: 'join',
                query: { plan }
            });
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
         * Google re-captcha has been verified
         */
        recaptcha (response)
        {
            this.g_recaptcha_response = response;
        },

        showStripe ()
        {
            this.$store.commit('showModal', {
                type: 'CreditCard'
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

            let plan_id = this.plans[this.planInt -1].plan_id;

            await this.$store.dispatch('CREATE_ACCOUNT', {
                name: this.name,
                username: this.username,
                email: this.email,
                password: this.password,
                password_confirmation: this.password_confirmation,
                recaptcha: this.g_recaptcha_response,
                plan: this.planInt,
                plan_id
            });

            this.processing = false;
        },
    }
}
</script>

<style>

    .captcha {
        display: flex;
        justify-content: center;
    }

    .call-container {
        padding-top: 5em;
        margin-bottom: 2em;
        margin-left: auto;
        margin-right: auto;
        max-width: 50em;
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

    /* Small screens */
    @media only screen and (max-width: 600px)
    {
        .call-container {
            padding: 2em 1em;
            margin-bottom: 0 !important;
        }

        .signup-container {
            width: 20em;
        }
    }

</style>
