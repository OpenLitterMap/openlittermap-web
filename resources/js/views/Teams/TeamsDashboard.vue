<template>
    <section class="tdc">
        <p class="subtitle is-centered is-3">{{ $t('teams.dashboard.teams-dashboard') }}</p>

        <div class="columns">
            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ photos_count }}</span>
                <br>
                {{ $t('teams.dashboard.photos-uploaded') }} {{ this.getPeriod() }}
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ litter_count }}</span>
                <br>
                {{ $t('teams.dashboard.litter-tagged') }} {{ this.getPeriod() }}
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ members_count }}</span>
                <br>
                {{ $t('teams.dashboard.members-uploaded') }} {{ this.getPeriod() }}
            </div>
        </div>

        <div class="mobile-teams-select">
            <!-- Change time period -->
            <select v-model="period" @change="changeTeamOrTime" class="input dash-time">
                <option v-for="time in timePeriods" :value="time">{{ getPeriod(time) }}</option>
            </select>

            <div style="flex: 0.1;" />

            <!-- All or Select Team -->
            <select v-model="viewTeam" @change="changeTeamOrTime" class="input dash-time">
                <option value="0" selected>{{ $t('teams.dashboard.all-teams') }}</option>
                <option v-for="team in teams" :value="team.id">{{ team.name }}</option>
            </select>
        </div>

        <TeamMap :team-id="viewTeam" />

    </section>
</template>

<script>
import TeamMap from '../../components/Teams/TeamMap';

export default {
    name: 'TeamsDashboard',
    components: {
        TeamMap
    },
    async created ()
    {
        await this.changeTeamOrTime();
    },
    data ()
    {
        return {
            period: 'all',
            timePeriods: [
                'today',
                'week',
                'month',
                'year',
                'all'
            ],
            viewTeam: 0
        };
    },
    computed: {

        /**
         * Total litter uploaded during this period
         */
        litter_count ()
        {
            return this.$store.state.teams.allTeams.litter_count ?? 0;
        },

        /**
         * Total photos uploaded during this period
         */
        photos_count ()
        {
            return this.$store.state.teams.allTeams.photos_count ?? 0;
        },

        /**
         * Total number of members who uploaded photos during this period
         */
        members_count ()
        {
            return this.$store.state.teams.allTeams.members_count ?? 0;
        },

        /**
         * Teams the user has joined
         *
         * Show active team
         */
        teams ()
        {
            return this.$store.state.teams.teams;
        }
    },
    methods: {

        /**
         * Change the time period for what data is visible on the dashboard
         */
        async changeTeamOrTime ()
        {
            await this.$store.dispatch('GET_TEAM_DASHBOARD_DATA', {
                period: this.period,
                team_id: this.viewTeam
            });
        },

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.dashboard.times.' + period)
        },
    }
}
</script>

<style scoped>

    .dash-time {
        width: 25%;
    }

    .mobile-teams-select {
        display: flex;
        justify-content: center;
    }

    .tdc {
        padding-left: 2em;
        padding-right: 2em;
    }

    .teams-card {
        background: white;
        text-align: center;
        margin: 1em;
        padding: 5em;
    }

    .teams-dashboard-subtitle {
        margin-bottom: 1em;
    }

    @media screen and (max-width: 768px)
    {
        .dash-time {
            width: 100%;
            margin-bottom: 1em;
        }

        .mobile-teams-select {
            display: block;
            justify-content: center;
        }

        .teams-card {
            padding: 3em;
        }

        .teams-dashboard-subtitle {
            margin-bottom: 2em;
        }
    }


</style>
