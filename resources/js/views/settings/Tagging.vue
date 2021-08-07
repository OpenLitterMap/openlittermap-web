<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">{{ $t('settings.tagging.tagging') }}</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column one-third is-offset-1">
				<div class="field">

                    <!-- Show Previous Tags -->
				    <label class="checkbox">
				    	<input type="checkbox" v-model="maps_name" />
				      	{{ $t('settings.tagging.show-previous-tags') }}
				    </label>
				    <br>
				</div>

                <br>
				<button :class="button" :disabled="processing" @click="submit">{{ $t('settings.privacy.update') }}</button>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Privacy',
    data ()
    {
        return {
            btn: 'button is-medium is-info',
            processing: false
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
         * Show personal name on any datapoints on any maps the user uploads data to
         */
        maps_name: {
            get () {
                return this.user.previous_tags;
            },
            set (v) {
                this.$store.commit('userColumnUpdate', {
                    column: 'previous_tags',
                    v
                });
            }
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },
    methods: {

        /**
         * Dispatch request to save all settings
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('SAVE_TAGGING_SETTINGS');

            this.processing = false;
        }
    }
}
</script>
