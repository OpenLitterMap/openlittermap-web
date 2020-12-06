<template>
    <section>

        <!-- remove margin-right on mobile -->
        <div class="columns">

            <div class="column is-one-fifth teams-left-col">
                <p class="teams-title">OpenLitterMap Teams</p>

                <div v-for="i in items" class="team-flex" @click="goto(i.component)">
                    <i :class="i.icon" />
                    <p>{{ i.name }}</p>
                </div>
            </div>

            <!-- add padding-left 2em on mobile -->
            <div class="column pt3 mobile-teams-padding" style="background-color: #edf1f4;">
                <p v-if="loading">Loading...</p>

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

export default {
    name: 'Teams',
    components: { TeamsDashboard, CreateTeam, JoinTeam, MyTeams },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_TEAM_TYPES');

        this.loading = false;
    },
    data ()
    {
        return {
            loading: true,
            items: [
                { id: 1, name: 'Dashboard', icon: 'fa fa-home teams-icon', component: 'TeamsDashboard' },
                { id: 2, name: 'Join a Team', icon: 'fa fa-sign-in teams-icon', component: 'JoinTeam' },
                { id: 3, name: 'Create a Team', icon: 'fa fa-plus teams-icon', component: 'CreateTeam' },
                { id: 4, name: 'Your Teams', icon: 'fa fa-users teams-icon', component: 'MyTeams' }
                // sub routes = Team members, Team charts, Team map
            ]
        }
    },
    computed: {

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

<style lang="scss">

    @import '../../styles/variables.scss';

    .fa-users {
        font-size: 1.75rem !important;
    }

    .team-flex {
        display: flex;
        margin-bottom: 1em;
        cursor: pointer;
    }

    /* remove height on mobile */
    .teams-left-col {
        background-color: #232d3f;
        height: calc(100vh - 70px);
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
            height: 100%;
            padding-left: 2em;
            color: #d3d8e0;
        }

        .mobile-teams-padding {
            padding-left: 2.5em;
            padding-bottom: 5em;
        }
    }

</style>
