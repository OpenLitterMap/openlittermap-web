<template>
    <div>
        <p class="mb2">You are allowed to create {{ this.remaining }} team(s)</p>

        <form method="post" @submit.prevent="create">
            <div class="control pb2">

                <p>Team Type</p>

                <div class="select">
                    <select v-model="teamType">
                        <option v-for="type in teamTypes" :value="type.id">{{ type.team }}</option>
                    </select>
                </div>
            </div>

            <label for="name">Team Name</label>
            <span
                class="is-danger"
                v-if="errorExists('name')"
                v-text="getFirstError('name')"
            />
            <input
                class="input mb2"
                name="name"
                placeholder="My Awesome Team"
                v-model="name"
                type="text"
                required
                @keydown="clearError('name')"
            />

            <label for="identifier">Team Identifier</label>
            <p>Anyone with this ID will be able to join your team.</p>
            <span
                class="is-danger"
                v-if="errorExists('identifier')"
                v-text="getFirstError('identifier')"
            />
            <input
                class="input mb2"
                name="identifier"
                placeholder="Awesome2020"
                required
                v-model="identifier"
                @keydown="clearError('identifier')"
            />

            <div>
                <button class="button is-medium" @click.prevent="goback">Cancel</button>
                <button :class="button" :disabled="processing">Create Team</button>
            </div>
        </form>
    </div>
</template>

<script>
/* Todo - show failed validation errors */
/* Todo - translations */
export default {
    name: 'CreateTeam',
    props: ['remaining'],
    data ()
    {
        return {
            btn: 'button is-medium is-primary',
            processing: false,
            identifier: '',
            name: '',
            teamType: 1
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
         * Errors object from teams
         */
        errors ()
        {
            return this.$store.state.teams.errors;
        },

        /**
         * Types of teams from the database
         */
        teamTypes ()
        {
            return this.$store.state.teams.types;
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
         * Create a new team
         */
        async create ()
        {
            this.processing = true;

            await this.$store.dispatch('CREATE_NEW_TEAM', {
                name: this.name,
                identifier: this.identifier,
                teamType: this.teamType
            });

            this.processing = false;
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
         * Change component the user can see
         */
        goback ()
        {
            this.$store.commit('teamComponent', 'Default');
        }
    }
}
</script>

<style scoped>

</style>
