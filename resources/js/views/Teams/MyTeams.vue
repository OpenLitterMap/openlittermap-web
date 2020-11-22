<template>
    <section>
        <div>
            <h1 class="title is-2">My Teams</h1>

            <div v-if="user.active_team" class="mb2" :key="user.team.id">
                <p>You are currently joined team {{ user.team.name }}</p>

                <select v-model="activeTeam" class="input mb1" style="max-width: 30em;">
                    <option v-for="team in teams" :value="team.id">{{ team.name }}</option>
                </select>

                <table class="table is-fullwidth">

                    <thead>
                        <th>Position</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Photos</th>
                        <th>Litter</th>
                        <th>Last Upload</th>
                    </thead>

                    <tbody>
                        <tr v-for="(member, index) in members.data">
                            <td>{{ index + 1 }}</td>
                            <td>{{ member.name }}</td>
                            <td>active team</td>
                            <td>{{ member.pivot.total_photos }}</td>
                            <td>{{ member.pivot.total_litter }}</td>
                            <!-- todo - last_uploaded -->
                            <td>{{ member.updated_at }}</td>
                        </tr>
                    </tbody>

                </table>
            </div>

            <div v-else class="mb2">
                <p>You are not currently joined a team</p>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'MyTeams',
    async created ()
    {
        this.processing = true;

        await this.$store.dispatch('GET_USERS_TEAMS');
        await this.$store.dispatch('GET_TEAM_MEMBERS', this.activeTeam); // active team

        this.processing = false;
    },
    computed: {

        /**
         * The users currently active team
         */
        activeTeam ()
        {
            return this.$store.state.user.user.active_team;
        },

        /**
         * Members for the team currently in view
         */
        members ()
        {
            return this.$store.state.teams.members;
        },

        /**
         * Array of all teams the user has joined
         */
        teams ()
        {
            return this.$store.state.teams.teams;
        },

        /**
         * Current user
         */
        user ()
        {
            return this.$store.state.user.user;
        }
    }
}
</script>

<style scoped>

</style>
