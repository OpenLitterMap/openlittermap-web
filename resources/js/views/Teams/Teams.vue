<template>
    <section>
        <div class="columns is-mobile mx-0">
            <div
                :class="['column teams-left-col',
                         showSideBar ?
                             'is-12-mobile is-3-desktop is-2-fullhd is-3-tablet open-side-bar':
                             'close-side-bar']"
            >
                <div class="team-flex" @click="toggleSideBar">
                    <i
                        :class="[showSideBar ? 'fa fa-angle-left': 'fa fa-angle-right', 'teams-icon']"
                    />
                    <p v-if="showSideBar" class="teams-title">
                        OpenLitterMap Teams
                    </p>
                </div>
                <div v-for="i in items" class="team-flex" @click="goto(i.component)">
                    <i :class="i.icon" />
                    <p v-if="showSideBar">
                        {{ i.name }}
                    </p>
                </div>
            </div>
            <div class="column team-right-col" style="background-color: #edf1f4;">
                <loading v-if="loading" :active="loading" :is-full-page="true" />
                <component :is="type" v-else />
            </div>
        </div>
    </section>
</template>

<script>
import TeamsDashboard from './TeamsDashboard';
import CreateTeam from './CreateTeam';
import JoinTeam from './JoinTeam';
import MyTeams from './MyTeams';
import Loading from 'vue-loading-overlay';

export default {
    name: 'Teams',
    components: { TeamsDashboard, CreateTeam, JoinTeam, MyTeams, Loading },
    data ()
    {
        return {
            loading: true,
            items: [
                { id: 1, name: 'Dashboard', icon: 'fa fa-home teams-icon', component: 'TeamsDashboard' },
                { id: 2, name: 'Create a Team', icon: 'fa fa-plus teams-icon', component: 'CreateTeam' },
                { id: 3, name: 'Your Teams', icon: 'fa fa-users teams-icon', component: 'MyTeams' }
                // sub routes = Team members, Team charts, Team map
            ],
            showSideBar: false
        };
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
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_TEAM_TYPES');

        this.loading = false;
    },
    methods: {

        /**
         * Change component
         */
        goto (type)
        {
            this.$store.commit('teamComponent', type);
            this.showSideBar = false;
        },
        toggleSideBar ()
        {
            this.showSideBar = !this.showSideBar;
        }
    }
};
</script>

<style scoped lang='scss'>
@import '../../styles/variables.scss';

    @include media-breakpoint-down(sm){
        .open-side-bar {
            position: absolute;
            height: 100%;
            z-index: 5;
        }
    }

    .close-side-bar {
        max-width: 60px;
    }

    .team-flex {

        &:first-child {
            margin-top: 1rem;
        }

        display: flex;
        margin-bottom: 1rem;
    }

    .teams-left-col {
        background-color: #232d3f;
        height: auto;
        min-height: calc(100vh - 70px);
        color: #d3d8e0;
    }

    .team-right-col {
        padding-top: 3rem;
    }

    @include media-breakpoint-down(sm){
        .team-right-col {
            padding-top: 2rem;
        }
    }

    .teams-icon {
        cursor: pointer;
        text-align: center;
        font-size: 1.5em;
        width: 40px;
        height: 30px;
    }

    .teams-title {
        font-weight: 700;
        font-size: 1rem;
        font-family: sans-serif;
    }
</style>
