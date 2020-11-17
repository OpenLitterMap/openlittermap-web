<template>
    <div>
        <p v-show="error.length > 0" class="team-error">{{ $t(this.error) }}</p>

        <form @submit.prevent="submit">
            <label for="join">Join team by identifier</label>
            <input
                class="input mb2"
                name="join"
                required
                v-model="identifier"
                @input="clearError"
            />

            <button class="button is-medium" @click="goback">Cancel</button>
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
        error ()
        {
            return this.$store.state.teams.error;
        }

    },
    methods: {

        /**
         * If there is an error, remove it when the user types something
         */
        clearError ()
        {
            if (this.error) this.$store.commit('teamsError', '');
        },

        /**
         * Emit event to go back to Default page
         */
        goback ()
        {
            this.$emit('goback', 'Default');
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
