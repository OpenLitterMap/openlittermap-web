<template>
    <div class="mb-6">
        <h1 class="title is-4">
            {{ $t('settings.presence.do-you-pickup') }}
        </h1>
        <hr>
        <p class="mb1">
            {{ $t('settings.presence.save-def-settings') }}
        </p>
        <p class="mb1">
            {{ $t('settings.presence.change-value-of-litter') }}
        </p>

        <br>
        <label class="checkbox">
            <input :value="picked_up" type="checkbox" @click="toggle">
            {{ text }}
        </label>
    </div>
</template>

<script>
export default {
    name: 'Presence',
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
         * Todo: move the value to the new user_settings table and use the column "picked_up"
         *
         * if items_remaining is true, the litter is not picked up
         */
        picked_up ()
        {
            return ! this.$store.state.user.user.items_remaining;
        },

        /**
         *
         */
        text ()
        {
            return 'Your litters will be marked as picked up.';
        }
    },
    methods: {

        /**
         * Dispatch action to save default setting value
         */
        async toggle ()
        {
            this.processing = true;
            await this.$store.dispatch('TOGGLE_LITTER_PICKED_UP_SETTING');

            this.processing = false;
        }
    }
};
</script>
