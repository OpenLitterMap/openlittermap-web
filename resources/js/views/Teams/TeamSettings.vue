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

                    <!-- Maps -->
                    <h1 class="title is-4">{{ $t('settings.privacy.maps') }}:</h1>
                    <label class="checkbox">
                        <input type="checkbox" v-model="show_name_maps" />
                        {{ $t('settings.privacy.credit-name') }}
                    </label>
                    <br>

                    <label class="checkbox">
                        <input type="checkbox" v-model="show_username_maps" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <!-- Leaderboard -->
                    <h1 class="title is-4 mt1">{{ $t('settings.privacy.leaderboards') }}:</h1>
                    <label class="checkbox">
                        <input type="checkbox" v-model="show_name_leaderboard" />
                        {{ $t('settings.privacy.credit-name') }}
                    </label>
                    <br>

                    <label class="checkbox">
                        <input type="checkbox" v-model="show_username_leaderboard" />
                        {{ $t('settings.privacy.credit-username') }}
                    </label>

                    <br>
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
            viewTeam: 0
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
         *
         */
        show_name_leaderboard: {
            get () {
                return false;
            },
            set (v) {
                console.log(v);
            }
        },

        /**
         *
         */
        show_username_leaderboard: {
            get () {
                return false;
            },
            set (v) {
                console.log(v);
            }
        },

        /**
         *
         */
        show_name_maps: {
            get () {
                return false;
            },
            set (v) {
                console.log(v);
            }
        },

        /**
         *
         */
        show_username_maps: {
            get () {
                return false;
            },
            set (v) {
                console.log(v);
            }
        },

        /**
         * Array of the users teams
         */
        teams ()
        {
            return this.$store.state.teams.teams;
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
