<template>
    <div class="jtc">
        <h1 class="title is-2">Join a Team</h1>

        <div class="columns mt3">

            <div class="column is-one-third">
                <p class="mb1">Enter an identifier to join a team.</p>
            </div>

            <div class="column is-half card p2">
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

                    <div class="has-text-right">
                        <button :class="button" :disabled="processing">Join Team</button>
                    </div>
                </form>
            </div>
        </div>
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

    .jtc {
        margin-top: 1em;
        margin-left: 5em;
    }

    .team-error {
        color: red;
        font-weight: 600;
        margin-bottom: 1em;
    }
</style>
