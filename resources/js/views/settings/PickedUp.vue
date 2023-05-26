<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">{{ $t('settings.presence.do-you-pickup') }}</h1>
		<hr>
        <p class="mb1">{{ $t('settings.presence.save-def-settings') }}</p>
        <p class="mb1">{{ $t('settings.presence.change-value-of-litter') }}</p>
		<p>{{ $t('settings.presence.status') }}</p>

        <br>
        <p><b>{{ $t('settings.presence.toggle-presence') }}:</b></p>
        <p><b :style="picked_up ? 'color: green' : 'color: red'">{{ this.text }}</b></p>
		<br>

		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<div class="row">
					<button
                        :class="button"
                        :disabled="processing"
                        @click="toggle"
                    >{{ $t('settings.presence.pickup?') }}</button>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'PickedUp',
    data () {
        return {
            processing: false
        };
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
            return this.picked_up
                ? "Your litter will be logged as picked up."
                : "Your litter is logged as not picked up.";
        }
    }
}
</script>
