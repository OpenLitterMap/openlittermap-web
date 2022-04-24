<template>
    <div style="padding-left: 1em; padding-right: 1em;">
        <h1 class="title is-4">{{ $t('settings.details.change-details') }}</h1>
        <hr>
        <br>
        <div class="columns">
            <div class="column is-one-third is-offset-1">

                <form @submit.prevent="submit" @keydown="clearError($event.target.name)">

                    <!-- The users name -->
                    <label for="twitter">Twitter</label>
                    <div class="field">
                        <div class="control has-icons-left">
                            <input
                                type="text"
                                name="twitter"
                                id="twitter"
                                class="input"
                                placeholder="Twitter URL"
                                v-model="twitter"
                            />
                            <span class="icon is-small is-left">
                                <i class="fa fa-user"/>
                            </span>
                        </div>

                        <p
                            class="help is-danger is-size-6"
                            v-if="errorExists('social_twitter')"
                            v-text="getFirstError('social_twitter')"
                        />
                    </div>

                    <button :class="button" :disabled="processing">Update</button>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'Social',
    data ()
    {
        return {
            btn: 'button is-medium is-info',
            processing: false,
            twitter: null,
        };
    },
    mounted ()
    {
        this.$store.commit('errors', {});

        this.twitter = this.user.settings.social_twitter;
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
         * Errors object created from failed request
         */
        errors ()
        {
            return this.$store.state.user.errors;
        },

        /**
         * The currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        },
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

            await this.$store.dispatch('UPDATE_SETTINGS', {
                social_twitter: this.twitter
            });

            this.processing = false;
        }
    }
};
</script>
