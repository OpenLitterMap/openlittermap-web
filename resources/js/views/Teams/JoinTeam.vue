<template>
    <div class="has-text-center">
        <form @submit.prevent="submit">
            <label for="join" class="mb2">Enter an identifier to join a team.</label>
            <span
                v-if="errorExists('identifier')"
                class="is-danger"
                v-text="getFirstError('identifier')"
            />
            <input v-model="identifier"
                   class="input mb2 mt2"
                   name="join"
                   placeholder="Enter ID to join a team"
                   required
                   @input="clearError"
            >

            <button :class="button" :disabled="processing">
                Join Team
            </button>
        </form>
    </div>
</template>

<script>
export default {
    name: 'JoinTeam',
    data ()
    {
        return {
            btn: 'button is-medium is-primary',
            identifier: '',
            processing: false,
        };
    },
    computed: {
        /**
         * Show spinner when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Error object from TeamsController
         */
        errors ()
        {
            return this.$store.state.teams.errors;
        },

    },
    methods: {
        /**
         * Clear an error with this key
         */
        clearError (key)
        {
            if (this.errors[key]) this.$store.commit('clearTeamsError', key);
        },

        /**
         * Check if any errors exist for this key
         */
        errorExists (key)
        {
            return this.errors.hasOwnProperty(key);
        },

        /**
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors[key][0];
        },

        /**
         * Dispatch action to join a team by identifier
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('JOIN_TEAM', this.identifier);

            this.processing = false;
        },
    },
};
</script>

<style scoped>

.team-error {
    color: red;
    font-weight: 600;
    margin-bottom: 1em;
}
</style>
