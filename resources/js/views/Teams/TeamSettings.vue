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

                    <!-- Team Maps -->
                    <h1 class="title is-4">{{ $t('settings.privacy.maps') }}:</h1>
                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_name_maps" />
                        {{ $t('settings.privacy.credit-name') }}
                    </label>

                    <br>

                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_username_maps" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <p v-show="show_name_maps" class="is-green">Your name will appear on the maps</p>
                    <p v-show="show_username_maps" class="is-green">Your username will appear on the maps</p>
                    <p v-show="! show_name_maps && ! show_username_maps" class="is-red">Your name and username will not appear on the maps</p>

                    <!-- Team Leaderboard -->
                    <h1 class="title is-4 mt1">{{ $t('settings.privacy.leaderboards') }}:</h1>
                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_name_leaderboards" />
                        {{ $t('settings.privacy.credit-name') }}
                    </label>

                    <br>

                    <label class="checkbox mb1">
                        <input type="checkbox" v-model="show_username_leaderboards" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <p v-show="show_name_leaderboards" class="is-green">Your name will appear on the leaderboard</p>
                    <p v-show="show_username_leaderboards" class="is-green">Your username will appear on the leaderboard</p>
                    <p v-show="! show_name_leaderboards && ! show_username_leaderboards" class="is-red">Your name and username will not appear on the leaderboard</p>

                    <button :class="button" @click="submit">Save</button>
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
            processing: false,
            btn: 'button is-medium is-primary mt1',
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
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
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
         * Dispatch a request to save settings per Team
         */
        async submit ()
        {
            await this.$store.dispatch('SAVE_TEAM_SETTINGS', this.viewTeam);
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
