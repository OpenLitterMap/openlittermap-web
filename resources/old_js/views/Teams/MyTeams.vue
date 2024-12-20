<template>
    <section>
        <div class="my-teams-container">
            <h1 class="title is-2">
                {{ $t('teams.myteams.title') }}
            </h1>

            <p v-if="loading">
                {{ $t('common.loading') }}
            </p>

            <div v-else>
                <div class="active-team-indicator">
                    <div>
                        <div v-if="user.active_team" class="mb1">
                            <p>
                                {{ $t('teams.myteams.currently-joined-team') }} <strong>{{ user.team.name }}</strong>.
                                <br/>
                                {{ $t('teams.myteams.enabled-contributions') }}
                            </p>
                        </div>

                        <p v-else-if="teams && teams.length"
                           class="mb1"
                           v-html="$t('teams.myteams.disabled-contributions')">
                        </p>

                        <p v-else class="mb1">
                            {{ $t('teams.myteams.no-joined-team') }}.
                        </p>
                    </div>

                    <div v-if="user.active_team"
                         class="button is-warning tooltip"
                         @click="inactivateTeam"
                    >
                        <span class="tooltip-text disable-teams-tooltip">
                            {{ $t('teams.myteams.disable-contributions-tooltip') }}
                        </span>
                        {{ $t('common.inactivate') }}
                    </div>
                </div>

                <div class="mb1" v-if="teams && teams.length">
                    <div class="is-size-3">
                        {{ $t('teams.myteams.team-details') }}
                    </div>

                    <div v-if="isLeader">
                        <p>{{ $t('teams.myteams.leader-of-team') }}.</p>
                    </div>
                </div>

                <div v-if="teams && teams.length" style="overflow-x: scroll">
                    <div class="flex mb1">
                        <select
                            v-model="viewTeam"
                            class="input mtba"
                            style="max-width: 20em; min-width: 5em;"
                            @change="changeViewedTeam"
                        >
                            <option
                                :selected="! viewTeam"
                                :value="null"
                                disabled
                            >
                                {{ $t('teams.myteams.join-team') }}
                            </option>

                            <option
                                v-for="team in teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.name }}
                            </option>
                        </select>
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
                            <tr
                                v-for="(member, index) in members.data"
                                :key="member.id"
                            >
                                <td>
                                    <div class="medal-container">
                                        <img
                                            v-show="index < 3"
                                            :src="medal(index)"
                                            class="medal"
                                        >
                                        <span>{{ getRank(index) }}</span>
                                    </div>
                                </td>
                                <td>{{ member.name ? member.name : '-' }}</td>
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

                <div v-if="teams && teams.length" style="overflow-x: scroll">
                    <div class="is-size-3 mb1">
                        {{ $t('teams.myteams.all-my-teams') }}
                    </div>

                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                        <th>{{ $t('teams.myteams.name-header') }}</th>
                        <th>{{ $t('teams.myteams.identifier-header') }}</th>
                        <th>{{ $t('teams.myteams.members-header') }}</th>
                        <th>{{ $t('teams.myteams.photos-header') }}</th>
                        <th>{{ $t('teams.myteams.litter-header') }}</th>
                        <th>{{ $t('common.actions') }}</th>
                        </thead>

                        <tbody>
                        <tr
                            v-for="team in teams"
                            :key="team.id"
                            :class="team.id === activeTeam ? 'is-primary-row' : ''"
                        >
                            <td>{{ team.name }}</td>
                            <td>{{ team.identifier }}</td>
                            <td>{{ team.members }}</td>
                            <td>{{ team.total_images }}</td>
                            <td>{{ team.total_litter }}</td>
                            <td style="min-width: 120px;max-width: 150px;">
                                <button
                                    class="button is-small is-primary team-action tooltip"
                                    :class="processing ? 'is-loading' : ''"
                                    :disabled="team.id === activeTeam"
                                    @click="changeActiveTeam(team.id)"
                                >
                                    <span class="tooltip-text">
                                        {{
                                            team.id === activeTeam
                                                ? $t('teams.myteams.this-is-active-team')
                                                : $t('teams.myteams.set-as-active-team')
                                        }}
                                    </span>
                                    <i class="fa fa-star" />
                                </button>
                                <button
                                    class="button is-small is-info team-action tooltip"
                                    :class="dlProcessing ? 'is-loading' : ''"
                                    @click="download(team.id)"
                                >
                                    <span class="tooltip-text">{{ $t('teams.myteams.download-team-data') }}</span>
                                    <i class="fa fa-download"/>
                                </button>
                                <button
                                    :disabled="team.members <= 1"
                                    class="button is-small is-danger team-action tooltip"
                                    @click="leaveTeam(team.id)"
                                >
                                    <span class="tooltip-text">
                                        {{
                                            team.members > 1
                                                ? $t('teams.myteams.leave-team')
                                                : $t('teams.myteams.cant-leave-team')
                                        }}
                                    </span>
                                    <i class="fa fa-sign-out"/>
                                </button>
                                <button
                                    v-if="team.leader === user.id"
                                    class="button is-small is-warning team-action tooltip"
                                    @click="toggleLeaderboardVis(team.id)"
                                >
                                    <span class="tooltip-text">
                                        {{
                                            team.leaderboards
                                                ? $t('teams.myteams.hide-from-leaderboards')
                                                : $t('teams.myteams.show-on-leaderboards')
                                        }}
                                    </span>
                                    <i class="fa"
                                       :class="team.leaderboards ? 'fa-eye-slash' : 'fa-eye'"
                                    />
                                </button>
                            </td>
                        </tr>
                        </tbody>
                    </table>
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
            loading: false,
            processing: false,
            changing: false,
            viewTeam: null, // the team the user is currently looking at. Different team = load different list of members
            dlProcessing: false,
        };
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
         * Get the current page the user is on
         */
        current_page ()
        {
            return this.members.current_page;
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
    async mounted ()
    {
        this.loading = true;

        await this.getUserTeams();

        this.loading = false;
    },
    methods: {
        /**
         * Change currently active team
         */
        async changeActiveTeam (teamId)
        {
            this.processing = true;

            await this.$store.dispatch('CHANGE_ACTIVE_TEAM', teamId);

            this.processing = false;
        },

        /**
         * Inactivate the currently active team
         */
        async inactivateTeam ()
        {
            this.processing = true;

            await this.$store.dispatch('INACTIVATE_TEAM');

            this.viewTeam = this.teams[0]?.id;

            await this.changeViewedTeam();

            this.processing = false;
        },

        /**
         * Get the user's teams and show the active team
         */
        async getUserTeams ()
        {
            await this.$store.dispatch('GET_USERS_TEAMS');

            let teamToShow = this.activeTeam || this.teams[0]?.id;

            if (teamToShow)
            {
                this.viewTeam = teamToShow;

                await this.$store.dispatch('GET_TEAM_MEMBERS', this.viewTeam);
            }
        },

        /**
         * Leave the team
         */
        async leaveTeam (teamId)
        {
            if (!confirm(this.$t('teams.myteams.confirm-leave-team'))) {
                return;
            }

            this.loading = true;

            await this.$store.dispatch('LEAVE_TEAM', teamId);

            await this.getUserTeams();

            this.loading = false;
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
        async download (teamId)
        {
            this.dlProcessing = true;

            await this.$store.dispatch('DOWNLOAD_DATA_FOR_TEAM', teamId);

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
        async toggleLeaderboardVis (teamId)
        {
            await this.$store.dispatch('TOGGLE_LEADERBOARD_VISIBILITY', teamId);
        },

        /**
         *
         */
        formatDate (date)
        {
            return moment(date).format('LLL');
        }
    }
};
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

    .team-action {
        border-radius: 5px;
    }

    .is-primary-row {
        background-color: #00c4a730;
    }

    .disable-teams-tooltip {
        width: 250px;
        white-space: initial;
    }

    .active-team-indicator {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
    }

    @media (max-width: 640px) {
        .active-team-indicator {
            flex-direction: column;
        }
        .active-team-indicator .button {
            max-width: min-content;
            margin-bottom: 2em;
        }
        .my-teams-container {
            padding: 0;
        }
    }

</style>
