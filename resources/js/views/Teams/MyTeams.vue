<template>
    <section>
        <div>
            <h1 class="title is-2">My Teams</h1>

            <p v-if="loading">Loading...</p>

            <div v-else>

            <div v-if="user.active_team" class="mb2" :key="user.team.id">
                <p>You are currently joined team {{ user.team.name }}</p>
            </div>

            <p v-else>You have not yet joined a team</p>

                <div v-if="teams">

                    <div class="flex mb1">
                        <select v-model="viewTeam" class="input mtba" style="max-width: 30em;" @change="changeViewedTeam">
                            <option :selected="! viewTeam" :value="null" disabled>Please join a team</option>
                            <option v-for="team in teams" :value="team.id">{{ team.name }}</option>
                        </select>

                        <button :class="button" @click="changeActiveTeam" :disabled="disabled">Change active team</button>
                    </div>

                    <table class="table is-fullwidth has-text-centered">
                        <thead>
                            <th>Position</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Photos</th>
                            <th>Litter</th>
                            <th>Last Activity</th>
                        </thead>

                        <tbody>
                            <tr v-for="(member, index) in members.data">
                                <td>{{ index + 1 }}</td>
                                <td>{{ member.name }}</td>
                                <td>{{ member.username }}</td>
                                <td :class="checkActiveTeam(user.active_team)" v-html="checkActiveTeamText(user.active_team)">
                                <td>{{ member.pivot.total_photos }}</td>
                                <td>{{ member.pivot.total_litter }}</td>
                                <!-- todo - last_uploaded -->
                                <td>{{ member.pivot.updated_at ? member.pivot.updated_at : "-" }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="has-text-centered">
                        <!-- Previous Page -->
                        <a
                            v-show="this.current_page > 1"
                            class="pagination-previous"
                            @click="previousPage"
                        >Previous</a>

                        <!-- Next Page -->
                        <a
                            v-show="this.show_next_page"
                            class="pagination-next"
                            @click="nextPage"
                        >Next page</a>
                    </div>
                </div>

                <div v-else class="mb2">
                    <p>You are not currently joined a team</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
export default {
    name: 'MyTeams',
    data ()
    {
        return {
            btn: 'button is-medium is-primary ml1',
            loading: false,
            processing: false,
            changing: false,
            viewTeam: null // the team the user is currently looking at. Different team = load different list of members
        };
    },
    async created ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_USERS_TEAMS');

        if (this.user.active_team)
        {
            this.viewTeam = this.activeTeam;

            await this.$store.dispatch('GET_TEAM_MEMBERS', this.viewTeam);
        }

        this.loading = false;
    },
    computed: {

        /**
         * Users currently active team
         */
        activeTeam ()
        {
            return this.user.active_team;
        },

        /**
         * Add spinner when processing
         */
        button ()
        {
            return this.processing ? this.btn + ' is-loading' : this.btn;
        },

        /**
         * Get the current page the user is on
         */
        current_page ()
        {
            return this.members.current_page;
        },

        /**
         * Return true to disable the JoinTeam button
         */
        disabled ()
        {
            if (this.processing) return true;

            if (! this.viewTeam) return true;

            if (this.viewTeam === this.activeTeam) return true;

            return false;
        },

        /**
         * Paginated object for the team currently in view
         *
         * Array of team members exist at members.data
         */
        members ()
        {
            return this.$store.state.teams.members;
        },

        /**
         * Only show Previous button if current page is greater than 1
         * If current page is 1, then we don't need to show the previous page button.
         */
        show_current_page ()
        {
            return this.members.current_page > 1;
        },

        /**
         * Only show Previous button if next_page_url exists
         */
        show_next_page ()
        {
            return this.members.next_page_url;
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
    },
    methods: {

        /**
         * Change currently active team
         */
        async changeActiveTeam ()
        {
            this.processing = true;

            await this.$store.dispatch('CHANGE_ACTIVE_TEAM', this.viewTeam);

            this.viewTeam = this.activeTeam;

            this.processing = false;
        },

        /**
         * Change what team members the user is currently looking at
         */
        async changeViewedTeam ()
        {
            this.changing = true;

            await this.$store.dispatch('GET_TEAM_MEMBERS', this.viewTeam);

            this.changing = false;
        },

        /**
         * Return class to show if user is currently joined this team or not
         */
        checkActiveTeam (users_active_team)
        {
            return users_active_team === this.viewTeam ? 'team-active' : 'team-inactive';
        },

        /**
         * Return text if the user is joined the team or not
         *
         * Todo - translate
         */
        checkActiveTeamText (users_active_team)
        {
            if (this.changing) return '...';

            return parseInt(users_active_team) === parseInt(this.viewTeam) ? 'Active' : 'Inactive';
        },

        /**
         * Load the previous page of members
         */
        previousPage ()
        {
            this.$store.dispatch('PREVIOUS_MEMBERS_PAGE', this.viewTeam);
        },

        /**
         * Load the next page of members
         */
        nextPage ()
        {
            this.$store.dispatch('NEXT_MEMBERS_PAGE', this.viewTeam);
        }
    }
}
</script>

<style scoped>

</style>
