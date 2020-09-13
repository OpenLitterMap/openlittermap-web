<template>
	<div style="padding: 0 1em;">
		<h1 class="title is-4">Toggle Email Subscription</h1>
		<hr>
		<p>Occasionally, we send out emails with updates and good news.</p>
		<p>You can subscribe or unsubscribe to our emails here.</p>
		<br>
		<p><b>Current Status:</b></p>
        <p><b :style="color">{{ this.computedPresence }}</b></p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
                <button :class="button" :disabled="processing" @click="toggle">Toggle Email Subscription</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Emails',
    data ()
    {
        return {
            processing: false
        };
    },
    computed: {

        /**
         * Dynamic button class
         */
        button ()
        {
            return this.processing ? 'button is-info is-loading' : 'button is-info';
        },

        /**
         *
         */
        color ()
        {
            return this.$store.state.user.user.emailsub ? "color: green" : "color: red";
        },

        /**
         *
         */
        computedPresence ()
        {
            return this.$store.state.user.user.emailsub ? "Subscribed" : "Unsubscribed";
        }
    },
    methods: {

        /**
         *
         */
        async toggle ()
        {
            this.processing = true;

            this.$store.dispatch('TOGGLE_EMAIL_SUBSCRIPTION');

            this.processing = false;
        }
    }
}
</script>
