<template>
    <div>
        <h1 class="title is-4">
            {{ $t('settings.details.change-details') }}
        </h1>
        <hr>
        <div class="mb-6">
            <form @submit.prevent="submit" @keydown="clearError($event.target.name)">
                <!-- The users name -->
                <label for="name">{{ $t('settings.details.your-name') }}</label>

                <span
                    v-if="errorExists('name')"
                    class="error"
                    v-text="getFirstError('name')"
                />

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            id="name"
                            v-model="name"
                            type="text"
                            name="name"
                            class="input"
                            :placeholder="name"
                            required
                        >
                        <span class="icon is-small is-left">
                            <i class="fa fa-user" />
                        </span>
                    </div>
                </div>

                <!-- The users username-->
                <label for="username">{{ $t('settings.details.unique-id') }}</label>

                <span
                    v-if="errorExists('username')"
                    class="error"
                    v-text="getFirstError('username')"
                />

                <div class="field">
                    <div class="control has-icons-left">
                        <input
                            id="username"
                            v-model="username"
                            type="text"
                            name="username"
                            class="input"
                            :placeholder="username"
                            required
                        >
                        <span class="icon is-small is-left">
                            @
                        </span>
                    </div>
                </div>

                <!-- The users email -->
                <label for="email">{{ $t('settings.details.email') }}</label>

                <span
                    v-if="errorExists('email')"
                    class="error"
                    v-text="getFirstError('email')"
                />

                <div class="field mb2">
                    <div class="control has-icons-left">
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            name="email"
                            class="input"
                            :placeholder="email"
                            required
                        >
                        <span class="icon is-small is-left">
                            <i class="fa fa-envelope" />
                        </span>
                    </div>
                </div>
                <div class="col-md-12 has-text-centered">
                    <button :class="button" :disabled="processing">
                        {{ $t('settings.details.update-details') }}
                    </button>
                </div>
            </form>
        </div>
        <emails />
        <presence />
    </div>
</template>

<script>
import Emails from './Emails.vue';
import Presence from './Presence.vue';

export default {
    name: 'Details',
    components: { Emails, Presence },
    data ()
    {
        return {
            btn: 'button is-normal is-info',
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
         * Update the users personal details (Name, Username, Email)
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('UPDATE_DETAILS');

            this.processing = false;
        }
    }
};
</script>
