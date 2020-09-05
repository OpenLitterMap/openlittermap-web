<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Finance the development of OpenLitterMap</h1>
		<hr>
		<br>
		<div class="columns">

            <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

            <div v-else class="column one-third is-offset-1">

                <div v-if="! check_for_stripe_id">
                    <p>Create first subscription</p>
                </div>

                <!-- The user has already subscribed -->
                <div v-else>

                    <div v-if="current_plan.active">

                        <p>You are currently subscribed to the <strong class="green">{{ current_plan.nickname }}</strong> plan</p>
                        <p class="mb1">For €{{ current_plan.amount / 100 }} per month</p>
                        <p>Thank you for helping the development of OpenLitterMap!</p>
                        <p class="mb1">You can change or cancel your subscription at any time.</p>

                        <button @click="cancel_active_subscription" class="button is-medium is-danger">Cancel Subscription</button>
                    </div>

                    <!-- Inactive subscription -->
                    <div v-else>

                        <p>Inactive plan</p>
                    </div>
                </div>

<!--				<div v-if="has_valid_subscription">-->

<!--					<p>Current Subscription: <strong>{{ this.plan }}</strong></p>-->
<!--                    <p>Status: Active.</p>-->
<!--                    <p>Do you want to cancel your subscription?</p>-->

<!--                    <form method="POST" @submit.prevent="cancelStripe">-->
<!--                        <input-->
<!--                            type="password"-->
<!--                            name="password"-->
<!--                            id="password"-->
<!--                            placeholder="******"-->
<!--                            v-model="cancelform.password"-->
<!--                            required-->
<!--                            @keydown="clearErrors"-->
<!--                        />-->
<!--                        <button class="button is-danger">Cancel My Subscription</button>-->
<!--                    </form>-->
<!--				</div>-->

<!--				&lt;!&ndash; Not Valid &ndash;&gt;-->
<!--				<div v-else>-->

<!--					&lt;!&ndash; Todo - fix this &ndash;&gt;-->
<!--					&lt;!&ndash; <div v-if="this.user">-->
<!--						<p>Your previous subscription and the grace period for 1-click reactivation has ended. Would you like to sign up again?</p>-->
<!--						<br>-->
<!--					</div> &ndash;&gt;-->

<!--					&lt;!&ndash; <div v-else> &ndash;&gt;-->
<!--						<p>Time to sign up?</p>-->
<!--					&lt;!&ndash; </div> &ndash;&gt;-->

<!--					<div class="columns">-->
<!--						<div class="column is-one-quarter">-->
<!--							<br>-->
<!--							<div class="control">-->
<!--								<div class="select">-->
<!--									<select name="plans" v-model="myplan">-->
<!--										<option v-for="plan in this.plans" id="options" :value="plan.id">-->
<!--											{{ plan.name }} &mdash; €{{ plan.price / 100}}-->
<!--										</option>-->
<!--									</select>-->
<!--								</div>-->
<!--							</div>-->
<!--							<br>-->
<!--							<button class="button is-large is-success" @click="subscribe">I want to subscribe.</button>-->
<!--							<br>-->
<!--							<br>-->
<!--							<p>We use <a href="https://stripe.com">Stripe</a> to securely handle payments & subscriptions over an encrypted https network.</p>-->
<!--						</div>-->

<!--						<div class="column is-half is-offset-1">-->
<!--							<p><strong style="color: #3273dc;">Free</strong></p>-->
<!--							<ul>-->
<!--								<li>- Upload 1000 images per day</li>-->
<!--								<li>- All users can earn Littercoin</li>-->
<!--								<li>- Your free account costs us money!</li>-->
<!--							</ul>-->
<!--							<br>-->
<!--							<p><strong style="color: #22C65B;">Please help finance the development of OpenLitterMap with a monthly subscription starting at €5 per month (€0.06 per day)</strong></p>-->
<!--							<ul>-->
<!--								<li>- Support Open Data on Plastic Pollution</li>-->
<!--								<li>- Help cover our costs</li>-->
<!--								<li>- Hire developers, designers & graduates</li>-->
<!--								<li>- Produce videos</li>-->
<!--								<li>- Write papers</li>-->
<!--								<li>- Conferences & outreach</li>-->
<!--								<li>- Incentivize data collection with Littercoin</li>-->
<!--								<li>- More exciting updates coming soon</li>-->
<!--							</ul>-->
<!--						</div>-->
<!--					</div>-->
<!--				</div>-->
			</div>
		</div>
	</div>
</template>

<script>
import Loading from 'vue-loading-overlay'
import 'vue-loading-overlay/dist/vue-loading.css'

export default {
    name: 'Payments',
    components: { Loading },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_PLANS');

        if (this.$store.state.user.user.stripe_id)
        {
            await this.$store.dispatch('CHECK_CURRENT_SUBSCRIPTION');
        }

        this.loading = false;
    },
	data ()
    {
		return {
		    loading: true,
            password: '',
			error: '',
			msg_password: '',
			submitting: false,
			msg_status: '',
			myplan: 1,
			stripeToken: '',
			stripeEmail: '',
			stripe: null,
            has_valid_subscription: false
		};
	},
    computed: {

        /**
         * Check for stripe_id on user
         */
        check_for_stripe_id ()
        {
            return this.$store.state.user.user.stripe_id;
        },

        /**
         * If user.stripe_id exists, the active/inactive plan is here
         */
        current_plan ()
        {
            return this.current_subscription.plan;
        },

        /**
         * If user.stripe_id exists, the current active/inactive subscription is here
         */
        current_subscription ()
        {
            return this.$store.state.subscriber.current_subscription;
        },

        /**
         * Array of plans from the database
         */
        plans ()
        {
            return this.$store.state.createaccount.plans;
        }
    },
    methods: {

        /**
         * The user wants to cancel their monthly subscription
         */
        async cancel_active_subscription ()
        {
            await this.$store.dispatch('DELETE_ACTIVE_SUBSCRIPTION');
        },

        /**
         * Cancel subscription
         */
        cancelStripe ()
        {
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

        /**
         *
         */
        clearErrors ()
        {
            console.log('todo - clear errors');
        },

        /**
         * Reactiviate an existing subscription
         */
        reactivate ()
        {
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
        },

        /**
         * The user wants to sign up for a monthly subscription
         */
		subscribe ()
        {
			if (this.myplan == 1)
			{
				return alert("You are already on the free plan. Please consider financing the development Open Litter Map with a monthly subscription starting as little as ~6 cents / p a day. You can unsubscribe or resubscribe at a click! It's easy.");
			}

			let newplan = this.plans[this.myplan - 1];

            console.log('todo - load stripe');
		}
	}
}
</script>
