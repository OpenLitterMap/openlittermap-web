<template>
	<div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.account.delete-account') }}</h1>
        <hr>
        <p>{{ $t('settings.account.delete-account') }}</p>
        <br>
        <div class="columns">
            <div class="column is-one-third is-offset-1">
                <div class="row">
                    <form method="POST"
                          @submit.prevent="submit"
                          @keydown="clearError($event.target.name)"
                    >
                        <label for="password">{{ $t('settings.account.delete-account?') }}</label>
                        <span
                            class="is-danger"
                            v-if="errorExists('password')"
                            v-text="getFirstError('password')"
                        />
                        <div class="field">
                            <div class="control">
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    placeholder="******"
                                    v-model="password"
                                    required
                                    class="input"
                                />
                            </div>
                        </div>

                        <button :class="button">{{ $t('settings.account.enter-password') }}</button>
                    </form>
                </div>
            </div>
        </div>
	</div>
</template>

<script>
export default {
    name: 'Account',
    async created ()
    {
        await this.$store.dispatch('GET_PLANS');
    },
	data ()
    {
		return {
		    btn: 'button is-danger',
			processing: false,
            password: ''
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
         * Errors object from user.js
         */
        errors ()
        {
            return this.$store.state.user.errors;
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
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors[key]) this.$store.commit('deleteUserError', key);
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
         * Submit a request to delete the users account
         */
        async submit ()
        {
			this.processing = true;

            await this.$store.dispatch('DELETE_ACCOUNT', this.password);

            this.processing = false;
            this.password = '';
		}
	}
}
</script>
