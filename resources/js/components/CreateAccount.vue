<template>
	<div class="container">
		<div class="call-container">
            <div class="has-text-centered">
                <strong>{{ $t('auth.subscribe.crowdfunding-message') }}</strong>

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

            <h3 class="title is-3">{{ $t('auth.subscribe.form-create-account') }}</h3>

            <form
                method="post"
                @submit.prevent="submit"
                @keydown="clearError($event.target.name)"
            >
                <div class="field">
                    <!-- NAME -->
                    <label class="label" for="name">{{ $t('auth.subscribe.form-field-name') }}</label>

                    <div class="control has-icons-left">
                        <input
                            name="name"
                            type="text"
                            class="input"
                            :class="errorExists('name') ? 'is-danger' : ''"
                            placeholder="Your full name"
                            required
                            v-model="name"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-user" />
                        </span>
                        <p
                            class="help is-danger"
                            v-if="errorExists('name')"
                            v-text="getFirstError('name')"
                        />
                   </div>
                </div>

                <div class="field">
                    <!-- USERNAME OR ORGANISATION -->
                    <label class="label" for="username">{{ $t('auth.subscribe.form-field-unique-id') }}</label>

                    <div class="control has-icons-left">
                        <input
                            name="username"
                            class="input"
                            :class="errorExists('username') ? 'is-danger' : ''"
                            placeholder="Unique Username or Organisation"
                            required
                            type="text"
                            v-model="username"
                        />
                        <span class="icon is-small is-left">
                            @
                        </span>
                        <p
                            class="help is-danger"
                            v-if="errorExists('username')"
                            v-text="getFirstError('username')"
                        />
                    </div>
                </div>

                <div class="field">
                    <!-- EMAIL -->
                    <label class="label" for="email">{{ $t('auth.subscribe.form-field-email') }}</label>

                    <div class="control has-icons-left">
                        <input
                            name="email"
                            class="input"
                            :class="errorExists('email') ? 'is-danger' : ''"
                            type="email"
                            placeholder="you@email.com"
                            required
                            v-model="email"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-envelope" />
                        </span>
                        <p
                            class="help is-danger"
                            v-if="errorExists('email')"
                            v-text="getFirstError('email')"
                        />
                    </div>
                </div>

                <div class="field">
                    <!-- PASSWORD -->
                    <label class="label" for="password">{{ $t('auth.subscribe.form-field-password') }}</label>

                    <div class="control has-icons-left">
                        <input
                            class="input"
                            :class="errorExists('password') ? 'is-danger' : ''"
                            id="password"
                            name="password"
                            type="password"
                            placeholder="Create a strong password"
                            required
                            v-model="password"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-key" />
                        </span>
                        <p
                            class="help is-danger"
                            v-if="errorExists('password')"
                            v-text="getFirstError('password')"
                        />
                    </div>
                </div>

                <div class="field">
                    <!-- CONFIRM PASSWORD -->
                    <label class="label" for="password_confirmation">{{ $t('auth.subscribe.form-field-pass-confirm') }}</label>

                    <div class="control has-icons-left">
                        <input
                            class="input"
                            :class="errorExists('password_confirmation') ? 'is-danger' : ''"
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Confirm your Password"
                            required
                            v-model="password_confirmation"
                        />
                        <span class="icon is-small is-left">
                            <i class="fa fa-refresh"/>
                        </span>
                        <p
                            class="help is-danger"
                            v-if="errorExists('password_confirmation')"
                            v-text="getFirstError('password_confirmation')"
                        />
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
                    <label for="ConfirmToS" v-html="$t('auth.subscribe.form-account-conditions')">

                    </label>
                </p>

                <div class="captcha">
                    <div>
                        <vue-recaptcha
                            sitekey="6Le9FtwcAAAAAMOImuwEoOYssOVdNf7dfI2x8XZh"
                            v-model="g_recaptcha_response"
                            :loadRecaptchaScript="true"
                            @verify="recaptcha"
                        />
                    </div>
                    <p
                        class="help is-danger"
                        v-if="errorExists('g-recaptcha-response')"
                        v-text="getFirstError('g-recaptcha-response')"
                    />
                </div>
                <br>
                <div style="text-align: center; padding-bottom: 1em;">

                    <button
                        class="button is-medium is-primary mb1"
                        :class="processing ? 'is-loading' : ''"
                        :disabled="checkDisabled"
                    >{{ $t('auth.subscribe.form-btn') }}</button>

                    <p>{{ $t('auth.subscribe.create-account-note') }} </p>
                </div>
            </form>
		</div>
	</div>
</template>

<script>
import VueRecaptcha from 'vue-recaptcha'

export default {
	name: 'CreateAccount',
	props: [
        'plan'
    ],
    components: {
        VueRecaptcha
    },
	created () {
		if (this.plan)
		{
			if (this.plan === 'startup') this.planInt = 2;
			else if (this.plan === 'basic') this.planInt = 3;
			else if (this.plan === 'advanced') this.planInt = 4;
			else if (this.plan === 'pro') this.planInt = 5;
		}
	},
	data () {
		return {
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
                query: {
                    plan
                }
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
                g_recaptcha_response: this.g_recaptcha_response,
                plan: this.planInt,
                plan_id
            });

            this.password_confirmation = '';

            this.processing = false;
        },
    }
}
</script>

<style>

    .captcha {
        display: flex;
        align-items: center;
        flex-direction: column;
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
