<template>
	<div style="padding: 0 1em;">
		<h1 class="title is-4">{{ $t('settings.emails.toggle-email') }}</h1>
		<hr>
		<p>{{ $t('settings.emails.we-send-updates') }}</p>
		<p>{{ $t('settings.emails.subscribe') }}</p>
		<br>
		<p><b>{{ $t('settings.emails.current-status') }}:</b></p>
        <p><b :style="color">{{ this.computedPresence }}</b></p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
                <button :class="button" :disabled="processing" @click="toggle">{{ $t('settings.emails.change-status') }}</button>
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
