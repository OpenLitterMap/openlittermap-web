<template>
    <div class="tsc">
        <h1 class="title is-2">Join a Team</h1>

        <div class="columns mt3">

            <div class="column is-one-third">
                <p class="mb1">Control your privacy for every team you have joined.</p>
            </div>

            <div class="column is-half card p2">

                <p v-if="loading">Loading...</p>

                <div v-else>
                    <select v-model="viewTeam" class="input mb2">
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
    </div>
</template>

<script>
export default {
    name: 'TeamSettings',
    data ()
    {
        return {
            loading: true,
            viewTeam: 0,
            allProcessing: false,
            submitProcessing: false,
            btnAll: 'button is-medium is-primary mt1',
            btn: 'button is-medium is-warning mt1 mr1',
        };
    },
    async created ()
    {
        this.loading = true;

        if (this.teams.length === 0) await this.$store.dispatch('GET_USERS_TEAMS');

        this.viewTeam = this.teams[0].id;

        this.loading = false;
    },
    computed: {

        /**
         * Add spinner when processing
         */
        allButton ()
        {
            return this.allProcessing ? this.btnAll + ' is-loading' : this.btnAll;
        },

        /**
         * Return true to disable the buttons
         */
        disabled ()
        {
            return (this.allProcessing || this.submitProcessing);
        },

        /**
         * Add spinner when processing
         */
        submitButton ()
        {
            return this.submitProcessing ? this.btn + ' is-loading' : this.btn;
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
                    team_id: this.viewTeam,
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
                    team_id: this.viewTeam,
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
                    team_id: this.viewTeam,
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
                    team_id: this.viewTeam,
                    key: 'show_username_maps',
                    v
                });
            }
        },

        /**
         * Current team we are viewing
         */
        team ()
        {
            return this.teams.find(team => team.id === this.viewTeam);
        },

        /**
         * Array of the users teams
         */
        teams ()
        {
            return this.$store.state.teams.teams;
        }
    },
    methods: {

        /**
         * Apply these settings to this team for this user
         */
        async submit (all)
        {
            all ? this.allProcessing = true : this.submitProcessing = true;

            await this.$store.dispatch('SAVE_TEAM_SETTINGS', {
                all,
                team_id: this.viewTeam
            });

            this.submitProcessing = false;
            this.allProcessing = false;
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
