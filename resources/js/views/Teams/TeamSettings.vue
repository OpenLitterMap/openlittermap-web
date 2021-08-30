<template>
    <div class="tsc">
        <h1 class="title is-2">{{ $t('teams.dashboard.settings') }}</h1>

        <p v-if="!loading && !teams.length" class="mb1">{{ $t('teams.myteams.no-joined-team') }}.</p>

        <div v-if="teams.length" class="columns mt3 mb3">
            <div class="column is-one-third pt0">
                <h1 class="title">{{ $t('teams.settings.privacy-title') }}</h1>
                <p class="mb1">{{ $t('teams.settings.privacy-text') }}</p>
            </div>

            <div class="column is-half card p2">

                <p v-if="loading">{{ $t('common.loading') }}</p>

                <div v-else>
                    <select v-model="privacySectionSelectedTeamId" class="input mb2">
                        <option v-for="team in teams" :value="team.id">
                            {{ team.name }}
                        </option>
                    </select>

                    <!-- Team Map -->
                    <h1 class="title is-4">{{ $t('teams.settings.maps.team-map') }}:</h1>
                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_name_maps" />
                        {{ $t('settings.privacy.credit-name') }}
                    </label>

                    <br>

                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_username_maps" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <p v-show="show_name_maps" class="is-green">{{ $t('teams.settings.maps.name-will-appear') }}</p>
                    <p v-show="show_username_maps" class="is-green">{{ $t('teams.settings.maps.username-will-appear') }}</p>
                    <p v-show="! show_name_maps && ! show_username_maps" class="is-red">{{ $t('teams.settings.maps.will-not-appear') }}</p>

                    <!-- Team Leaderboard -->
                    <h1 class="title is-4 mt1">{{ $t('teams.settings.leaderboards.team-leaderboard') }}:</h1>
                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_name_leaderboards" />
                        <!-- Credit my username -->
                        {{ $t('settings.privacy.credit-name') }}
                    </label>

                    <br>

                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_username_leaderboards" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <p v-show="show_name_leaderboards" class="is-green">{{ $t('teams.settings.leaderboards.name-will-appear') }}</p>
                    <p v-show="show_username_leaderboards" class="is-green">{{ $t('teams.settings.leaderboards.username-will-appear') }}</p>
                    <p v-show="! show_name_leaderboards && ! show_username_leaderboards" class="is-red">{{ $t('teams.settings.leaderboards.will-not-appear') }}</p>

                    <div class="flex">
                        <button :class="submitButton" @click="submit(false)" :disabled="disabled">{{ $t('teams.settings.submit-one-team') }}</button>
                        <button :class="allButton" @click="submit(true)" :disabled="disabled">{{ $t('teams.settings.apply-all-teams') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="teamsLedByUser.length" class="columns mb3">
            <div class="column is-one-third pt0">
                <h1 class="title">{{ $t('teams.settings.team-update-title') }}</h1>
                <p class="mb1">{{ $t('teams.settings.team-update-text') }}</p>
            </div>

            <div class="column is-half card p2">

                <p v-if="loading">{{ $t('common.loading') }}</p>

                <div v-else>
                    <form method="post" @submit.prevent="updateTeam">
                        <div class="control pb2">
                            <p>{{ $t('teams.create.select-team') }}</p>
                            <div class="select">
                                <select v-model="attributesSectionSelectedTeamId">
                                    <option v-for="team in teamsLedByUser" :value="team.id">
                                        {{ team.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="control pb2">
                            <label for="name">{{ $t('teams.create.team-name') }}</label>
                            <input
                                class="input"
                                name="name"
                                :placeholder="$t('teams.create.my-awesome-team-placeholder')"
                                v-model="attributesTeamName"
                                type="text"
                                required
                                @keydown="clearError('name')"
                            />
                            <p
                                class="is-danger"
                                v-if="getFirstError('name')"
                                v-text="getFirstError('name')"
                            />
                        </div>

                        <div class="control pb2">
                            <label for="identifier">{{ $t('teams.create.unique-team-id') }}</label>
                            <br>
                            <small>{{ $t('teams.create.id-to-join-team') }}</small>
                            <input
                                class="input"
                                id="identifier"
                                name="identifier"
                                placeholder="Awesome2021"
                                required
                                v-model="attributesTeamIdentifier"
                                @keydown="clearError('identifier')"
                            />
                            <p
                                class="is-danger"
                                v-if="getFirstError('identifier')"
                                v-text="getFirstError('identifier')"
                            />
                        </div>

                        <div>
                            <button :class="btnAll" :disabled="attributesProcessing">{{ $t('teams.create.update-team') }}</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TeamSettings',
    data ()
    {
        return {
            loading: true,
            privacySectionSelectedTeamId: 0,
            attributesSectionSelectedTeamId: 0,
            privacyAllProcessing: false,
            privacySubmitProcessing: false,
            attributesProcessing: false,
            attributesTeamName: '',
            attributesTeamIdentifier: '',
            btnAll: 'button is-medium is-primary mt1',
            btn: 'button is-medium is-warning mt1 mr1',
        };
    },
    async created ()
    {
        this.loading = true;

        if (this.teams.length === 0) await this.$store.dispatch('GET_USERS_TEAMS');

        this.privacySectionSelectedTeamId = this.teams[0]?.id;
        this.attributesSectionSelectedTeamId = this.teamsLedByUser[0]?.id;

        this.clearErrors();

        this.loading = false;
    },
    watch: {
        attributesSectionSelectedTeam () {
            this.attributesTeamName = this.attributesSectionSelectedTeam.name;
            this.attributesTeamIdentifier = this.attributesSectionSelectedTeam.identifier;
        }
    },
    computed: {

        /**
         * Add spinner when processing
         */
        allButton ()
        {
            return this.privacyAllProcessing ? this.btnAll + ' is-loading' : this.btnAll;
        },

        /**
         * Return true to disable the buttons
         */
        disabled ()
        {
            return (this.privacyAllProcessing || this.privacySubmitProcessing);
        },

        /**
         * Add spinner when processing
         */
        submitButton ()
        {
            return this.privacySubmitProcessing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         *
         */
        show_name_leaderboards: {
            get () {
                return this.team.pivot.show_name_leaderboards;
            },
            set (v) {
                this.$store.commit('team_settings', {
                    team_id: this.privacySectionSelectedTeamId,
                    key: 'show_name_leaderboards',
                    v
                });
            }
        },

        /**
         *
         */
        show_username_leaderboards: {
            get () {
                return this.team.pivot.show_username_leaderboards;
            },
            set (v) {
                this.$store.commit('team_settings', {
                    team_id: this.privacySectionSelectedTeamId,
                    key: 'show_username_leaderboards',
                    v
                });
            }
        },

        /**
         *
         */
        show_name_maps: {
            get () {
                return this.team.pivot.show_name_maps;
            },
            set (v) {
                this.$store.commit('team_settings', {
                    team_id: this.privacySectionSelectedTeamId,
                    key: 'show_name_maps',
                    v
                });
            }
        },

        /**
         *
         */
        show_username_maps: {
            get () {
                return this.team.pivot.show_username_maps;
            },
            set (v) {
                this.$store.commit('team_settings', {
                    team_id: this.privacySectionSelectedTeamId,
                    key: 'show_username_maps',
                    v
                });
            }
        },

        /**
         * Current team we are viewing on privacy section
         */
        team ()
        {
            return this.teams.find(team => team.id === this.privacySectionSelectedTeamId);
        },

        /**
         * Current team we are viewing on attributes section
         */
        attributesSectionSelectedTeam ()
        {
            return this.teamsLedByUser.find(team => team.id === this.attributesSectionSelectedTeamId);
        },

        /**
         * Array of the users teams
         */
        teams ()
        {
            return this.$store.state.teams.teams;
        },

        /**
         * Current user
         */
        user ()
        {
            return this.$store.state.user.user;
        },

        /**
         * Array of the teams where the user is the leader
         */
        teamsLedByUser ()
        {
            return this.teams.filter((team) => team.leader === this.user.id);
        },

        /**
         * Errors object from teams
         */
        errors ()
        {
            return this.$store.state.teams.errors;
        },
    },
    methods: {

        /**
         * Apply these settings to this team for this user
         */
        async submit (all)
        {
            all ? this.privacyAllProcessing = true : this.privacySubmitProcessing = true;

            await this.$store.dispatch('SAVE_TEAM_SETTINGS', {
                all,
                team_id: this.privacySectionSelectedTeamId
            });

            this.privacySubmitProcessing = false;
            this.privacyAllProcessing = false;
        },

        /**
         * Apply the updated attributes to this team
         */
        async updateTeam ()
        {
            this.attributesProcessing = true;

            await this.$store.dispatch('UPDATE_TEAM', {
                teamId: this.attributesSectionSelectedTeamId,
                name: this.attributesTeamName,
                identifier: this.attributesTeamIdentifier,
            });

            this.attributesProcessing = false;

            if (Object.keys(this.errors).length) return;

            // This will refresh the teams for other screens too
            await this.$store.dispatch('GET_USERS_TEAMS');

            // Refreshes the user's active team
            if (this.user.active_team === this.attributesSectionSelectedTeamId) {
                let updatedTeam = this.teams.find(team => team.id === this.attributesSectionSelectedTeamId)
                this.$store.commit('usersTeam', updatedTeam)
            }

        },

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
         * Get the first error from errors object
         */
        getFirstError (key)
        {
            return this.errors[key] ? this.errors[key][0] : null;
        }
    }
};
</script>

<style scoped>

    .tsc {
        margin-top: 1em;
        margin-left: 5em;
    }


    @media screen and (max-width: 768px)
    {
        .tsc {
            margin-top: 0;
            margin-left: 0;
        }
    }

</style>
