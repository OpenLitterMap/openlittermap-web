<template>
    <div style="padding-left: 1em; padding-right: 1em;">

        <div v-if="loading">
            <p>Loading.....</p>
        </div>

        <div v-else>
            <h1 class="title is-4">{{ $t('settings.teams.title') }}</h1>
            <hr>
            <br>
            <div class="columns">
                <div class="column is-offset-1">

                    <div v-if="user.team" class="mb2" :key="user.team">
                        <p>You are currently joined team {{ user.team.name }}</p>
                    </div>

                    <div v-else class="mb2">
                        <p>You are not currently joined a team</p>
                    </div>

                    <component
                        :is="this.type"
                        @goto="goto"
                        :remaining="user.remaining_teams"
                    />

                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Default from "./Teams/Default"
import CreateTeam from "./Teams/CreateTeam"
import JoinTeam from "./Teams/JoinTeam"

export default {
    name: 'Teams',
    components: {
        CreateTeam,
        JoinTeam,
        Default
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_TEAM_TYPES');

        this.loading = false;
    },
    data ()
    {
        return {
            type: 'Default',
            loading: true
        };
    },
    computed: {

        /**
         * Currently authenticated user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    },
    methods: {

        /**
         * Open a component
         */
        goto (type)
        {
            this.type = type
        }
    }
}
</script>

<style scoped>

</style>
