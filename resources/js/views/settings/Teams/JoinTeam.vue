<template>
    <div>
        <p v-show="error.length > 0" class="team-error">{{ $t(this.error) }}</p>

        <form @submit.prevent="submit">
            <label for="join">Join team by identifier</label>
            <span
                class="is-danger"
                v-if="errorExists('identifier')"
                v-text="getFirstError('identifier')"
            />
            <input
                class="input mb2"
                name="join"
                placeholder="Enter ID to join a team"
                required
                v-model="identifier"
                @input="clearError"
            />

            <button class="button is-medium" @click.prevent="goback">Cancel</button>
            <button :class="button" :disabled="processing">Join Team</button>
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
            processing: false
        };
    },
    computed: {
        /**
         * Add spinner when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Error string
         */
        errors ()
        {
            return this.$store.state.teams.errors;
        }

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
         * Emit event to go back to Default page
         */
        goback ()
        {
            this.$store.commit('teamComponent', 'Default');
        },

        /**
         * Dispatch action to join a team by identifier
         */
        async submit ()
        {
            this.processing = true;

            await this.$store.dispatch('JOIN_TEAM', this.identifier);

            this.processing = false;
        }
    }
}
</script>

<style scoped>
    .team-error {
        color: red;
        font-weight: 600;
        margin-bottom: 1em;
    }
</style>
