<template>
    <div class="mb-6">
        <h1 class="title is-4">
            {{ $t('settings.emails.email-subscription') }}
        </h1>
        <hr>
        <p>{{ $t('settings.emails.we-send-updates') }}</p>
        <p>{{ $t('settings.emails.subscribe') }}</p>
        <br>
        <label class="checkbox">
            <input v-model="computedPresence" type="checkbox" @click="toggle">
            {{ $t('settings.emails.subscribe-to-our-emails') }}
        </label>
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
            return this.$store.state.user.user.emailsub ? 'color: green' : 'color: red';
        },

        /**
         *
         */
        computedPresence ()
        {
            return !!this.$store.state.user.user.emailsub;
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
};
</script>
