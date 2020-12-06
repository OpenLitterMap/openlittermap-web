<template>
    <section class="tdc">
        <p class="subtitle is-centered is-3">Teams Dashboard</p>
        <p class="teams-dashboard-subtitle">Here you can find the combined impact made by all the teams you have joined.</p>

        <div class="columns">
            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ photos_count }}</span>
                <br>
                Photos uploaded {{ this.getPeriod() }}
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ litter_count }}</span>
                <br>
                Litter tagged {{ this.getPeriod() }}
            </div>

            <div class="column teams-card">
                <span class="title is-2" style="color: #7b848e;">{{ members_count }}</span>
                <br>
                Team members uploaded {{ this.getPeriod() }}
            </div>
        </div>

        <!-- Change time period -->
        <select v-model="period" @change="changeTime" class="input" style="max-width: 25%;">
            <option v-for="time in timePeriods" :value="time">{{ getPeriod(time) }}</option>
        </select>

        <!-- todo - Map of all teams effort -->
    </section>
</template>

<script>
export default {
    name: 'TeamsDashboard',
    created ()
    {
        this.changeTime();
    },
    data ()
    {
        return {
            period: 'today',
            timePeriods: [
                'today',
                'week',
                'month',
                'year',
                'all'
            ]
        };
    },
    computed: {

        /**
         * Total litter uploaded during this period
         */
        litter_count ()
        {
            return this.$store.state.teams.allTeams.litter_count;
        },

        /**
         * Total photos uploaded during this period
         */
        photos_count ()
        {
            return this.$store.state.teams.allTeams.photos_count;
        },

        /**
         * Total number of members who uploaded photos during this period
         */
        members_count ()
        {
            return this.$store.state.teams.allTeams.members_count;
        }
    },
    methods: {

        /**
         * Change the time period for what data is visible on the dashboard
         */
        changeTime ()
        {
            this.$store.dispatch('GET_COMBINED_TEAM_EFFORT', this.period);
        },

        /**
         * Return translated time period
         */
        getPeriod (period)
        {
            if (! period) period = this.period;

            return this.$t('teams.times.' + period)
        },
    }
}
</script>

<style scoped>

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
        .teams-card {
            padding: 3em;
        }

        .teams-dashboard-subtitle {
            margin-bottom: 2em;
        }
    }


</style>
