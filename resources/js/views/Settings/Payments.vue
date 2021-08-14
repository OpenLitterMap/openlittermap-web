<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">{{ $t('settings.payments.finance') }}</h1>
		<hr>
		<br>
		<div class="columns">

            <loading v-if="loading" :active.sync="loading" :is-full-page="true" />

            <div v-else class="column one-third is-offset-1">

                <div v-if="! check_for_stripe_id">
                    <p>{{ $t('settings.payments.help') }}</p>

                    <ul>
                        <li>- {{ $t('settings.payments.support') }}</li>
                        <li>- {{ $t('settings.payments.help-costs') }}</li>
                        <li>- {{ $t('settings.payments.help-hire') }}</li>
                        <li>- {{ $t('settings.payments.help-produce') }}</li>
                        <li>- {{ $t('settings.payments.help-write') }}</li>
                        <li>- {{ $t('settings.payments.help-outreach') }}</li>
                        <li>- {{ $t('settings.payments.help-incentivize') }}</li>
                        <li>- {{ $t('settings.payments.more-soon') }}</li>
                    </ul>

                    <!-- Show list of plans -->

                    <button class="button is-medium is-primary" @click="subscribe">{{ $t('settings.payments.click-to-support') }}</button>
                </div>

                <!-- The user has already subscribed -->
                <div v-else>

                    <div v-if="subscription.stripe_status === 'active'">

                        <p>You are currently subscribed to the <strong class="green">{{ subscription.name }}</strong> plan</p>
                        <p class="mb1">Helping us with <strong class="green">€{{ current_plan.price / 100 }}</strong> per month</p>
                        <p>Thank you for helping the development of OpenLitterMap!</p>
                        <p class="mb1">You can change or cancel your subscription at any time.</p>

                        <button @click="cancel_active_subscription" class="button is-medium is-danger">Cancel Subscription</button>
                    </div>

                    <!-- Cancelled subscription -->
                    <div v-else>

                        <p class="mb1">You have unsubscribed from <strong class="green">{{ subscription.name }}</strong></p>
                        <p class="mb1">Thank you for supporting the development of OpenLitterMap</p>


                        <p>Please contact us if you would like to resubscribe, or else create a new account. Thanks!</p>
                        <!-- Show list of plans -->
<!--                        <div class="control mb1">-->
<!--                            <div class="select">-->
<!--                                <select v-model="plan">-->
<!--                                    <option v-for="plan in plans" :value="plan.name">-->
<!--                                        {{ plan.name }} &mdash; €{{ plan.price / 100 }}-->
<!--                                    </option>-->
<!--                                </select>-->
<!--                            </div>-->
<!--                        </div>-->

<!--                        <button class="button is-medium is-primary" @click="resubscribe">Click here to resubscribe</button>-->
                    </div>
                </div>
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

        if (this.$store.state.plans.plans.length === 0)
        {
            await this.$store.dispatch('GET_PLANS');
        }

        if (this.$store.state.user.user.stripe_id)
        {
            await this.$store.dispatch('GET_USERS_SUBSCRIPTIONS');
        }

        this.loading = false;
    },
	data ()
    {
		return {
		    loading: true,
            plan: 'Startup'
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
         * The current plan the user is subscribed to, if any
         */
        current_plan ()
        {
            return this.plans.find(plan => plan.name === this.subscription.name);
        },

        /**
         * Array of plans from the database
         */
        plans ()
        {
            return this.$store.state.plans.plans;
        },

        /**
         * If user.stripe_id exists, the active/inactive plan is here
         */
        subscription ()
        {
            return this.$store.state.subscriber.subscription;
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
         * The user already has a Stripe customer account / user.stripe_id and wants to resubscribe
         */
        async resubscribe ()
        {
            await this.$store.dispatch('RESUBSCRIBE', this.plan);
        },

        /**
         * The user wants to sign up for a monthly subscription
         */
		subscribe ()
        {
            console.log('todo - load stripe');
		}
	}
}
</script>
