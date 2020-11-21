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

                    <div v-if="user.team" class="mb2" :key="user.team.id">
                        <p>You are currently joined team {{ user.team.name }}</p>
                    </div>

                    <div v-else class="mb2">
                        <p>You are not currently joined a team</p>
                    </div>

                    <component
                        :is="this.type"
                        :remaining="user.remaining_teams"
                    />

                </div>
            </div>
        </div>
    </div>
</template>

<script>

export default {
    name: 'Teams',

    data ()
    {
        return {
            loading: true
        };
    },
    computed: {

        /**
         * "Default", "JoinTeam" or "CreateTeam"
         */
        type ()
        {
            return this.$store.state.teams.component_type;
        },

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

        }
    }
}
</script>

<style scoped>

</style>
