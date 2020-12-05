<template>
    <section class="container">
        <div class="dashboard-header">
            <p class="subtitle is-centered is-3">
                Teams Dashboard
            </p>
            <button class="button is-primary" @click="showJoinTeam">
                Join Team
            </button>
        </div>
        <p class="mb-3">
            Here you can find the combined impact made by all the teams you have joined.
        </p>

        <div class="columns is-mobile is-multiline">
            <div class="column is-11-mobile teams-card card level">
                <p class="title level-item" style="color: #7b848e;">
                    {{ photos_count }}
                </p>
                <p class="level-item">
                    Photos uploaded {{ this.getPeriod() }}
                </p>
            </div>

            <div class="column is-11-mobile teams-card card level">
                <p class="title level-item" style="color: #7b848e;">
                    {{ litter_count }}
                </p>
                <p class="level-item">
                    Litter tagged {{ this.getPeriod() }}
                </p>
            </div>

            <div class="column is-11-mobile teams-card card level">
                <p class="title level-item" style="color: #7b848e;">
                    {{ members_count }}
                </p>
                <p class="level-item">
                    Team members uploaded {{ this.getPeriod() }}
                </p>
            </div>
        </div>

        <!-- Change time period -->
        <select v-model="period" class="input" style="max-width: 25%;" @change="changeTime">
            <option v-for="time in timePeriods" :value="time">
                {{ getPeriod(time) }}
            </option>
        </select>

        <!-- todo - Map of all teams effort -->
    </section>
</template>

<script>
export default {
    name: 'TeamsDashboard',
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
    created ()
    {
        this.changeTime();
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

            return this.$t('teams.times.' + period);
        },

        showJoinTeam ()
        {
            this.$store.commit('showModal', {
                type: 'JoinTeam',
                title: 'Join a Team',
                action: 'JOINTEAM'
            });
        }
    }
};
</script>

<style scoped lang="scss">
@import '../../styles/variables.scss';
    .teams-card {
        border-radius: 10px;
        margin: 1em;
        padding: 3rem 1.5rem;
    }

     @include media-breakpoint-down(sm){
        .teams-card {
            border-radius: 10px;
            margin: 1em;
            padding: 2rem 1.5rem;
        }
    }

    .dashboard-header{
        display: flex;
        justify-content: space-between;
    }
</style>
