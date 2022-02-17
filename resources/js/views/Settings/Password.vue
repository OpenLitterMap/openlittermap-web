<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4"> {{ $t('settings.password.change-password') }}</h1>
		<hr>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">

                <!-- Change password -->
                <form method="POST" @submit.prevent="submit" @keydown="clearError($event.target.name)">

                    <!-- Old Password -->
                    <label for="oldpassword"> {{ $t('settings.password.enter-old-password') }}</label>

                    <span
                        v-if="errorExists('oldpassword')"
                        v-text="getFirstError('oldpassword')"
                        class="error"
                    />

                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="password"
                                name="oldpassword"
                                class="input"
                                placeholder="*********"
                                v-model="oldpassword"
                                required
                            >
                            <span class="icon is-small is-left">
                                <i class="fa fa-key" />
                            </span>
                        </div>
                    </div>

                    <!-- New Password -->
                    <label for="password">{{ $t('settings.password.enter-new-password') }}</label>

                    <span
                        v-if="errorExists('password')"
                        v-text="getFirstError('password')"
                        class="error"
                    />
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="input"
                                :placeholder="translate('password.enter-strong-password')"
                                v-model="password"
                                required
                            />
                            <span class="icon is-small is-left">
                                <i class="fa fa-key" />
                            </span>
                        </div>
                    </div>

                    <!-- Repeat Password -->
                    <label for="password_confirmation">{{ $t('settings.password.confirm-new-password') }}</label>

                    <span
                        v-if="errorExists('password_confirmation')"
                        v-text="getFirstError('password_confirmation')"
                        class="error"
                    />

                    <div class="field mb2">
                        <div class="control has-icons-left">
                            <input
                                type="password"
                                name="password_confirmation"
                                class="input"
                                :placeholder="translate('password.repeat-strong-password')"
                                v-model="password_confirmation"
                                required
                            />
                            <span class="icon is-small is-left">
                                <i class="fa fa-key" />
                            </span>
                        </div>
                    </div>

                    <div class="col-md-12" style="text-align: center;">
                        <button :class="button" :disabled="processing">{{ $t('settings.password.update-password') }}</button>
                    </div>
                </form>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Password',
    data ()
    {
        return {
            processing: false,
            oldpassword: '',
            password: '',
            password_confirmation: '',
            btn: 'button is-medium is-info'
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
         *
         */
        errors ()
        {
            return this.$store.state.user.errors;
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
         * Request to update the users password
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('CHANGE_PASSWORD', {
                oldpassword: this.oldpassword,
                password: this.password,
                password_confirmation: this.password_confirmation
            });

            this.processing = false;
        },
        /**
         * Get translated text 
         */
        translate (text)
        {
            return this.$t('settings.' + text);
        }
    },
}
</script>
