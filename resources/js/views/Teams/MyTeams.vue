<template>
    <section>
        <div class="my-teams-container">
            <h1 class="title is-2">{{ $t('teams.myteams.title') }}</h1>

            <p v-if="loading">{{ $t('common.loading') }}</p>

            <div v-else>

                <div v-if="user.active_team" class="mb2" :key="user.team.id">
                    <p>{{ $t('teams.myteams.currently-joined-team') }} <strong>{{ user.team.name }}</strong>.</p>
                </div>

                <p v-else>{{ $t('teams.myteams.no-joined-team') }}.</p>

                <div v-if="isLeader" class="mb2">
                    <p>{{ $t('teams.myteams.leader-of-team') }}.</p>
                </div>

                <div v-if="teams" style="overflow-x: scroll">
                    <div class="flex mb1">
                        <select v-model="viewTeam" class="input mtba" style="max-width: 30em; min-width: 5em;" @change="changeViewedTeam">
                            <option :selected="! viewTeam" :value="null" disabled>{{ $t('teams.myteams.join-team') }}</option>
                            <option v-for="team in teams" :value="team.id">{{ team.name }}</option>
                        </select>

                        <button :class="button" @click="changeActiveTeam" :disabled="disabled">{{ $t('teams.myteams.change-active-team') }}</button>
                        <button :class="downloadClass" :disabled="dlProcessing" @click="download">{{ $t('teams.myteams.download-team-data') }}</button>
                        <button
                            v-if="isLeader"
                            :class="leaderboardClass"
                            :disabled="leaderboardProcessing"
                            @click="toggleLeaderboardVis"
                        >{{ showLeaderboard }}</button>
                    </div>

                    <table class="table is-fullwidth is-hoverable has-text-centered">
                        <thead>
                            <th>{{ $t('teams.myteams.position-header') }}</th>
                            <th>{{ $t('teams.myteams.name-header') }}</th>
                            <th>{{ $t('teams.myteams.username-header') }}</th>
                            <th>{{ $t('teams.myteams.status-header') }}</th>
                            <th>{{ $t('teams.myteams.photos-header') }}</th>
                            <th>{{ $t('teams.myteams.litter-header') }}</th>
                            <th>{{ $t('teams.myteams.last-activity-header') }}</th>
                        </thead>

                        <tbody>
                            <tr v-for="(member, index) in members.data">
                                <td>
                                    <div class="medal-container">
                                        <img
                                            v-show="index < 3"
                                            :src="medal(index)"
                                            class="medal"
                                        />
                                        <span>{{ getRank(index) }}</span>
                                    </div>
                                </td>
                                <td>{{ member.name ? member.name : '-'}}</td>
                                <td>{{ member.username ? member.username: '-' }}</td>
                                <td style="width: 9em;white-space: nowrap">
                                    <span :class="checkActiveTeam(member.active_team)">
                                        <i :class="icon(member.active_team)" />
                                        {{ checkActiveTeamText(member.active_team) }}
                                    </span>
                                </td>
                                <td>{{ member.pivot.total_photos }}</td>
                                <td>{{ member.pivot.total_litter }}</td>
                                <td style="max-width: 100px">
                                    {{ member.pivot.updated_at ? formatDate(member.pivot.updated_at) : "-" }}
                                </td>
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
                        >{{ $t('common.previous') }}</a>

                        <!-- Next Page -->
                        <a
                            v-show="this.show_next_page"
                            class="pagination-next"
                            @click="nextPage"
                        >{{ $t('common.next-page') }}</a>
                    </div>
                </div>

                <div v-else class="mb2">
                    <p>{{ $t('teams.myteams.currently-not-joined-team') }}</p>
                </div>
            </div>
        </div>
    </section>
</template>

<script>
import moment from 'moment';

export default {
    name: 'MyTeams',
    data ()
    {
        return {
            btn: 'button is-medium is-primary ml1',
            loading: false,
            processing: false,
            changing: false,
            viewTeam: null, // the team the user is currently looking at. Different team = load different list of members
            dlProcessing: false,
            dlButtonClass: 'button is-medium is-info ml1',
            leaderboardClass: 'button is-medium is-warning ml1',
            leaderboardProcessing: false
        };
    },
    async mounted ()
    {
        this.loading = true;

        await this.$store.dispatch('GET_USERS_TEAMS');

        if (this.activeTeam)
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

            return this.viewTeam === this.activeTeam;
        },

        /**
         * Add spinner to download button class when processing
         */
        downloadClass ()
        {
            return this.dlProcessing ? this.dlButtonClass + ' is-loading' : this.dlButtonClass;
        },

        /**
         * Check if the user.id
         */
        isLeader ()
        {
            const team = this.teams.find(team => team.id === this.viewTeam);

            return team && team.leader === this.user.id;
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
         * Return bool for current team being looked at
         */
        showLeaderboard ()
        {
            return this.teams.find(team => team.id === this.viewTeam).leaderboards
                ? this.$t('teams.myteams.hide-from-leaderboards')
                : this.$t('teams.myteams.show-on-leaderboards');
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

            await this.changeViewedTeam();

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
        checkActiveTeam (active_team_id)
        {
            return active_team_id === this.viewTeam ? 'team-active' : 'team-inactive';
        },

        /**
         * Return text if the user is joined the team or not
         *
         * Todo - translate
         */
        checkActiveTeamText (active_team_id)
        {
            if (this.changing) return '...';

            return active_team_id === this.viewTeam ? this.$t('common.active') : this.$t('common.inactive');
        },

        /**
         * Download the data from this Team
         */
        async download ()
        {
            this.dlProcessing = true;

            await this.$store.dispatch('DOWNLOAD_DATA_FOR_TEAM', this.viewTeam);

            this.dlProcessing = false;
        },

        /**
         * Return the correct position for every rank in the leaderboard
         */
        getRank (index)
        {
            if (this.members.current_page === 1) return index + 1; // 1-10

            return (index + 1) + ((this.members.current_page -1) * 10); // 11-19
        },

        /**
         * Return icon class for active/inactive team
         */
        icon (active_team_id)
        {
            return active_team_id === this.viewTeam ? 'fa fa-check' : 'fa fa-ban';
        },

        /**
         * Return medal for 1st, 2nd and 3rd
         */
        medal (i)
        {
            if (this.members.current_page === 1)
            {
                if (i === 0) return '/assets/icons/gold-medal.png';
                if (i === 1) return '/assets/icons/silver-medal.png';
                if (i === 2) return '/assets/icons/bronze-medal.svg';
            }

            return '';
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
        },

        /**
         *
         */
        async toggleLeaderboardVis ()
        {
            await this.$store.dispatch('TOGGLE_LEADERBOARD_VISIBILITY', this.viewTeam);
        },

        /**
         *
         */
        formatDate(date)
        {
            return moment(date).format('LLL')
        }
    }
}
</script>

<style scoped>

    .medal-container {
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative
    }

    .medal {
        height: 1em;
        position: absolute;
        left: 2em;
    }

    .my-teams-container {
        padding: 0 1em;
    }

    .team-active {
        background-color: #2ecc71;
        padding: 0.5em 1em;
        border-radius: 10px;
    }

    .team-inactive {
        background-color: #e67e22;
        padding: 0.5em 1em;
        border-radius: 10px;
    }

</style>
