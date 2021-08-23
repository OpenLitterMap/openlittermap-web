<template>
    <div class="ctc">
        <h1 class="title is-2">{{ $t('teams.dashboard.create-a-team') }}</h1>

        <p class="mb2">{{ $t('teams.create.allowed-to-create', { teams:  this.remaining } ) }}.</p>

        <div v-if="remaining" class="columns mt3">

            <div class="column is-one-third">
                <p class="mb1">{{ $t('teams.create.what-kind-of-team') }}</p>
            </div>

            <div class="column is-half card p2">
                <form method="post" @submit.prevent="create">
                    <div class="control pb2">

                        <p>{{ $t('teams.create.team-type') }}</p>

                        <div class="select">
                            <select v-model="teamType">
                                <option v-for="type in teamTypes" :value="type.id">{{ type.team }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="control pb2">
                        <label for="name">{{ $t('teams.create.team-name') }}</label>
                        <input
                            class="input"
                            name="name"
                            :placeholder="$t('teams.create.my-awesome-team-placeholder')"
                            v-model="name"
                            type="text"
                            required
                            @keydown="clearError('name')"
                        />
                        <p
                            class="is-danger"
                            v-if="errorExists('name')"
                            v-text="getFirstError('name')"
                        />
                    </div>

                    <div class="control pb2">
                        <label for="identifier">{{ $t('teams.create.unique-team-id') }}</label>
                        <br>
                        <small>{{ $t('teams.create.id-to-join-team') }}</small>
                        <input
                            class="input"
                            name="identifier"
                            placeholder="Awesome2021"
                            required
                            v-model="identifier"
                            @keydown="clearError('identifier')"
                        />
                        <p
                            class="is-danger"
                            v-if="errorExists('identifier')"
                            v-text="getFirstError('identifier')"
                        />
                    </div>

                    <!-- Todo - Checkbox -->
                    <!-- Allow people to join your team automatically? -->
                    <!-- Yes = auto-join is on -->
                    <!-- No = approval is required -->

                    <div>
                        <button :class="button" :disabled="processing">{{ $t('teams.create.create-team') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script>
/* Todo - translations */
export default {
    name: 'CreateTeam',
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
         * Number of teams the user is allowed to create
         */
        remaining ()
        {
            return this.user.remaining_teams;
        },

        /**
         * Types of teams from the database
         */
        teamTypes ()
        {
            return this.$store.state.teams.types;
        },

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },
    methods: {

        /**
         * Clear all errors
         */
        clearErrors ()
        {
            this.$store.commit('teamErrors', []);
        },

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

            this.name = "";
            this.identifier = "";
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
        }
    },
    mounted () {
        this.clearErrors();
    }
}
</script>

<style scoped>

    .ctc {
        margin-top: 1em;
        margin-left: 5em;
    }

    @media screen and (max-width: 768px)
    {
        .ctc {
            margin-top: 0;
            margin-left: 0;
        }
    }
</style>
