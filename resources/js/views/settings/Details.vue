<template>
	<div style="padding-left: 1em; padding-right: 1em;">
		<h1 class="title is-4">Change Personal Details</h1>
		<hr>
		<p>You can update any one of these at a time</p>
		<br>
		<div class="columns">
			<div class="column is-one-third is-offset-1">
				<form
                      method="POST"
                      @submit.prevent="submit"
                      @keydown="clearError($event.target.name)"
                >
                    <label for="name">Your name</label>

                    <span
                        class="is-danger"
                        v-if="errorExists('name')"
                        v-text="getFirstError('name')"
                    />

                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="name"
                                id="name"
                                class="input"
                                :placeholder="name"
                                v-model="name"
                            />
                            <span class="icon is-small is-left">
                                <i class="fa fa-user" />
                            </span>
                        </div>
                    </div>

                    <label for="username">Unique Identifier</label>

                    <span
                        class="is-danger"
                        v-if="errorExists('username')"
                        v-text="getFirstError('username')"
                    />

                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="input"
                                :placeholder="username"
                                v-model="username"
                            />
                            <span class="icon is-small is-left">
                                @
                            </span>
                        </div>
                    </div>

                    <label for="email">Email</label>

                    <span
                        class="is-danger"
                        v-if="errorExists('email')"
                        v-text="getFirstError('email')"
                    />

                    <div class="field mb2">
                        <div class="control has-icons-left">
                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="input"
                                :placeholder="email"
                                v-model="email"
                            />
                            <span class="icon is-small is-left">
                                <i class="fa fa-envelope" />
                            </span>
                        </div>
                    </div>

                    <button :class="button" :disabled="processing">Update Details</button>
				</form>
			</div>
		</div>
	</div>
</template>

<script>
export default {
    name: 'Details',
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
         * The users email address
         */
        email: {
            get () {
                return this.user.email;
            },
            set (v) {
                this.$store.commit('changeUserEmail', v);
            }
        },

        /**
         * Errors object created from failed request
         */
        errors ()
        {
            return this.$store.state.user.errors;
        },

        /**
         * The users name
         */
        name: {
            get () {
                return this.user.name;
            },
            set (v) {
                this.$store.commit('changeUserName', v);
            }
        },

        /**
         * The currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        },

        /**
         * The users username
         */
        username: {
            get () {
                return this.user.username;
            },
            set (v) {
                this.$store.commit('changeUserUsername', v);
            }
        }
    },
    methods: {

        /**
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors[key]) this.$store.commit('clearCreateAccountError', key);
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
         * Update the users personal details (Name, Username, Email)
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_DETAILS');

            this.processing = false;
        }
    }
}
</script>
