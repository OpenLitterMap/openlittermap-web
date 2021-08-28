<template>
    <section>
        <div class="columns">

            <div class="column is-one-fifth teams-left-col">
                <p class="teams-title">{{ $t('teams.dashboard.olm-teams') }}</p>

                <div v-for="i in items" class="team-flex" @click="goto(i.component)">
                    <i :class="i.icon" />
                    <p class="mtba">{{ i.name }}</p>
                </div>
            </div>

            <div class="column pt3 mobile-teams-padding" style="background-color: #edf1f4;">
                <p v-if="loading">{{ $t('common.loading') }}</p>

                <component v-else :is="type" />
            </div>
        </div>
    </section>
</template>

<script>
import TeamsDashboard from './TeamsDashboard'
import CreateTeam from './CreateTeam'
import JoinTeam from './JoinTeam'
import MyTeams from './MyTeams'
import TeamSettings from './TeamSettings'
import TeamsLeaderboard from './TeamsLeaderboard'

export default {
    name: 'Teams',
    components: {
        TeamsDashboard,
        CreateTeam,
        JoinTeam,
        MyTeams,
        TeamSettings,
        TeamsLeaderboard
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_TEAM_TYPES');

        if (this.teams.length === 0) await this.$store.dispatch('GET_USERS_TEAMS');

        this.loading = false;
    },
    data ()
    {
        return {
            loading: true,
            items: [
                { name: this.$t('teams.dashboard.dashboard'), icon: 'fa fa-home teams-icon', component: 'TeamsDashboard' },
                { name: this.$t('teams.dashboard.join-a-team'), icon: 'fa fa-sign-in teams-icon', component: 'JoinTeam' },
                { name: this.$t('teams.dashboard.create-a-team'), icon: 'fa fa-plus teams-icon', component: 'CreateTeam' },
                { name: this.$t('teams.myteams.title'), icon: 'fa fa-users teams-icon', component: 'MyTeams' },
                { name: this.$t('teams.dashboard.leaderboard'), icon: 'fa fa-trophy teams-icon', component: 'TeamsLeaderboard' },
                // todo - sub routes = Team members, Team charts, Team map
                { name: this.$t('teams.dashboard.settings'), icon: 'fa fa-gear teams-icon', component: 'TeamSettings' }
            ]
        }
    },
    computed: {

        /**
         * Array of teams the user has joined
         */
        teams ()
        {
            return this.$store.state.teams.teams;
        },

        /**
         * What component to show
         */
        type ()
        {
            return this.$store.state.teams.component_type;
        }
    },
    methods: {

        /**
         * Change component
         */
        goto (type)
        {
            this.$store.commit('teamComponent', type);
        }
    }
}
</script>

<style lang="scss" scoped>

    @import '../../styles/variables.scss';

    .fa-users {
        font-size: 1.75rem !important;
    }

    .team-flex {
        display: flex;
        margin-bottom: 1em;
        cursor: pointer;
    }

    .teams-left-col {
        background-color: #232d3f;
        min-height: calc(100vh - 70px);
        padding-left: 2em;
        color: #d3d8e0;
    }

    .teams-icon {
        margin: auto 1em auto 0;
        font-size: 2em;
    }

    .teams-title {
        font-size: 1.75rem;
        font-family: sans-serif;
        margin-top: 1em;
        margin-bottom: 1em;
    }

    @include media-breakpoint-down (sm)
    {
        .columns {
            margin-right: 0;
        }

        .teams-left-col {
            background-color: #232d3f;
            height: auto;
            min-height: auto;
            padding-left: 2em;
            color: #d3d8e0;
        }

        .mobile-teams-padding {
            padding-left: 1.5em;
            padding-bottom: 5em;
        }
    }

</style>
